<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('party_representative_polling_station', function (Blueprint $table) {
            $table->index('polling_station_id', 'prps_polling_station_idx');
        });

        Schema::table('results', function (Blueprint $table) {
            $table->index(['submitted_by', 'submitted_at'], 'results_officer_recent_idx');
        });
    }

    public function down(): void
    {
        Schema::table('party_representative_polling_station', function (Blueprint $table) {
            $table->dropIndex('prps_polling_station_idx');
        });

        Schema::table('results', function (Blueprint $table) {
            $table->dropIndex('results_officer_recent_idx');
        });
    }
};
