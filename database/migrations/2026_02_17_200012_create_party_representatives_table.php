<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('party_representatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('political_party_id')->constrained()->cascadeOnDelete();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->string('designation')->nullable();
            $table->string('accreditation_number')->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'election_id']);
            $table->index('political_party_id');
            $table->index('election_id');
        });

        Schema::create('party_representative_polling_station', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_representative_id')->constrained('party_representatives')->cascadeOnDelete();
            $table->foreignId('polling_station_id')->constrained()->cascadeOnDelete();
            $table->timestamp('assigned_at');
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->unique(['party_representative_id', 'polling_station_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_representative_polling_station');
        Schema::dropIfExists('party_representatives');
    }
};
