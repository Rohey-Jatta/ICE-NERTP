<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('election_political_party', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('political_party_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['election_id', 'political_party_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('election_political_party');
    }
};