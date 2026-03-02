<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('election_monitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->string('organization')->nullable();
            $table->string('accreditation_number')->nullable()->unique();
            $table->enum('type', ['domestic', 'international', 'civil_society']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'election_id']);
            $table->index('election_id');
        });

        Schema::create('election_monitor_polling_station', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_monitor_id')->constrained('election_monitors')->cascadeOnDelete();
            $table->foreignId('polling_station_id')->constrained()->cascadeOnDelete();
            $table->timestamp('assigned_at');
            $table->unique(['election_monitor_id', 'polling_station_id']);
        });

        Schema::create('monitor_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_monitor_id')->constrained('election_monitors')->restrictOnDelete();
            $table->foreignId('polling_station_id')->constrained()->restrictOnDelete();
            $table->foreignId('election_id')->constrained()->restrictOnDelete();
            $table->enum('observation_type', ['general', 'irregularity', 'process_concern', 'positive', 'incident']);
            $table->string('title');
            $table->text('observation');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('low');
            $table->json('photo_paths')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamp('observed_at');
            $table->timestamps();
            $table->index('election_id');
            $table->index('polling_station_id');
            $table->index('observation_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitor_observations');
        Schema::dropIfExists('election_monitor_polling_station');
        Schema::dropIfExists('election_monitors');
    }
};
