<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\PollingStation;
use App\Models\AdministrativeHierarchy;
use App\Models\Candidate;
use App\Models\ElectionMonitor;
use App\Models\PartyRepresentative;
use App\Models\PoliticalParty;
use Illuminate\Support\Facades\Hash;

class MassUserSeeder extends Seeder
{
    public function run(): void
    {
        $electionId = \App\Models\Election::where('slug', 'gambia-2021-presidential')->value('id');
        if (!$electionId) {
            throw new \RuntimeException('Election not found');
        }

        $stations = PollingStation::all();
        $wards = AdministrativeHierarchy::where('level', 'ward')->get();
        $candidates = Candidate::where('election_id', $electionId)->get();
        $parties = PoliticalParty::where('election_id', $electionId)->get();

        // 1. Polling Officers (1 per station)
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

        // 2. Election Monitors (1 per ward) – also create the monitor record
        foreach ($wards as $ward) {
            $user = User::firstOrCreate([
                'email' => "monitor-{$ward->id}@iec.gm"
            ], [
                'name' => "Monitor {$ward->name}",
                'password' => Hash::make('password123'),
                'status' => 'active',
            ]);
            $user->syncRoles(['election-monitor']);

            // Create the ElectionMonitor record
            $monitor = ElectionMonitor::firstOrCreate([
                'user_id' => $user->id,
                'election_id' => $electionId,
            ], [
                'type' => 'domestic',
                'is_active' => true,
            ]);

            // Assign monitor to all stations in this ward
            $stationIds = PollingStation::where('ward_id', $ward->id)->pluck('id')->toArray();
            foreach ($stationIds as $sid) {
                $monitor->pollingStations()->syncWithoutDetaching([$sid => ['assigned_at' => now()]]);
            }

            $ward->assigned_approver_id = $user->id;
            $ward->save();
        }

        // 3. Party Representatives (1 per candidate per station)
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

                // Find the political party (candidate may be independent)
                $partyId = $candidate->political_party_id;
                if (!$partyId && !$candidate->is_independent) continue;

                // Create PartyRepresentative record
                $partyRep = PartyRepresentative::firstOrCreate([
                    'user_id' => $user->id,
                    'election_id' => $electionId,
                ], [
                    'political_party_id' => $partyId,
                    'is_active' => true,
                ]);

                // Attach to polling station via pivot
                $partyRep->pollingStations()->syncWithoutDetaching([
                    $station->id => ['assigned_at' => now(), 'assigned_by' => 1] // assigned_by = seeder admin
                ]);
            }
        }
    }
}
