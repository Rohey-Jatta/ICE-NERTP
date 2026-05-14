<?php

namespace Database\Seeders;

use App\Models\Election;
use App\Models\Result;
use App\Models\ResultCandidateVote;
use App\Models\Candidate;
use App\Models\PollingStation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResultSeeder extends Seeder
{
    public function run()
    {
        $electionId = Election::where('slug', 'gambia-2021-presidential')->value('id');
        if (!$electionId) {
            throw new \RuntimeException('Election gambia-2021-presidential must exist before running ResultSeeder.');
        }

        // Skip if results already exist for this election
        if (Result::where('election_id', $electionId)->exists()) {
            $this->command->info('ResultSeeder: results already exist, skipping.');
            return;
        }

        $candidates = Candidate::where('election_id', $electionId)->get();

        if ($candidates->isEmpty()) {
            $this->command->warn('ResultSeeder: no candidates found, skipping.');
            return;
        }

        PollingStation::where('election_id', $electionId)->chunk(200, function ($stations) use ($candidates, $electionId) {
            foreach ($stations as $station) {
                // Skip if a result already exists for this station
                if (Result::where('polling_station_id', $station->id)
                    ->where('election_id', $electionId)
                    ->exists()) {
                    continue;
                }

                DB::transaction(function () use ($station, $candidates, $electionId) {
                    $totalRegistered = max(200, $station->registered_voters);
                    $turnout         = (int) round($totalRegistered * rand(60, 95) / 100);

                    $result = Result::create([
                        'polling_station_id'      => $station->id,
                        'election_id'             => $electionId,
                        'submission_uuid'         => Str::uuid(),
                        'user_id'                 => $station->assigned_officer_id,
                        'total_registered_voters' => $totalRegistered,
                        'total_votes_cast'        => $turnout,
                        'valid_votes'             => $turnout - rand(0, (int) ($turnout * 0.02)),
                        'rejected_votes'          => rand(0, (int) ($turnout * 0.02)),
                        'disputed_votes'          => rand(0, (int) ($turnout * 0.01)),
                        'certification_status'    => Result::STATUS_PENDING_WARD,
                        'submitted_by'            => $station->assigned_officer_id,
                        'submitted_at'            => now()->subMinutes(rand(10, 3000)),
                    ]);

                    $weights   = $candidates->map(fn($c) => rand(10, 100))->toArray();
                    $sum       = array_sum($weights);
                    $remaining = $result->valid_votes;

                    foreach ($candidates as $idx => $candidate) {
                        if ($idx === $candidates->count() - 1) {
                            $votes = $remaining;
                        } else {
                            $votes     = (int) floor($result->valid_votes * ($weights[$idx] / $sum));
                            $remaining -= $votes;
                        }

                        ResultCandidateVote::firstOrCreate(
                            [
                                'result_id'    => $result->id,
                                'candidate_id' => $candidate->id,
                            ],
                            [
                                'election_id' => $electionId,
                                'votes'       => max(0, $votes),
                            ]
                        );
                    }
                });
            }
        });

        $this->command->info('ResultSeeder: results created.');
    }
}