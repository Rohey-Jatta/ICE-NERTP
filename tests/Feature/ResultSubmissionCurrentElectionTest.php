<?php

namespace Tests\Feature;

use App\Models\Election;
use App\Models\PollingStation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Covers business rules #6 and #8 at the result-submission layer:
 * submissions must dynamically reference the current election context,
 * and submission must be blocked entirely when no election is current.
 */
class ResultSubmissionCurrentElectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Storage::fake('local');
        Role::findOrCreate('polling-officer', 'web');
    }

    private function makeOfficerWithStation(): array
    {
        $officer = User::factory()->create();
        $officer->assignRole('polling-officer');

        $station = PollingStation::factory()->create([
            'is_active' => true,
            'assigned_officer_id' => $officer->id,
            'registered_voters' => 500,
        ]);

        return [$officer, $station];
    }

    public function test_submission_is_blocked_with_409_when_no_current_election(): void
    {
        [$officer, $station] = $this->makeOfficerWithStation();

        $response = $this->actingAs($officer)->postJson('/api/results/submit', [
            'submission_uuid'        => (string) \Illuminate\Support\Str::uuid(),
            'polling_station_id'     => $station->id,
            'total_registered_voters'=> 500,
            'total_votes_cast'       => 0,
            'valid_votes'            => 0,
            'rejected_votes'         => 0,
            'candidate_votes'        => [],
            'submitted_latitude'     => 13.45,
            'submitted_longitude'    => -15.3,
        ]);

        $response->assertStatus(409);
        $response->assertJson(['code' => 'NO_CURRENT_ELECTION']);
    }

    public function test_submission_rejects_a_stale_client_supplied_election_id(): void
    {
        [$officer, $station] = $this->makeOfficerWithStation();

        $staleElection   = Election::factory()->certified()->create(['start_date' => now()->subYears(4)]);
        $currentElection = Election::factory()->create(['status' => 'submitting', 'start_date' => now()]);

        $candidate = \App\Models\Candidate::factory()->create(['election_id' => $currentElection->id]);

        $photo = UploadedFile::fake()->image('result.jpg');

        $response = $this->actingAs($officer)->postJson('/api/results/submit', [
            'submission_uuid'        => (string) \Illuminate\Support\Str::uuid(),
            'polling_station_id'     => $station->id,
            // Client deliberately sends the STALE election id.
            'election_id'            => $staleElection->id,
            'total_registered_voters'=> 500,
            'total_votes_cast'       => 100,
            'valid_votes'            => 100,
            'rejected_votes'         => 0,
            'candidate_votes'        => [['candidate_id' => $candidate->id, 'votes' => 100]],
            'result_sheet_photo'     => $photo,
            'submitted_latitude'     => 13.45,
            'submitted_longitude'    => -15.3,
        ]);

        $response->assertStatus(409);
        $response->assertJson(['code' => 'STALE_ELECTION_CONTEXT']);
    }

    public function test_submission_succeeds_against_the_resolved_current_election_and_marks_station_seen(): void
    {
        [$officer, $station] = $this->makeOfficerWithStation();

        $currentElection = Election::factory()->create(['status' => 'submitting', 'start_date' => now()]);
        $candidate = \App\Models\Candidate::factory()->create(['election_id' => $currentElection->id]);

        $photo = UploadedFile::fake()->image('result.jpg');

        $response = $this->actingAs($officer)->postJson('/api/results/submit', [
            'submission_uuid'        => (string) \Illuminate\Support\Str::uuid(),
            'polling_station_id'     => $station->id,
            'total_registered_voters'=> 500,
            'total_votes_cast'       => 100,
            'valid_votes'            => 100,
            'rejected_votes'         => 0,
            'candidate_votes'        => [['candidate_id' => $candidate->id, 'votes' => 100]],
            'result_sheet_photo'     => $photo,
            'submitted_latitude'     => 13.45,
            'submitted_longitude'    => -15.3,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('results', [
            'polling_station_id' => $station->id,
            'election_id'        => $currentElection->id,
        ]);

        // Submission should have updated the station's historical marker.
        $station->refresh();
        $this->assertEquals($currentElection->id, $station->election_id);
    }
}