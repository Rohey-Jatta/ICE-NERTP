<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('political_party_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('constituency_id')->nullable()->constrained('administrative_hierarchy')->nullOnDelete();
            $table->string('name');
            $table->string('ballot_number')->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('is_independent')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_withdrawn')->default(false);
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('election_id');
            $table->index('political_party_id');
            $table->index('constituency_id');
            $table->unique(['election_id', 'ballot_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
