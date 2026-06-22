<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Part of the "dynamic current election" refactor.
 *
 * 1. Adds the 'submitting' status to elections.status. This sits in the
 *    lifecycle between 'active' (polling open) and 'certifying'
 *    (approval chain running) — it represents the window where polling
 *    officers are actively submitting results for tabulation.
 *
 *    Postgres enums created via Laravel's enum() are actually CHECK
 *    constraints, not native PG enums, so we drop and recreate the
 *    constraint rather than ALTER TYPE.
 *
 * 2. Makes polling_stations.election_id NULLABLE and drops its NOT NULL
 *    constraint. Per the new architecture, polling stations are no
 *    longer statically owned by an election — they are resolved
 *    dynamically against whichever election is currently
 *    active/submitting/certifying (see App\Services\CurrentElectionResolver).
 *
 *    The column is NOT dropped. It is repurposed as "last seen election"
 *    — informational only, updated opportunistically whenever a station
 *    is touched under a current election, and used for historical
 *    reporting. No live query should filter on it anymore.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Find and drop the existing CHECK constraint Laravel generated
            // for the elections.status enum, then recreate it with the new value.
            $constraint = DB::selectOne("
                SELECT conname
                FROM pg_constraint
                WHERE conrelid = 'elections'::regclass
                  AND contype = 'c'
                  AND pg_get_constraintdef(oid) LIKE '%status%'
                  AND pg_get_constraintdef(oid) LIKE '%draft%'
            ");

            if ($constraint) {
                DB::statement('ALTER TABLE elections DROP CONSTRAINT ' . $constraint->conname);
            }

            DB::statement("
                ALTER TABLE elections
                ADD CONSTRAINT elections_status_check
                CHECK (status IN (
                    'draft', 'configured', 'active', 'submitting',
                    'results_pending', 'certifying', 'certified', 'archived'
                ))
            ");
        } else {
            // Fallback for non-pgsql drivers (e.g. sqlite has no enum constraint
            // to alter — Laravel just uses a plain string/check there too,
            // but sqlite doesn't enforce CHECK on existing tables by default
            // in the way pgsql does, so this is a no-op safeguard).
            Schema::table('elections', function (Blueprint $table) {
                // no-op: sqlite stores enum as TEXT, no constraint to alter
            });
        }

        Schema::table('polling_stations', function (Blueprint $table) {
            $table->foreignId('election_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Re-tighten polling_stations.election_id back to NOT NULL.
        // Safe only if no NULLs exist; if rolling back after stations were
        // created without an election_id, backfill before running down().
        Schema::table('polling_stations', function (Blueprint $table) {
            $table->foreignId('election_id')->nullable(false)->change();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE elections DROP CONSTRAINT IF EXISTS elections_status_check');
            DB::statement("
                ALTER TABLE elections
                ADD CONSTRAINT elections_status_check
                CHECK (status IN (
                    'draft', 'configured', 'active',
                    'results_pending', 'certifying', 'certified', 'archived'
                ))
            ");
        }
    }
};