<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->index('election_id');
            $table->index('certification_status');
            $table->index(['election_id', 'certification_status']);
            $table->index('polling_station_id');
        });

        Schema::table('result_candidate_votes', function (Blueprint $table) {
            $table->index('result_id');
            $table->index('candidate_id');
        });

        Schema::table('polling_stations', function (Blueprint $table) {
            $table->index('election_id');
        });

        Schema::table('party_acceptances', function (Blueprint $table) {
            $table->index('result_id');
        });
    }

    public function down(): void
    {
        //
    }
};
