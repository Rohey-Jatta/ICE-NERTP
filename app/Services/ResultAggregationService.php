<?php

namespace App\Services;

use App\Models\Election;
use App\Models\Result;
use Illuminate\Support\Facades\DB;

/**
 * ResultAggregationService - Real-time vote aggregation.
 * 
 * From architecture: app/Services/ResultAggregationService.php
 * 
 * Aggregates votes across hierarchy levels:
 * - Polling Station → Ward → Constituency → Admin Area → National
 * 
 * Uses PostgreSQL materialized views for performance.
 */
class ResultAggregationService
{
    /**
     * Aggregate results for a specific administrative level.
     */
    public function aggregateByLevel(int $electionId, string $level, int $levelId)
    {
        // Get all certified results for this level
        $results = $this->getResultsForLevel($electionId, $level, $levelId);

        if ($results->isEmpty()) {
            return null;
        }

        // Aggregate totals
        $aggregation = [
            'total_registered_voters' => $results->sum('total_registered_voters'),
            'total_votes_cast' => $results->sum('total_votes_cast'),
            'valid_votes' => $results->sum('valid_votes'),
            'rejected_votes' => $results->sum('rejected_votes'),
            'disputed_votes' => $results->sum('disputed_votes'),
            'total_polling_stations' => $results->count(),
            'certified_stations' => $results->where('certification_status', '!=', Result::STATUS_SUBMITTED)->count(),
        ];

        // Calculate turnout
        if ($aggregation['total_registered_voters'] > 0) {
            $aggregation['turnout_percentage'] = round(
                ($aggregation['total_votes_cast'] / $aggregation['total_registered_voters']) * 100,
                2
            );
        } else {
            $aggregation['turnout_percentage'] = 0;
        }

        // Aggregate candidate votes
        $candidateVotes = DB::table('result_candidate_votes')
            ->select('candidate_id', DB::raw('SUM(votes) as total_votes'))
            ->whereIn('result_id', $results->pluck('id'))
            ->groupBy('candidate_id')
            ->get();

        $aggregation['candidate_totals'] = $candidateVotes->map(function($cv) {
            return [
                'candidate_id' => $cv->candidate_id,
                'votes' => $cv->total_votes,
            ];
        });

        return $aggregation;
    }

    /**
     * Get national aggregation (all certified results).
     */
    public function getNationalAggregation(int $electionId)
    {
        $results = Result::where('election_id', $electionId)
            ->whereIn('certification_status', [
                Result::STATUS_WARD_CERTIFIED,
                Result::STATUS_CONSTITUENCY_CERTIFIED,
                Result::STATUS_ADMIN_AREA_CERTIFIED,
                Result::STATUS_NATIONALLY_CERTIFIED,
            ])
            ->get();

        if ($results->isEmpty()) {
            return null;
        }

        return $this->calculateAggregation($results);
    }

    /**
     * Get results for a specific administrative level.
     */
    private function getResultsForLevel(int $electionId, string $level, int $levelId)
    {
        $query = Result::where('election_id', $electionId)
            ->whereIn('certification_status', [
                Result::STATUS_WARD_CERTIFIED,
                Result::STATUS_CONSTITUENCY_CERTIFIED,
                Result::STATUS_ADMIN_AREA_CERTIFIED,
                Result::STATUS_NATIONALLY_CERTIFIED,
            ]);

        switch ($level) {
            case 'ward':
                $query->whereHas('pollingStation', fn($q) => $q->where('ward_id', $levelId));
                break;
            case 'constituency':
                $query->whereHas('pollingStation.ward', fn($q) => $q->where('constituency_id', $levelId));
                break;
            case 'admin_area':
                $query->whereHas('pollingStation.ward.constituency', fn($q) => $q->where('admin_area_id', $levelId));
                break;
        }

        return $query->get();
    }

    private function calculateAggregation($results)
    {
        $aggregation = [
            'total_registered_voters' => $results->sum('total_registered_voters'),
            'total_votes_cast' => $results->sum('total_votes_cast'),
            'valid_votes' => $results->sum('valid_votes'),
            'rejected_votes' => $results->sum('rejected_votes'),
            'disputed_votes' => $results->sum('disputed_votes'),
            'total_polling_stations' => $results->count(),
        ];

        if ($aggregation['total_registered_voters'] > 0) {
            $aggregation['turnout_percentage'] = round(
                ($aggregation['total_votes_cast'] / $aggregation['total_registered_voters']) * 100,
                2
            );
        } else {
            $aggregation['turnout_percentage'] = 0;
        }

        // Aggregate candidate votes
        $candidateVotes = DB::table('result_candidate_votes')
            ->select('candidate_id', DB::raw('SUM(votes) as total_votes'))
            ->whereIn('result_id', $results->pluck('id'))
            ->groupBy('candidate_id')
            ->get();

        $aggregation['candidate_totals'] = $candidateVotes;

        return $aggregation;
    }
}
