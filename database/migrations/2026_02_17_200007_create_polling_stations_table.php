<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PostGIS GEOMETRY(Point, 4326) column for GPS validation.
 * ST_Distance() used to verify officer is within allowed radius of station.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polling_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ward_id')->constrained('administrative_hierarchy')->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->unsignedInteger('registered_voters')->default(0);
            $table->foreignId('assigned_officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_test_station')->default(false);
            $table->string('station_photo_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('election_id');
            $table->index('ward_id');
            $table->index('assigned_officer_id');
            $table->index('is_active');
        });

        // PostGIS geometry column + spatial index
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE polling_stations ADD COLUMN location GEOMETRY(Point, 4326)');
            DB::statement('UPDATE polling_stations SET location = ST_SetSRID(ST_MakePoint(longitude, latitude), 4326)');
            DB::statement('CREATE INDEX polling_stations_location_idx ON polling_stations USING GIST (location)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('polling_stations');
    }
};
