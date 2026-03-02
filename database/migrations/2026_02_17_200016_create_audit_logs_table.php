<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IMMUTABLE audit trail - no updated_at, no FK cascade deletes.
 * Every sensitive action records: actor, role, permission, before/after state, IP, device.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('election_id')->nullable()->constrained('elections')->nullOnDelete();
            $table->string('action');
            $table->string('event');
            $table->string('module', 100);
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('user_role')->nullable();
            $table->string('permission_used')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('device_fingerprint')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->enum('outcome', ['success', 'failure', 'blocked'])->default('success');
            $table->text('failure_reason')->nullable();
            $table->timestamp('created_at');           // No updated_at - IMMUTABLE
            $table->index('user_id');
            $table->index('election_id');
            $table->index('action');
            $table->index('event');
            $table->index(['auditable_type', 'auditable_id']);
            $table->index('created_at');
            $table->index(['election_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
