<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add comprehensive device fingerprint data column for similarity-based matching.
     * Stores JSON with server and client fingerprint components for flexible comparison.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            // Add column to store comprehensive fingerprint data (JSON)
            // This allows similarity-based matching instead of exact hash matching
            $table->longText('device_fingerprint_data')->nullable()->after('device_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('device_fingerprint_data');
        });
    }
};
