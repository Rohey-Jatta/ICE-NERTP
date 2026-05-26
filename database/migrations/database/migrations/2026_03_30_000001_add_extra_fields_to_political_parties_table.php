<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('political_parties', function (Blueprint $table) {
            $table->string('leader_name')->nullable()->after('color');
            $table->string('leader_photo_path')->nullable()->after('leader_name');
            $table->string('symbol_path')->nullable()->after('leader_photo_path');
            $table->text('motto')->nullable()->after('symbol_path');
            $table->string('headquarters')->nullable()->after('motto');
            $table->string('website')->nullable()->after('headquarters');
        });
    }

    public function down(): void
    {
        Schema::table('political_parties', function (Blueprint $table) {
            $table->dropColumn(['leader_name', 'leader_photo_path', 'symbol_path', 'motto', 'headquarters', 'website']);
        });
    }
};