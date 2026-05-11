<?php

namespace Database\Seeders;

use App\Models\Election;
use App\Models\User;
use App\Models\ElectionMonitor;
use App\Models\AdministrativeHierarchy;
use App\Models\PollingStation;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run()
    {
        $electionId = Election::where('slug', 'gambia-2021-presidential')->value('id');
        if (!$electionId) {
            throw new \RuntimeException('Election gambia-2021-presidential must exist before running UserSeeder.');
        }
        // Use the canonical role names (hyphenated) used across the app
        Role::firstOrCreate(['name' => 'iec-chairman']);
        Role::firstOrCreate(['name' => 'iec-administrator']);
        Role::firstOrCreate(['name' => 'constituency-approver']);
        Role::firstOrCreate(['name' => 'ward-approver']);
        Role::firstOrCreate(['name' => 'polling-officer']);
        Role::firstOrCreate(['name' => 'admin-area-approver']);
        Role::firstOrCreate(['name' => 'election-monitor']);
        Role::firstOrCreate(['name' => 'party-representative']);

        // Chairman
        $chair = User::factory()->create(['name' => 'IEC Chairman', 'email' => 'chairman@iec.local']);
        $chair->assignRole('iec-chairman');

        // Election Monitors: create 120 and assign to wards deterministically
        $wards = AdministrativeHierarchy::where('level', 'ward')->get();
        $totalMonitors = 120;
        $perWard = max(1, (int) floor($wards->count() ? $totalMonitors / $wards->count() : 1));
        $created = 0;

        foreach ($wards as $ward) {
            for ($i = 0; $i < $perWard && $created < $totalMonitors; $i++, $created++) {
                $u = User::factory()->create(['name' => $ward->name . ' Monitor', 'email' => 'monitor.' . ($created + 1) . '@iec.local']);
                $u->assignRole('election-monitor');
                $monitor = ElectionMonitor::create([
                    'election_id' => $electionId,
                    'user_id' => $u->id,
                    'type' => 'domestic',
                    'is_active' => true,
                ]);

                // assign monitor to all polling stations in this ward via pivot
                $stations = PollingStation::where('ward_id', $ward->id)->pluck('id')->toArray();
                if (!empty($stations)) {
                    $attach = [];
                    foreach ($stations as $sid) {
                        $attach[$sid] = ['assigned_at' => now()];
                    }
                    $monitor->pollingStations()->attach($attach);
                }
            }
            if ($created >= $totalMonitors) break;
        }

        // Party agents: one per party
        $parties = \App\Models\PoliticalParty::where('election_id', $electionId)->get();
        foreach ($parties as $p) {
            $u = User::factory()->create(['name' => $p->abbreviation . ' Agent', 'email' => 'agent.' . strtolower($p->abbreviation) . '@iec.local']);
            $u->assignRole('party-representative');
        }
    }
}
