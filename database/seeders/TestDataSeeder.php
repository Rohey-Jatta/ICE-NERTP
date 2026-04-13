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
use Illuminate\Support\Facades\DB;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        // ── Step 1: Create all users ──────────────────────────────────────────
        $userData = [
            [
                'name'        => 'System Administrator',
                'email'       => 'admina@iec.gm',
                'phone'       => '+2203329739',
                'password'    => Hash::make('password123'),
                'employee_id' => 'IEC-ADMIN-002',
                'status'      => 'active',
                'role'        => 'iec-administrator',
            ],
            [
                'name'        => 'Test Polling Officer',
                'email'       => 'officer@iec.gm',
                'phone'       => '+2203329740',
                'password'    => Hash::make('password123'),
                'employee_id' => 'TEST-POL',
                'status'      => 'active',
                'role'        => 'polling-officer',
            ],
            [
                'name'        => 'Ward Approver',
                'email'       => 'ward@iec.gm',
                'phone'       => '+2203329741',
                'password'    => Hash::make('password123'),
                'employee_id' => 'WARD-APP-001',
                'status'      => 'active',
                'role'        => 'ward-approver',
            ],
            [
                'name'        => 'Constituency Approver',
                'email'       => 'constituency@iec.gm',
                'phone'       => '+2203329742',
                'password'    => Hash::make('password123'),
                'employee_id' => 'CONST-APP-001',
                'status'      => 'active',
                'role'        => 'constituency-approver',
            ],
            [
                'name'        => 'Admin Area Approver',
                'email'       => 'adminarea@iec.gm',
                'phone'       => '+2203329743',
                'password'    => Hash::make('password123'),
                'employee_id' => 'ADMIN-APP-001',
                'status'      => 'active',
                'role'        => 'admin-area-approver',
            ],
            [
                'name'        => 'IEC Chairman',
                'email'       => 'chairman@iec.gm',
                'phone'       => '+2203329744',
                'password'    => Hash::make('password123'),
                'employee_id' => 'IEC-CHAIR-001',
                'status'      => 'active',
                'role'        => 'iec-chairman',
            ],
            [
                'name'        => 'Party Representative',
                'email'       => 'party@iec.gm',
                'phone'       => '+2203329745',
                'password'    => Hash::make('password123'),
                'employee_id' => 'PARTY-REP-001',
                'status'      => 'active',
                'role'        => 'party-representative',
            ],
            [
                'name'        => 'Election Monitor',
                'email'       => 'monitor@iec.gm',
                'phone'       => '+2203329746',
                'password'    => Hash::make('password123'),
                'employee_id' => 'MON-001',
                'status'      => 'active',
                'role'        => 'election-monitor',
            ],
        ];

        foreach ($userData as $item) {
            $role = $item['role'];
            unset($item['role']);
            $user = User::updateOrCreate(['email' => $item['email']], $item);
            $user->syncRoles([$role]);
            $this->command->info("✓ Created user: {$user->name} ({$user->email}) - Role: {$role}");
        }

        $admin          = User::where('email', 'admina@iec.gm')->firstOrFail();
        $pollingOfficer = User::where('email', 'officer@iec.gm')->firstOrFail();
        $wardApprover   = User::where('email', 'ward@iec.gm')->firstOrFail();
        $constApprover  = User::where('email', 'constituency@iec.gm')->firstOrFail();
        $areaApprover   = User::where('email', 'adminarea@iec.gm')->firstOrFail();

        // // ── Step 2: Create election ───────────────────────────────────────────
        // $election = Election::updateOrCreate(
        //     ['name' => '2026 Presidential Election'],
        //     [
        //         'type'                            => 'presidential',
        //         'start_date'                      => now()->addMonth(),
        //         'end_date'                        => now()->addMonth()->addDay(),
        //         'status'                          => 'active',
        //         'created_by'                      => $admin->id,
        //         'allow_provisional_public_display' => true,
        //         'slug'                            => '2026-presidential-election',
        //     ]
        // );
        // $this->command->info("✓ Created election: {$election->name} (ID: {$election->id})");

        // // ── Step 3: Create administrative hierarchy (national → area → constituency → ward) ──
        // // National level — used by IEC Chairman for final certification
        // $national = AdministrativeHierarchy::updateOrCreate(
        //     ['election_id' => $election->id, 'level' => 'national', 'name' => 'The Gambia'],
        //     ['parent_id' => null]
        // );

        // $adminArea = AdministrativeHierarchy::updateOrCreate(
        //     ['election_id' => $election->id, 'level' => 'admin_area', 'name' => 'Banjul Administrative Area'],
        //     ['parent_id' => $national->id, 'assigned_approver_id' => $areaApprover->id]
        // );

        // $constituency = AdministrativeHierarchy::updateOrCreate(
        //     ['election_id' => $election->id, 'level' => 'constituency', 'name' => 'Banjul North Constituency'],
        //     ['parent_id' => $adminArea->id, 'assigned_approver_id' => $constApprover->id]
        // );

        // $ward = AdministrativeHierarchy::updateOrCreate(
        //     ['election_id' => $election->id, 'level' => 'ward', 'name' => 'Campama Ward'],
        //     ['parent_id' => $constituency->id, 'assigned_approver_id' => $wardApprover->id]
        // );

        // $this->command->info("✓ Created hierarchy: National → Admin Area (ID:{$adminArea->id}) → Constituency (ID:{$constituency->id}) → Ward (ID:{$ward->id})");

       // ── Step 4: Create political parties ──────────────────────────────────
        // $partiesData = [
        //     ['name' => 'United Democratic Party',          'abbreviation' => 'UDP',   'color' => '#1e40af'],
        //     ['name' => "National People's Party",          'abbreviation' => 'NPP',   'color' => '#059669'],
        //     ['name' => 'Gambia Democratic Congress',       'abbreviation' => 'GDC',   'color' => '#dc2626'],
        //     ['name' => "People's Democratic Organisation", 'abbreviation' => 'PDOIS', 'color' => '#ea580c'],
        // ];

        // $createdParties = [];
        // foreach ($partiesData as $partyData) {
        //     $party = PoliticalParty::updateOrCreate(
        //         ['election_id' => $election->id, 'abbreviation' => $partyData['abbreviation']],
        //         [
        //             'name'        => $partyData['name'],
        //             'color'       => $partyData['color'],
        //             'slug'        => \Illuminate\Support\Str::slug($partyData['name']),
        //             'election_id' => $election->id,
        //         ]
        //     );
        //     $createdParties[] = $party;
        // }
        // $this->command->info("✓ Created " . count($createdParties) . " political parties");

        // // ── Step 5: Create polling stations ───────────────────────────────────
        // $stationsData = [
        //     [
        //         'code'                => 'BNL-001',
        //         'name'                => 'Campama Primary School',
        //         'ward_id'             => $ward->id,
        //         'election_id'         => $election->id,
        //         'latitude'            => 13.4549,
        //         'longitude'           => -16.5790,
        //         'registered_voters'   => 450,
        //         'assigned_officer_id' => $pollingOfficer->id,
        //         'is_active'           => true,
        //     ],
        //     [
        //         'code'              => 'BNL-002',
        //         'name'              => 'Mosque Road Polling Station',
        //         'ward_id'           => $ward->id,
        //         'election_id'       => $election->id,
        //         'latitude'          => 13.4560,
        //         'longitude'         => -16.5800,
        //         'registered_voters' => 380,
        //         'is_active'         => true,
        //     ],
        //     [
        //         'code'              => 'BNL-003',
        //         'name'              => 'Market Street Center',
        //         'ward_id'           => $ward->id,
        //         'election_id'       => $election->id,
        //         'latitude'          => 13.4570,
        //         'longitude'         => -16.5810,
        //         'registered_voters' => 520,
        //         'is_active'         => true,
        //     ],
        // ];

        // $stations = [];
        // foreach ($stationsData as $stationData) {
        //     $stations[] = PollingStation::updateOrCreate(
        //         ['code' => $stationData['code']],
        //         $stationData
        //     );
        // }
        // $this->command->info("✓ Created " . count($stations) . " polling stations");

        // ── Step 6: Create candidates ─────────────────────────────────────────
        // $candidateNames = [
        //     'UDP'   => 'Adama Barrow',
        //     'NPP'   => 'Ousainou Darboe',
        //     'GDC'   => 'Mama Kandeh',
        //     'PDOIS' => 'Halifa Sallah',
        // ];

        // $createdCandidates = [];
        // foreach ($createdParties as $party) {
        //     $candidate = Candidate::updateOrCreate(
        //         ['election_id' => $election->id, 'political_party_id' => $party->id],
        //         [
        //             'name'           => $candidateNames[$party->abbreviation] ?? $party->name . ' Candidate',
        //             'ballot_number'  => (string)(array_search($party, $createdParties) + 1),
        //             'is_independent' => false,
        //             'is_active'      => true,
        //         ]
        //     );
        //     $createdCandidates[] = $candidate;
        // }
        // $this->command->info("✓ Created " . count($createdCandidates) . " candidates");

        // ── Step 7: Create a sample submitted result ──────────────────────────
        // $firstStation = $stations[0];

        // $result = Result::updateOrCreate(
        //     ['polling_station_id' => $firstStation->id, 'election_id' => $election->id],
        //     [
        //         'submission_uuid'         => \Illuminate\Support\Str::uuid(),
        //         'user_id'                 => $pollingOfficer->id,
        //         'total_registered_voters' => 450,
        //         'total_votes_cast'        => 420,
        //         'valid_votes'             => 410,
        //         'rejected_votes'          => 10,
        //         'disputed_votes'          => 0,
        //         'certification_status'    => 'submitted',
        //         'submitted_by'            => $pollingOfficer->id,
        //         'submitted_at'            => now(),
        //         'gps_validated'           => false,
        //     ]
        // );

        // $voteDistribution = [150, 120, 90, 50];
        // foreach ($createdCandidates as $i => $candidate) {
        //     ResultCandidateVote::updateOrCreate(
        //         ['result_id' => $result->id, 'candidate_id' => $candidate->id],
        //         [
        //             'election_id' => $election->id,
        //             'votes'       => $voteDistribution[$i] ?? 0,
        //         ]
        //     );
        // }
        // $this->command->info("✓ Created sample result with candidate vote breakdown");

        // ── Summary ───────────────────────────────────────────────────────────
        $this->command->newLine();
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('                  TEST CREDENTIALS                     ');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['IEC Administrator',     'admina@iec.gm',       'password123'],
                ['Polling Officer',       'officer@iec.gm',      'password123'],
                ['Ward Approver',         'ward@iec.gm',         'password123'],
                ['Constituency Approver', 'constituency@iec.gm', 'password123'],
                ['Admin Area Approver',   'adminarea@iec.gm',    'password123'],
                ['IEC Chairman',          'chairman@iec.gm',     'password123'],
                ['Party Representative',  'party@iec.gm',        'password123'],
                ['Election Monitor',      'monitor@iec.gm',      'password123'],
            ]
        );
        $this->command->info('All passwords: password123');
        $this->command->info('2FA codes are logged to storage/logs/laravel.log');
    }
}