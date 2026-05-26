<?php

namespace Database\Seeders;

use App\Models\AdministrativeHierarchy;
use App\Models\Election;
use App\Models\ElectionMonitor;
use App\Models\PollingStation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Creates named test users for each role and ensures polling stations
 * have an assigned officer for result submission.
 *
 * NOTE: Constituency and ward approvers are already created and assigned
 * by PollingStationSeeder via assignApprover(). This seeder creates the
 * named test accounts and assigns a default officer to all stations.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $electionId = Election::where('slug', 'gambia-2021-presidential')->value('id');
        if (!$electionId) {
            throw new \RuntimeException('Election gambia-2021-presidential must exist before running UserSeeder.');
        }

        // ── Ensure all roles exist ─────────────────────────────────────────
        $roles = [
            'iec-chairman', 'iec-administrator', 'constituency-approver',
            'ward-approver', 'polling-officer', 'admin-area-approver',
            'election-monitor', 'party-representative',
        ];
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        // ── Named test accounts ────────────────────────────────────────────
        $testUsers = [
            ['email' => 'chairman@iec.gm',      'name' => 'IEC Chairman',          'role' => 'iec-chairman'],
            ['email' => 'ward@iec.gm',           'name' => 'Test Ward Approver',    'role' => 'ward-approver'],
            ['email' => 'constituency@iec.gm',   'name' => 'Test Constituency Approver', 'role' => 'constituency-approver'],
            ['email' => 'adminarea@iec.gm',      'name' => 'Test Admin Area Approver',   'role' => 'admin-area-approver'],
            ['email' => 'officer@iec.gm',        'name' => 'Test Polling Officer',  'role' => 'polling-officer'],
            ['email' => 'party@iec.gm',          'name' => 'Test Party Agent',      'role' => 'party-representative'],
            ['email' => 'monitor@iec.gm',        'name' => 'Test Election Monitor', 'role' => 'election-monitor'],
        ];

        foreach ($testUsers as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'     => $data['name'],
                    'password' => Hash::make('password123'),
                    'status'   => 'active',
                ]
            );
            if (!$user->hasRole($data['role'])) {
                $user->assignRole($data['role']);
            }
        }

        // ── Assign test officer to all stations that don't have one ────────
        $officer = User::where('email', 'officer@iec.gm')->firstOrFail();
        $unassigned = PollingStation::where('election_id', $electionId)
            ->whereNull('assigned_officer_id')
            ->count();

        if ($unassigned > 0) {
            PollingStation::where('election_id', $electionId)
                ->whereNull('assigned_officer_id')
                ->update(['assigned_officer_id' => $officer->id]);

            $this->command->info("✓ Assigned test officer to {$unassigned} unassigned stations.");
        }

        // ── Create election monitors for wards that don't have one ─────────
        $wards = AdministrativeHierarchy::where('election_id', $electionId)
            ->where('level', 'ward')
            ->whereNull('assigned_approver_id')
            ->get();

        foreach ($wards as $idx => $ward) {
            $email = 'monitor.' . ($idx + 1) . '@iec.gm';
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name'     => $ward->name . ' Monitor',
                    'password' => Hash::make('password123'),
                    'status'   => 'active',
                ]
            );
            if (!$user->hasRole('election-monitor')) {
                $user->assignRole('election-monitor');
            }

            // ElectionMonitor record
            $monitor = ElectionMonitor::firstOrCreate(
                ['user_id' => $user->id, 'election_id' => $electionId],
                ['type' => 'domestic', 'is_active' => true]
            );

            // Attach to stations in this ward
            $stationIds = PollingStation::where('ward_id', $ward->id)->pluck('id');
            foreach ($stationIds as $sid) {
                $monitor->pollingStations()->syncWithoutDetaching([$sid => ['assigned_at' => now()]]);
            }

            $ward->assigned_approver_id = $user->id;
            $ward->saveQuietly();
        }

        $this->command->info('✓ UserSeeder complete.');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['IEC Administrator',     'admina@iec.gm',       'password123'],
                ['IEC Chairman',          'chairman@iec.gm',  'password123'],
                ['Ward Approver',         'ward@iec.gm',      'password123'],
                ['Constituency Approver', 'constituency@iec.gm', 'password123'],
                ['Polling Officer',       'officer@iec.gm',   'password123'],
                ['Party Agent',           'party@iec.gm',     'password123'],
                ['Election Monitor',      'monitor@iec.gm',   'password123'],
            ]
        );
    }
}
