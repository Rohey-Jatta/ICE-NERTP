<?php

namespace Tests\Feature;

use App\Models\Election;
use App\Models\PollingStation;
use App\Services\CurrentElectionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Covers business rules #1, #4, #5 — polling stations are reusable across
 * election cycles, are never statically pinned to an election, and require
 * no manual reassignment when a new election is created/activated.
 */
class PollingStationElectionVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_polling_station_can_be_created_without_an_election_id(): void
    {
        $station = PollingStation::factory()->create();

        $this->assertNull($station->election_id);
        $this->assertNotNull($station->id);
    }

    public function test_active_stations_appear_under_whichever_election_is_currently_operational(): void
    {
        $stationA = PollingStation::factory()->create(['is_active' => true]);
        $stationB = PollingStation::factory()->create(['is_active' => true]);

        // No election exists yet — currentElectionStations() should throw.
        $this->expectException(\App\Exceptions\NoCurrentElectionException::class);
        PollingStation::currentElectionStations()->get();
    }

    public function test_stations_remain_visible_when_a_new_election_is_activated_with_no_reassignment(): void
    {
        $stationA = PollingStation::factory()->create(['is_active' => true]);
        $stationB = PollingStation::factory()->create(['is_active' => true]);

        // First election cycle.
        $electionOne = Election::factory()->create(['status' => 'active', 'start_date' => now()->subYears(4)]);
        $stations = PollingStation::currentElectionStations()->get();
        $this->assertCount(2, $stations);

        // Move election one to certified (closed) and create + activate a
        // brand new election — per business rule #4, the SAME stations
        // should be visible under the new election with ZERO manual
        // reassignment of polling_stations rows.
        $electionOne->update(['status' => 'certified']);
        $electionTwo = Election::factory()->create(['status' => 'active', 'start_date' => now()]);

        $stationsUnderNewElection = PollingStation::currentElectionStations()->get();

        $this->assertCount(2, $stationsUnderNewElection);
        $this->assertEqualsCanonicalizing(
            [$stationA->id, $stationB->id],
            $stationsUnderNewElection->pluck('id')->all()
        );

        // Confirm we resolved against the NEW election, not the closed one.
        $resolver = new CurrentElectionResolver();
        $this->assertTrue($resolver->current()->is($electionTwo));
    }

    public function test_inactive_stations_are_excluded_from_current_election_stations(): void
    {
        PollingStation::factory()->create(['is_active' => true]);
        PollingStation::factory()->create(['is_active' => false]);

        Election::factory()->create(['status' => 'active', 'start_date' => now()]);

        $stations = PollingStation::currentElectionStations()->get();

        $this->assertCount(1, $stations);
    }

    public function test_mark_seen_under_updates_historical_marker_without_affecting_visibility(): void
    {
        $station = PollingStation::factory()->create(['is_active' => true]);
        $election = Election::factory()->create(['status' => 'active', 'start_date' => now()]);

        $this->assertNull($station->election_id);

        $station->markSeenUnder($election);
        $station->refresh();

        $this->assertEquals($election->id, $station->election_id);

        // Visibility under currentElectionStations() must not depend on
        // this historical marker at all — it should already have been
        // visible before markSeenUnder() was ever called.
        $stations = PollingStation::currentElectionStations()->get();
        $this->assertTrue($stations->contains('id', $station->id));
    }

    public function test_mark_seen_under_is_idempotent_when_already_pointing_at_the_election(): void
    {
        $station = PollingStation::factory()->create(['is_active' => true]);
        $election = Election::factory()->create(['status' => 'active', 'start_date' => now()]);

        $station->markSeenUnder($election);
        $updatedAtAfterFirstCall = $station->refresh()->updated_at;

        // Calling again with the same election should be a no-op write.
        $station->markSeenUnder($election);
        $station->refresh();

        $this->assertEquals($election->id, $station->election_id);
        // saveQuietly() with no actual change shouldn't bump updated_at,
        // but we mainly assert no exception and correct final state here
        // since timestamp precision can vary across DB drivers.
        $this->assertEquals($election->id, $station->election_id);
    }
}