<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            // Drop the global unique constraint on device_fingerprint
            $table->dropUnique('devices_device_fingerprint_unique');
            // Add composite unique constraint on user_id + device_fingerprint
            $table->unique(['user_id', 'device_fingerprint']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            // Revert to single unique constraint
            $table->dropUnique('devices_user_id_device_fingerprint_unique');
            $table->unique('device_fingerprint');
        });
    }
};
