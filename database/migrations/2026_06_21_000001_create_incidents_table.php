<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('observation_id')->nullable(); // raw table, no FK
            $table->foreignId('result_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['dispute', 'rejection', 'resubmission']);
            $table->string('administrative_area_name')->nullable();
            $table->unsignedBigInteger('administrative_area_id')->nullable();
            $table->string('polling_station_name')->nullable();
            $table->unsignedBigInteger('polling_station_id')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('election_id');
            $table->index('administrative_area_id');
            $table->index('observation_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};