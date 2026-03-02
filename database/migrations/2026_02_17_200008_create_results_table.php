<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Core result entity with full 10-state certification state machine.
 * submission_uuid ensures offline idempotency (no duplicate submissions).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polling_station_id')->constrained()->restrictOnDelete();
            $table->foreignId('election_id')->constrained()->restrictOnDelete();
            $table->uuid('submission_uuid')->unique();
            $table->unsignedInteger('total_registered_voters');
            $table->unsignedInteger('total_votes_cast');
            $table->unsignedInteger('valid_votes');
            $table->unsignedInteger('rejected_votes')->default(0);
            $table->unsignedInteger('disputed_votes')->default(0);
            $table->string('result_sheet_photo_path')->nullable();
            $table->string('result_sheet_photo_hash')->nullable();
            $table->decimal('submitted_latitude', 10, 8)->nullable();
            $table->decimal('submitted_longitude', 11, 8)->nullable();
            $table->decimal('gps_accuracy_meters', 8, 2)->nullable();
            $table->boolean('gps_validated')->default(false);
            $table->enum('certification_status', [
                'submitted', 'pending_party_acceptance',
                'pending_ward', 'ward_certified',
                'pending_constituency', 'constituency_certified',
                'pending_admin_area', 'admin_area_certified',
                'pending_national', 'nationally_certified', 'rejected',
            ])->default('submitted');
            $table->unsignedTinyInteger('rejection_count')->default(0);
            $table->text('last_rejection_reason')->nullable();
            $table->foreignId('last_rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_rejected_at')->nullable();
            $table->boolean('submitted_offline')->default(false);
            $table->timestamp('offline_queued_at')->nullable();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('nationally_certified_at')->nullable();
            $table->timestamps();
            $table->index('election_id');
            $table->index('polling_station_id');
            $table->index('certification_status');
            $table->index('submitted_by');
            $table->index(['election_id', 'certification_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('results');
    }
};
