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
        Schema::table('monitor_observations', function (Blueprint $table) {
            // Add documents field if not already present
            if (!Schema::hasColumn('monitor_observations', 'documents_paths')) {
                $table->json('documents_paths')->nullable()->after('photo_paths');
                $table->index('documents_paths');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monitor_observations', function (Blueprint $table) {
            if (Schema::hasColumn('monitor_observations', 'documents_paths')) {
                $table->dropColumn('documents_paths');
            }
        });
    }
};
