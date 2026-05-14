<?php

namespace Database\Seeders;

use App\Models\Election;
use App\Models\User;
use App\Models\ElectionMonitor;
use App\Models\AdministrativeHierarchy;
use App\Models\PollingStation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run()
    {
        $electionId = Election::where('slug', 'gambia-2021-presidential')->value('id');
        if (!$electionId) {
            throw new \RuntimeException('Election gambia-2021-presidential must exist before running UserSeeder.');
        }

        // Ensure roles exist
        Role::firstOrCreate(['name' => 'iec-chairman',          'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'iec-administrator',     'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'constituency-approver', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'ward-approver',         'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'polling-officer',       'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin-area-approver',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'election-monitor',      'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'party-representative',  'guard_name' => 'web']);

        // ── Chairman ──────────────────────────────────────────────────────────
        $chair = User::firstOrCreate(
            ['email' => 'chairman@iec.local'],
            [
                'name'     => 'IEC Chairman',
                'password' => Hash::make('password123'),
                'status'   => 'active',
            ]
        );
        if (!$chair->hasRole('iec-chairman')) {
            $chair->assignRole('iec-chairman');
        }

        // ── Election Monitors: 1 per ward ─────────────────────────────────────
        $wards        = AdministrativeHierarchy::where('level', 'ward')->get();
        $totalMonitors = 120;
        $perWard      = max(1, (int) floor($wards->count() ? $totalMonitors / $wards->count() : 1));
        $created      = 0;

        foreach ($wards as $ward) {
            for ($i = 0; $i < $perWard && $created < $totalMonitors; $i++, $created++) {
                $email = 'monitor.' . ($created + 1) . '@iec.local';

                $u = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name'     => $ward->name . ' Monitor',
                        'password' => Hash::make('password123'),
                        'status'   => 'active',
                    ]
                );

                if (!$u->hasRole('election-monitor')) {
                    $u->assignRole('election-monitor');
                }

                // Create ElectionMonitor record if it doesn't exist
                $monitor = ElectionMonitor::firstOrCreate(
                    [
                        'user_id'     => $u->id,
                        'election_id' => $electionId,
                    ],
                    [
                        'type'      => 'domestic',
                        'is_active' => true,
                    ]
                );

                // Attach monitor to all polling stations in this ward (safe to re-run)
                $stations = PollingStation::where('ward_id', $ward->id)->pluck('id')->toArray();
                if (!empty($stations)) {
                    $attach = [];
                    foreach ($stations as $sid) {
                        $attach[$sid] = ['assigned_at' => now()];
                    }
                    $monitor->pollingStations()->syncWithoutDetaching($attach);
                }
            }
            if ($created >= $totalMonitors) break;
        }

        // ── Party agents: 1 per party ─────────────────────────────────────────
        $parties = \App\Models\PoliticalParty::where('election_id', $electionId)->get();
        foreach ($parties as $p) {
            $email = 'agent.' . strtolower($p->abbreviation) . '@iec.local';

            $u = User::firstOrCreate(
                ['email' => $email],
                [
                    'name'     => $p->abbreviation . ' Agent',
                    'password' => Hash::make('password123'),
                    'status'   => 'active',
                ]
            );

            if (!$u->hasRole('party-representative')) {
                $u->assignRole('party-representative');
            }
        }

        $this->command->info('UserSeeder: users created/verified.');
    }
}
