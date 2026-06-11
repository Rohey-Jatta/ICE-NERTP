<?php

namespace App\Support;

/**
 * Shared subqueries for the public results pages (summary, map, stations).
 *
 * Written with window functions instead of Postgres-only DISTINCT ON so the
 * same SQL runs on both the SQLite dev database and Postgres in production.
 */
class PublicResultsQuery
{
    /**
     * Latest result row per polling station for an election — any
     * certification status, preferring nationally certified rows. Used to
     * display each station's pipeline status.
     */
    public static function latestResultsSql(int $electionId): string
    {
        return <<<SQL
SELECT * FROM (
    SELECT results.*, ROW_NUMBER() OVER (
        PARTITION BY polling_station_id
        ORDER BY CASE WHEN certification_status = 'nationally_certified' THEN 0 ELSE 1 END,
                 nationally_certified_at DESC NULLS LAST,
                 submitted_at DESC,
                 id DESC
    ) AS rn
    FROM results
    WHERE election_id = {$electionId}
) ranked
WHERE rn = 1
SQL;
    }

    /**
     * Latest PUBLISHED (nationally certified) result per polling station.
     * Public vote totals must only ever be aggregated from these rows.
     */
    public static function latestPublishedResultsSql(int $electionId): string
    {
        return <<<SQL
SELECT * FROM (
    SELECT results.*, ROW_NUMBER() OVER (
        PARTITION BY polling_station_id
        ORDER BY nationally_certified_at DESC NULLS LAST, id DESC
    ) AS rn
    FROM results
    WHERE election_id = {$electionId}
      AND certification_status = 'nationally_certified'
) ranked
WHERE rn = 1
SQL;
    }
}
