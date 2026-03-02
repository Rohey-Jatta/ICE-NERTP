<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One record per decision per level per result.
 * Records are NEVER updated - rejection + resubmission creates new record.
 * Tracks SLA compliance (target: <24 hours per level).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('result_certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_id')->constrained()->restrictOnDelete();
            $table->enum('certification_level', ['ward', 'constituency', 'admin_area', 'national']);
            $table->foreignId('hierarchy_node_id')->constrained('administrative_hierarchy')->restrictOnDelete();
            $table->foreignId('approver_id')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected']);
            $table->text('comments')->nullable();
            $table->timestamp('assigned_at');
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('next_certification_id')->nullable()->constrained('result_certifications')->nullOnDelete();
            $table->timestamp('created_at');
            $table->index('result_id');
            $table->index('certification_level');
            $table->index('approver_id');
            $table->index('status');
            $table->index(['certification_level', 'status']);
            $table->index('hierarchy_node_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_certifications');
    }
};
