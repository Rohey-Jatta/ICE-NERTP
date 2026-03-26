<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Election;
use App\Models\AdministrativeHierarchy;
use App\Models\PollingStation;
use App\Models\Candidate;
use App\Models\PoliticalParty;
use App\Models\Result;
use App\Models\ResultCandidateVote;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create test users for ALL 8 ROLES
        $users = [
            [
                'name' => 'System Administrator',
                'email' => 'admina@iec.gm',
                'phone' => '+2203329739',
                'password' => Hash::make('password123'),
                'employee_id' => 'IEC-ADMIN-002',
                'status' => 'active',
                'role' => 'iec-administrator',
            ],
            [
                'name' => 'Test Polling Officer',
                'email' => 'officer@iec.gm',
                'phone' => '+2203329739',
                'password' => Hash::make('password123'),
                'employee_id' => 'TEST-POL',
                'status' => 'active',
                'role' => 'polling-officer',
            ],
            [
                'name' => 'Ward Approver',
                'email' => 'ward@iec.gm',
                'phone' => '+2203329739',
                'password' => Hash::make('password123'),
                'employee_id' => 'WARD-APP-001',
                'status' => 'active',
                'role' => 'ward-approver',
            ],
            [
                'name' => 'Constituency Approver',
                'email' => 'constituency@iec.gm',
                'phone' => '+2203329739',
                'password' => Hash::make('password123'),
                'employee_id' => 'CONST-APP-001',
                'status' => 'active',
                'role' => 'constituency-approver',
            ],
            [
                'name' => 'Admin Area Approver',
                'email' => 'adminarea@iec.gm',
                'phone' => '+2203329739',
                'password' => Hash::make('password123'),
                'employee_id' => 'ADMIN-APP-001',
                'status' => 'active',
                'role' => 'admin-area-approver',
            ],
            [
                'name' => 'IEC Chairman',
                'email' => 'chairman@iec.gm',
                'phone' => '+2203329739',
                'password' => Hash::make('password123'),
                'employee_id' => 'IEC-CHAIR-001',
                'status' => 'active',
                'role' => 'iec-chairman',
            ],
            [
                'name' => 'Party Representative',
                'email' => 'party@iec.gm',
                'phone' => '+2203329739',
                'password' => Hash::make('password123'),
                'employee_id' => 'PARTY-REP-001',
                'status' => 'active',
                'role' => 'party-representative',
            ],
            [
                'name' => 'Election Monitor',
                'email' => 'monitor@iec.gm',
                'phone' => '+2203329739',
                'password' => Hash::make('password123'),
                'employee_id' => 'MON-001',
                'status' => 'active',
                'role' => 'election-monitor',
            ],
        ];

        foreach ($users as $userData) {
            $role = $userData['role'];
            unset($userData['role']);

            $user = User::create($userData);
            $user->assignRole($role);

            $this->command->info("✓ Created user: {$user->name} ({$user->email}) - Role: {$role}");
        }

        // Create test election
        $election = Election::create([
            'name' => '2026 Presidential Election',
            'type' => 'presidential',
            'start_date' => now()->addDays(30),
            'end_date' => now()->addDays(31),
            'status' => 'active',
            'allow_provisional_public_display' => true,
        ]);

        $this->command->info("✓ Created election: {$election->name}");

        // Create administrative hierarchy
        $adminArea = AdministrativeHierarchy::create([
            'election_id' => $election->id,
            'level' => 'admin_area',
            'name' => 'Banjul Administrative Area',
            'parent_id' => null,
        ]);

        $constituency = AdministrativeHierarchy::create([
            'election_id' => $election->id,
            'level' => 'constituency',
            'name' => 'Banjul North Constituency',
            'parent_id' => $adminArea->id,
        ]);

        $ward = AdministrativeHierarchy::create([
            'election_id' => $election->id,
            'level' => 'ward',
            'name' => 'Campama Ward',
            'parent_id' => $constituency->id,
        ]);

        $this->command->info("✓ Created hierarchy: Admin Area → Constituency → Ward");

        // Create polling stations
        $pollingOfficer = User::where('email', 'officer@iec.gm')->first();

        $stations = [
            [
                'code' => 'BNL-001',
                'name' => 'Campama Primary School',
                'ward_id' => $ward->id,
                'election_id' => $election->id,
                'latitude' => 13.4549,
                'longitude' => -16.5790,
                'registered_voters' => 450,
                'assigned_officer_id' => $pollingOfficer->id,
            ],
            [
                'code' => 'BNL-002',
                'name' => 'Mosque Road Polling Station',
                'ward_id' => $ward->id,
                'election_id' => $election->id,
                'latitude' => 13.4560,
                'longitude' => -16.5800,
                'registered_voters' => 380,
                'assigned_officer_id' => null,
            ],
            [
                'code' => 'BNL-003',
                'name' => 'Market Street Center',
                'ward_id' => $ward->id,
                'election_id' => $election->id,
                'latitude' => 13.4570,
                'longitude' => -16.5810,
                'registered_voters' => 520,
                'assigned_officer_id' => null,
            ],
        ];

        foreach ($stations as $stationData) {
            PollingStation::create($stationData);
        }

        $this->command->info("✓ Created " . count($stations) . " polling stations");

        // Create political parties
        $parties = [
            ['name' => 'United Democratic Party', 'abbreviation' => 'UDP', 'color' => '#1e40af'],
            ['name' => 'National People\'s Party', 'abbreviation' => 'NPP', 'color' => '#059669'],
            ['name' => 'Gambia Democratic Congress', 'abbreviation' => 'GDC', 'color' => '#dc2626'],
            ['name' => 'People\'s Democratic Organisation', 'abbreviation' => 'PDOIS', 'color' => '#ea580c'],
        ];

        $createdParties = [];
        foreach ($parties as $partyData) {
            $party = PoliticalParty::create($partyData);
            $createdParties[] = $party;
        }

        $this->command->info("✓ Created " . count($parties) . " political parties");

        // Create candidates
        $candidateNames = [
            'Adama Barrow',
            'Ousainou Darboe',
            'Mama Kandeh',
            'Halifa Sallah',
        ];

        foreach ($createdParties as $index => $party) {
            Candidate::create([
                'election_id' => $election->id,
                'political_party_id' => $party->id,
                'name' => $candidateNames[$index],
                'position' => 'Presidential Candidate',
            ]);
        }

        $this->command->info("✓ Created " . count($candidateNames) . " candidates");

        // Create sample results
        $station = PollingStation::first();
        $candidates = Candidate::where('election_id', $election->id)->get();

        $result = Result::create([
            'election_id' => $election->id,
            'polling_station_id' => $station->id,
            'total_votes_cast' => 420,
            'valid_votes' => 410,
            'rejected_votes' => 10,
            'certification_status' => 'submitted',
            'submitted_by' => $pollingOfficer->id,
            'submitted_at' => now(),
        ]);

        // Add vote breakdown
        $votes = [150, 120, 90, 50]; // Total = 410
        foreach ($candidates as $index => $candidate) {
            ResultCandidateVote::create([
                'result_id' => $result->id,
                'candidate_id' => $candidate->id,
                'votes' => $votes[$index],
            ]);
        }


        $this->command->table(
            ['Role', 'Email', 'Password', 'Phone'],
            [
                ['IEC Administrator', 'admin@iec.gm', 'password123', '+2205872319'],
                ['Polling Officer', 'officer@iec.gm', 'password123', '+2205872320'],
                ['Ward Approver', 'ward@iec.gm', 'password123', '+2205872321'],
                ['Constituency Approver', 'constituency@iec.gm', 'password123', '+2205872322'],
                ['Admin Area Approver', 'adminarea@iec.gm', 'password123', '+2205872323'],
                ['IEC Chairman', 'chairman@iec.gm', 'password123', '+2205872324'],
                ['Party Representative', 'party@iec.gm', 'password123', '+2205872325'],
                ['Election Monitor', 'monitor@iec.gm', 'password123', '+2205872326'],
            ]
        );


    }
}
