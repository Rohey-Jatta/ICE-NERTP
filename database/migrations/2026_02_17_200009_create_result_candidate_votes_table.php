<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('result_candidate_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->restrictOnDelete();
            $table->foreignId('election_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('votes')->default(0);
            $table->timestamps();
            $table->unique(['result_id', 'candidate_id']);
            $table->index('election_id');
            $table->index('candidate_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_candidate_votes');
    }
};
