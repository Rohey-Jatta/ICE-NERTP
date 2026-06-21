<?php

namespace App\Services;

use App\Exceptions\NoCurrentElectionException;
use App\Models\Election;
use Illuminate\Support\Facades\Cache;

/**
 * CurrentElectionResolver — single source of truth for "which election is
 * the operational one right now."
 *
 * Replaces the old pattern of polling stations being statically pinned to
 * an election_id. Per the business rules:
 *
 *   1. Polling stations automatically belong to the most current election.
 *   2. ACTIVE, SUBMITTING, or CERTIFYING elections are "current."
 *   3. All station/result/certification queries resolve against this
 *      service instead of a hardcoded election_id.
 *   7. If multiple qualify, pick the most recent by start_date (tie-broken
 *      by id, since two elections can theoretically share a start_date).
 *   8. If none qualify, block the operation with a clear message.
 *
 * Cached briefly (10s) because this is called on nearly every public and
 * authenticated election-scoped request; the underlying data changes
 * rarely enough that a short TTL is safe and avoids a query storm.
 */
class CurrentElectionResolver
{
    public const CURRENT_STATUSES = ['active', 'submitting', 'certifying'];

    private const CACHE_KEY = 'current_election_resolver_v1';
    private const CACHE_TTL_SECONDS = 10;

    /**
     * Resolve the current operational election.
     *
     * @throws NoCurrentElectionException if no election qualifies.
     */
    public function current(): Election
    {
        $election = $this->currentOrNull();

        if (!$election) {
            throw new NoCurrentElectionException();
        }

        return $election;
    }

    /**
     * Same as current(), but returns null instead of throwing.
     * Use this in places (dashboards, public pages) that should degrade
     * gracefully rather than error out.
     */
    public function currentOrNull(): ?Election
    {
        $id = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            return Election::whereIn('status', self::CURRENT_STATUSES)
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->value('id');
        });

        if (!$id) {
            return null;
        }

        // Re-fetch the full model rather than caching it directly — election
        // attributes (e.g. status) can change between cache hits, and we
        // always want the live row, just with the lookup itself cached.
        return Election::find($id);
    }

    /**
     * Resolve the current election's id only, or null.
     * Convenience for raw-SQL call sites that just need the id.
     */
    public function currentIdOrNull(): ?int
    {
        return $this->currentOrNull()?->id;
    }

    /**
     * Whether a given election id is the current operational election.
     */
    public function isCurrentElection(int $electionId): bool
    {
        return $this->currentIdOrNull() === $electionId;
    }

    /**
     * Call this whenever an election's status changes (activation,
     * progressing to submitting/certifying/certified, etc.) so the next
     * resolve() reflects reality immediately instead of waiting out the
     * cache TTL.
     */
    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}