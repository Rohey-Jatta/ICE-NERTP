<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends audit_logs with the forensic / chain-of-custody fields required
 * by the Enterprise Election Audit Trail spec. Append-only table — no
 * columns here are ever updated after insert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // Workflow correlation — ties every step of one result's journey together
            $table->uuid('correlation_id')->nullable()->after('id');

            // Geographic / election scope
            $table->unsignedBigInteger('polling_station_id')->nullable()->after('election_id');
            $table->unsignedBigInteger('ward_id')->nullable()->after('polling_station_id');
            $table->unsignedBigInteger('constituency_id')->nullable()->after('ward_id');
            $table->unsignedBigInteger('admin_area_id')->nullable()->after('constituency_id');
            $table->unsignedBigInteger('result_id')->nullable()->after('admin_area_id');

            // Chain of custody
            $table->string('previous_custodian_role')->nullable()->after('permission_used');
            $table->string('current_custodian_role')->nullable()->after('previous_custodian_role');
            $table->string('next_custodian_role')->nullable()->after('current_custodian_role');
            $table->string('workflow_stage')->nullable()->after('next_custodian_role');
            $table->string('decision')->nullable()->after('workflow_stage'); // approved | approved_with_reservation | rejected | accepted | certified | published | closed ...
            $table->string('previous_status')->nullable()->after('decision');
            $table->string('new_status')->nullable()->after('previous_status');

            // Exact, untruncated comment text
            $table->text('comment_text')->nullable()->after('new_status');

            // Human-readable, fully explicit description (never "User updated result")
            $table->text('description')->nullable()->after('comment_text');

            // Request / device metadata
            $table->string('session_id')->nullable()->after('device_fingerprint');
            $table->string('request_id')->nullable()->after('session_id');
            $table->string('url', 2048)->nullable()->after('request_id');
            $table->string('http_method', 10)->nullable()->after('url');
            $table->string('os')->nullable()->after('http_method');
            $table->string('browser')->nullable()->after('os');
            $table->string('device_type', 50)->nullable()->after('browser');
            $table->string('mac_address')->nullable()->after('device_type');
            $table->string('timezone', 64)->nullable()->after('mac_address');

            $table->index('correlation_id');
            $table->index('polling_station_id');
            $table->index('ward_id');
            $table->index('constituency_id');
            $table->index('admin_area_id');
            $table->index('result_id');
            $table->index('workflow_stage');
            $table->index('decision');
            $table->index(['election_id', 'polling_station_id']);
            $table->index(['election_id', 'workflow_stage']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn([
                'correlation_id', 'polling_station_id', 'ward_id', 'constituency_id',
                'admin_area_id', 'result_id', 'previous_custodian_role',
                'current_custodian_role', 'next_custodian_role', 'workflow_stage',
                'decision', 'previous_status', 'new_status', 'comment_text',
                'description', 'session_id', 'request_id', 'url', 'http_method',
                'os', 'browser', 'device_type', 'mac_address', 'timezone',
            ]);
        });
    }
};