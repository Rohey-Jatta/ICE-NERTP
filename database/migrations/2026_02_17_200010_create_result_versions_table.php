<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only snapshots of every result modification.
 * Architecture requirement: "All modifications create new version in audit log."
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('result_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->json('result_snapshot');
            $table->json('votes_snapshot');
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->enum('change_reason', [
                'initial_submission', 'officer_edit',
                'resubmission_after_rejection', 'photo_update',
            ]);
            $table->text('change_notes')->nullable();
            $table->string('certification_status_at_version');
            $table->timestamp('created_at');
            $table->index('result_id');
            $table->unique(['result_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_versions');
    }
};
