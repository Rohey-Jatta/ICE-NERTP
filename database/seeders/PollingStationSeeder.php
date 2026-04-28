<?php

namespace Database\Seeders;

use App\Models\PollingStation;
use App\Models\AdministrativeHierarchy;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class PollingStationSeeder extends Seeder
{
    public function run()
    {
        Role::firstOrCreate(['name' => 'polling-officer']);
        // create a polling-station approver role (hyphenated)
        Role::firstOrCreate(['name' => 'polling-station-approver']);

        $wards = AdministrativeHierarchy::where('level', 'ward')->get();
        $totalStations = 1555;
        $perWard = (int) ceil($totalStations / max(1, $wards->count()));
        $created = 0;

        foreach ($wards as $ward) {
            for ($i = 1; $i <= $perWard && $created < $totalStations; $i++, $created++) {
                $station = PollingStation::create([
                    'election_id' => 1,
                    'ward_id' => $ward->id,
                    'code' => 'PS-' . ($created + 1),
                    'name' => $ward->name . ' Polling Station ' . ($i),
                    'address' => 'Address for station ' . ($created + 1),
                    'registered_voters' => rand(200, 1200),
                    'latitude' => 13.45 + (rand(-500, 500) / 10000),
                    'longitude' => -16.66 + (rand(-500, 500) / 10000),
                    'is_active' => true,
                ]);

                // create polling officer
                $officer = User::factory()->create([
                    'name' => $station->name . ' Officer',
                    'email' => 'officer.' . ($created + 1) . '@iec.local'
                ]);
                $officer->assignRole('polling-officer');
                $station->assigned_officer_id = $officer->id;
                $station->saveQuietly();

                // create polling station approver
                $approver = User::factory()->create([
                    'name' => $station->name . ' Approver',
                    'email' => 'ps.approver.' . ($created + 1) . '@iec.local'
                ]);
                $approver->assignRole('polling-station-approver');
            }
        }
    }
}
