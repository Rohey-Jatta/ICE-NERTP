<?php

namespace App\Services;

use App\Models\Election;
use App\Models\PollingStation;

/**
 * ResultValidationService - validates vote count submissions.
 *
 * From architecture: "Validation rules, integrity checks"
 *
 * Core validations:
 * 1. Total votes <= registered voters
 * 2. Valid votes + rejected votes = total votes cast
 * 3. All candidate votes sum to valid votes
 * 4. Turnout is reasonable (not >100%)
 * 5. GPS coordinates are valid
 *
 * NOTE: this service no longer cares which election a polling station is
 * "assigned" to — $data['election_id'] is always supplied by the caller
 * as the CURRENT operational election (resolved via
 * CurrentElectionResolver), not read from the station itself.
 */
class ResultValidationService
{
    public function validateSubmission(array $data, PollingStation $station): array
    {
        $errors = [];

        // Validate registered voters
        if ($data['total_registered_voters'] !== $station->registered_voters) {
            $errors['total_registered_voters'] = "Must match station's registered voters ({$station->registered_voters})";
        }

        // Validate vote counts logic
        $totalVotesCast = $data['total_votes_cast'];
        $validVotes = $data['valid_votes'];
        $rejectedVotes = $data['rejected_votes'] ?? 0;
        $disputedVotes = $data['disputed_votes'] ?? 0;

        // Rule 1: Total votes cast <= registered voters
        if ($totalVotesCast > $data['total_registered_voters']) {
            $errors['total_votes_cast'] = "Cannot exceed registered voters ({$data['total_registered_voters']})";
        }

        // Rule 2: Valid + Rejected + Disputed = Total Cast
        $calculatedTotal = $validVotes + $rejectedVotes + $disputedVotes;
        if ($calculatedTotal !== $totalVotesCast) {
            $errors['vote_totals'] = "Valid ({$validVotes}) + Rejected ({$rejectedVotes}) + Disputed ({$disputedVotes}) must equal Total Cast ({$totalVotesCast})";
        }

        // Rule 3: Sum of candidate votes = valid votes
        if (isset($data['candidate_votes']) && is_array($data['candidate_votes'])) {
            $candidateVotesSum = array_sum(array_column($data['candidate_votes'], 'votes'));
            if ($candidateVotesSum !== $validVotes) {
                $errors['candidate_votes'] = "Sum of candidate votes ({$candidateVotesSum}) must equal valid votes ({$validVotes})";
            }

            // Validate no negative votes
            foreach ($data['candidate_votes'] as $cv) {
                if ($cv['votes'] < 0) {
                    $errors['candidate_votes'] = "Candidate votes cannot be negative";
                    break;
                }
            }
        } else {
            $errors['candidate_votes'] = "Candidate votes are required";
        }

        // Rule 4: Turnout percentage check
        if ($data['total_registered_voters'] > 0) {
            $turnout = ($totalVotesCast / $data['total_registered_voters']) * 100;
            if ($turnout > 100) {
                $errors['turnout'] = "Turnout cannot exceed 100% (calculated: {$turnout}%)";
            }
        }

        // Rule 5: GPS validation (coordinates provided)
        if (empty($data['submitted_latitude']) || empty($data['submitted_longitude'])) {
            $errors['gps'] = "GPS coordinates are required";
        }

        // Rule 6: Photo hash required
        if (empty($data['result_sheet_photo_hash'])) {
            $errors['photo'] = "Result sheet photo is required";
        }

        // Rule 7: an election_id must be present and must be resolvable as
        // an actual election. This is a sanity check only — the caller is
        // responsible for guaranteeing it is the CURRENT operational
        // election; this service does not re-resolve it itself to avoid
        // doing the resolution twice per request.
        if (empty($data['election_id']) || !Election::whereKey($data['election_id'])->exists()) {
            $errors['election'] = "A valid current election context is required to submit results.";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Check for anomalies that don't block submission but flag for review.
     */
    public function detectAnomalies(array $data, PollingStation $station): array
    {
        $warnings = [];

        // Low turnout warning
        if ($data['total_registered_voters'] > 0) {
            $turnout = ($data['total_votes_cast'] / $data['total_registered_voters']) * 100;
            if ($turnout < 30) {
                $warnings[] = "Low turnout: {$turnout}% (investigate potential issues)";
            }
        }

        // Unusually high rejected votes
        if ($data['total_votes_cast'] > 0) {
            $rejectedPercentage = ($data['rejected_votes'] / $data['total_votes_cast']) * 100;
            if ($rejectedPercentage > 5) {
                $warnings[] = "High rejected votes: {$rejectedPercentage}% (national average ~2%)";
            }
        }

        // Disputed votes present
        if (($data['disputed_votes'] ?? 0) > 0) {
            $warnings[] = "Disputed votes reported: {$data['disputed_votes']}";
        }

        return $warnings;
    }
}