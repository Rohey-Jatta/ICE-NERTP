<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Performance indexes for the forensic audit_logs columns.
 *
 * Uses raw `CREATE INDEX IF NOT EXISTS` / `DROP INDEX IF EXISTS` throughout
 * so the migration is fully idempotent and works on both PostgreSQL and MySQL
 * without any driver-specific branching in PHP.
 *
 * PostgreSQL:  IF NOT EXISTS on CREATE INDEX is supported since PG 9.5
 * MySQL:       IF NOT EXISTS on CREATE INDEX is supported since MySQL 8.0.1
 * MariaDB:     IF NOT EXISTS on CREATE INDEX is supported since 10.1.4
 *
 * FULLTEXT is MySQL/MariaDB only — skipped on PostgreSQL; a separate GIN
 * migration can be added if full-text search on description is required on PG.
 */
return new class extends Migration
{
    /**
     * Each entry: [index_name, column_list_sql]
     * column_list_sql is dropped verbatim into:
     *   CREATE INDEX IF NOT EXISTS {name} ON audit_logs ({columns})
     */
    private array $indexes = [
        // Chain of custody — most frequent: WHERE result_id = ? ORDER BY created_at
        ['audit_logs_result_id_created_at_index',       '"result_id", "created_at"'],

        // Main table default view
        ['audit_logs_module_created_at_index',           '"module", "created_at"'],

        // Stage filter
        ['audit_logs_workflow_stage_created_at_index',   '"workflow_stage", "created_at"'],

        // Approval / rejection filter
        ['audit_logs_decision_created_at_index',         '"decision", "created_at"'],

        // Success / failure / blocked filter
        ['audit_logs_outcome_created_at_index',          '"outcome", "created_at"'],

        // Election scope
        ['audit_logs_election_id_created_at_index',      '"election_id", "created_at"'],

        // Station scope
        ['audit_logs_polling_station_id_created_at_index', '"polling_station_id", "created_at"'],

        // User filter
        ['audit_logs_user_id_created_at_index',          '"user_id", "created_at"'],

        // Correlation ID lookup
        ['audit_logs_correlation_id_index',              '"correlation_id"'],

        // Dashboard aggregation: WHERE election_id = ? AND module = ? AND workflow_stage = ?
        ['audit_logs_election_module_stage_index',       '"election_id", "module", "workflow_stage"'],

        // Approval / rejection counts per election
        ['audit_logs_election_decision_index',           '"election_id", "decision", "created_at"'],

        // Export query: date range + module ordered by time
        ['audit_logs_created_at_module_index',           '"created_at", "module"'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as [$name, $columns]) {
            DB::statement(
                "CREATE INDEX IF NOT EXISTS {$name} ON audit_logs ({$columns})"
            );
        }

        // FULLTEXT on description — MySQL / MariaDB only
        // PostgreSQL users: add a GIN index with tsvector in a separate migration.
        if ($this->isMysql()) {
            DB::statement(
                'CREATE FULLTEXT INDEX IF NOT EXISTS audit_logs_description_fulltext
                 ON audit_logs (description)'
            );
        }
    }

    public function down(): void
    {
        if ($this->isMysql()) {
            // MySQL requires DROP INDEX ... ON table
            DB::statement(
                'ALTER TABLE audit_logs
                 DROP INDEX IF EXISTS audit_logs_description_fulltext'
            );
        }

        foreach (array_reverse($this->indexes) as [$name]) {
            if ($this->isMysql()) {
                // MySQL: DROP INDEX name ON table
                DB::statement("DROP INDEX IF EXISTS {$name} ON audit_logs");
            } else {
                // PostgreSQL: indexes are schema-scoped, not table-scoped
                DB::statement("DROP INDEX IF EXISTS {$name}");
            }
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function isMysql(): bool
    {
        return in_array(
            DB::connection()->getDriverName(),
            ['mysql', 'mariadb'],
            true
        );
    }
};