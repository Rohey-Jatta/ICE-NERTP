<?php

namespace Tests\Feature;

use App\Exceptions\NoCurrentElectionException;
use App\Models\Election;
use App\Models\User;
use App\Services\CurrentElectionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Covers business rules #1, #2, #7, #8 from the polling-station
 * election-assignment refactor:
 *
 *   1. Polling stations automatically belong to the most current election.
 *   2. ACTIVE / CERTIFYING / SUBMITTING are "current" statuses.
 *   7. Most recent (by start_date, then id) qualifying election wins.
 *   8. No qualifying election => block with a clear message.
 */
class CurrentElectionResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_returns_null_when_no_election_exists(): void
    {
        $resolver = new CurrentElectionResolver();

        $this->assertNull($resolver->currentOrNull());
    }

    public function test_throws_when_no_election_is_in_a_current_status(): void
    {
        Election::factory()->draft()->create(['start_date' => now()->subDays(5)]);
        Election::factory()->certified()->create(['start_date' => now()->subDays(10)]);

        $resolver = new CurrentElectionResolver();

        $this->expectException(NoCurrentElectionException::class);
        $resolver->current();
    }

    public function test_active_election_is_resolved_as_current(): void
    {
        $election = Election::factory()->create([
            'status' => 'active',
            'start_date' => now()->subDay(),
        ]);

        $resolver = new CurrentElectionResolver();

        $this->assertTrue($resolver->current()->is($election));
    }

    public function test_submitting_election_is_resolved_as_current(): void
    {
        $election = Election::factory()->submitting()->create([
            'start_date' => now()->subDay(),
        ]);

        $resolver = new CurrentElectionResolver();

        $this->assertTrue($resolver->current()->is($election));
    }

    public function test_certifying_election_is_resolved_as_current(): void
    {
        $election = Election::factory()->certifying()->create([
            'start_date' => now()->subDay(),
        ]);

        $resolver = new CurrentElectionResolver();

        $this->assertTrue($resolver->current()->is($election));
    }

    public function test_certified_and_draft_elections_are_never_current(): void
    {
        Election::factory()->certified()->create(['start_date' => now()]);
        Election::factory()->draft()->create(['start_date' => now()]);

        $resolver = new CurrentElectionResolver();

        $this->assertNull($resolver->currentOrNull());
    }

    /**
     * Rule #7: when multiple elections qualify, the most recent by
     * start_date wins — simulating an election transition where a new
     * election has been activated while an older one technically still
     * carries a current-ish status briefly during migration.
     */
    public function test_most_recent_election_by_start_date_wins_among_multiple_current(): void
    {
        $older = Election::factory()->create([
            'status' => 'active',
            'start_date' => now()->subYears(4),
        ]);

        $newer = Election::factory()->submitting()->create([
            'start_date' => now()->subDay(),
        ]);

        $resolver = new CurrentElectionResolver();

        $this->assertTrue($resolver->current()->is($newer));
        $this->assertFalse($resolver->current()->is($older));
    }

    /**
     * Tie-break by id when two elections somehow share a start_date.
     */
    public function test_ties_on_start_date_are_broken_by_id(): void
    {
        $sameDate = now()->subDay();

        $first = Election::factory()->create(['status' => 'active', 'start_date' => $sameDate]);
        $second = Election::factory()->submitting()->create(['start_date' => $sameDate]);

        $resolver = new CurrentElectionResolver();

        // Higher id (created later) should win the tie-break.
        $this->assertTrue($resolver->current()->is($second));
    }

    public function test_is_current_election_helper(): void
    {
        $current = Election::factory()->create(['status' => 'active', 'start_date' => now()]);
        $other   = Election::factory()->certified()->create(['start_date' => now()->subYear()]);

        $resolver = new CurrentElectionResolver();

        $this->assertTrue($resolver->isCurrentElection($current->id));
        $this->assertFalse($resolver->isCurrentElection($other->id));
    }

    /**
     * Resolver cache must self-invalidate when an election's status
     * changes — covers App\Models\Election::booted()'s forgetCache() hook.
     */
    public function test_resolver_cache_invalidates_on_status_transition(): void
    {
        $election = Election::factory()->create([
            'status' => 'active',
            'start_date' => now(),
        ]);

        $resolver = new CurrentElectionResolver();
        $this->assertTrue($resolver->current()->is($election));

        $election->update(['status' => 'certified']);

        // Without cache invalidation this would incorrectly still resolve
        // to $election for up to CACHE_TTL_SECONDS.
        $this->assertNull($resolver->currentOrNull());
    }

    /**
     * Election transition test: an election moving through its full
     * lifecycle (active -> submitting -> certifying -> certified) should
     * be "current" at every step except the final certified state.
     */
    public function test_full_election_lifecycle_transition(): void
    {
        $election = Election::factory()->create(['status' => 'active', 'start_date' => now()]);
        $resolver = new CurrentElectionResolver();

        $this->assertTrue($resolver->current()->is($election));

        $election->update(['status' => 'submitting']);
        $this->assertTrue($resolver->current()->is($election));

        $election->update(['status' => 'certifying']);
        $this->assertTrue($resolver->current()->is($election));

        $election->update(['status' => 'certified']);
        $this->assertNull($resolver->currentOrNull());
    }
}