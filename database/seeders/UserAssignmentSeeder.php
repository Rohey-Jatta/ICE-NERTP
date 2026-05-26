<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\PollingStation;
use App\Models\AdministrativeHierarchy;
use App\Models\Candidate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserAssignmentSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info("Assigning users...");

        $stations = PollingStation::all();
        $wards = AdministrativeHierarchy::where('level', 'ward')->get();
        $candidates = Candidate::all();

        /*
        ─────────────────────────────
        1. POLLING OFFICERS (1 per station)
        ─────────────────────────────
        */
        foreach ($stations as $station) {

            $user = User::firstOrCreate([
                'email' => "officer-{$station->id}@iec.gm"
            ], [
                'name' => "Officer {$station->name}",
                'password' => Hash::make('password123'),
                'status' => 'active',
            ]);

            $user->syncRoles(['polling-officer']);

            $station->assigned_officer_id = $user->id;
            $station->save();
        }

        /*
        ─────────────────────────────
        2. ELECTION MONITORS (1 per ward)
        ─────────────────────────────
        */
        foreach ($wards as $ward) {

            $user = User::firstOrCreate([
                'email' => "monitor-{$ward->id}@iec.gm"
            ], [
                'name' => "Monitor {$ward->name}",
                'password' => Hash::make('password123'),
                'status' => 'active',
            ]);

            $user->syncRoles(['election-monitor']);

            $ward->assigned_approver_id = $user->id;
            $ward->save();
        }

        /*
        ─────────────────────────────
        3. PARTY REPS (1 per candidate per station)
        ─────────────────────────────
        */
        foreach ($stations as $station) {
            foreach ($candidates as $candidate) {

                $user = User::firstOrCreate([
                    'email' => "rep-{$candidate->id}-{$station->id}@iec.gm"
                ], [
                    'name' => "Rep {$candidate->name} @ {$station->name}",
                    'password' => Hash::make('password123'),
                    'status' => 'active',
                ]);

                $user->syncRoles(['party-representative']);

                // optional pivot table
                // DB::table('party_station_assignments')->insertOrIgnore([...]);
            }
        }
    }
}
