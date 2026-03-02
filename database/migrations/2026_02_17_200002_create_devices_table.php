<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Device binding - required for officers, approvers, chairman, admin.
 * Security rule: device must be verified before login is allowed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_fingerprint')->unique();
            $table->string('device_name');
            $table->string('device_type', 50);
            $table->string('os', 100)->nullable();
            $table->string('browser', 100)->nullable();
            $table->string('token_id')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('verified_by_ip', 45)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->boolean('is_trusted')->default(false);
            $table->boolean('is_revoked')->default(false);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index('user_id');
            $table->index('is_trusted');
            $table->index('is_revoked');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
