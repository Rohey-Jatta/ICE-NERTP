<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-computed vote totals by hierarchy level.
 * Enables <2 second public dashboard with 100k concurrent users.
 * Updated by AggregateResults background job via Laravel Horizon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aggregated_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->restrictOnDelete();
            $table->foreignId('political_party_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('hierarchy_node_id')->constrained('administrative_hierarchy')->restrictOnDelete();
            $table->enum('level', ['ward', 'constituency', 'admin_area', 'national']);
            $table->unsignedBigInteger('total_votes')->default(0);
            $table->unsignedBigInteger('total_registered_voters')->default(0);
            $table->unsignedBigInteger('total_votes_cast')->default(0);
            $table->unsignedBigInteger('valid_votes')->default(0);
            $table->unsignedBigInteger('rejected_votes')->default(0);
            $table->unsignedInteger('total_polling_stations')->default(0);
            $table->unsignedInteger('stations_reported')->default(0);
            $table->unsignedInteger('stations_certified')->default(0);
            $table->enum('based_on_certification_level', [
                'submitted', 'ward_certified', 'constituency_certified',
                'admin_area_certified', 'nationally_certified',
            ])->default('submitted');
            $table->timestamp('last_computed_at');
            $table->timestamps();
            $table->unique(
                ['election_id', 'candidate_id', 'hierarchy_node_id', 'based_on_certification_level'],
                'agg_results_unique'
            );
            $table->index('election_id');
            $table->index(['election_id', 'level']);
            $table->index(['hierarchy_node_id', 'based_on_certification_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aggregated_results');
    }
};
