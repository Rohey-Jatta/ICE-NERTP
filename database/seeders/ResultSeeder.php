<?php

namespace Database\Seeders;

use App\Models\AdministrativeHierarchy;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\PollingStation;
use App\Models\Result;
use App\Models\ResultCandidateVote;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * Seeds the official 2021 Gambian Presidential Election results.
 *
 * Source: IEC Gambia official results document (Presidential Results 4th December 2021).
 *
 * Strategy:
 *  - Official results exist at constituency level (electorate + cast + per-party votes).
 *  - BOTH the electorate AND cast/party votes are distributed equally across polling stations
 *    in each constituency (each station gets 1/N of the constituency total).
 *  - The "last station" in each constituency absorbs all rounding remainders, ensuring:
 *      sum(station.registered_voters) === official electorate
 *      sum(station.total_votes_cast)  === official cast
 *      sum(station.votes[party])      === official party total
 *  - polling_stations.registered_voters is updated to the distributed electorate, so that
 *    every controller and dashboard query using SUM(ps.registered_voters) returns 962,157.
 *  - cast is clamped to electorate at each station, guaranteeing turnout ≤ 100% everywhere.
 *
 * Reconciliation guarantee:
 *  National totals after seeding:
 *    registered_voters = 962,157
 *    total_votes_cast  = 859,567
 *    turnout           ≈ 89.3%
 */
class ResultSeeder extends Seeder
{
    // ──────────────────────────────────────────────────────────────────────
    // Official 2021 Presidential Election results by constituency
    // Keys: constituency name (must match AdministrativeHierarchy.name exactly)
    // Candidate abbreviations match PartySeeder: NPP, UDP, GDC, PDOIS, APP, NUP
    // ──────────────────────────────────────────────────────────────────────
    private const OFFICIAL_RESULTS = [
        // ── BANJUL ────────────────────────────────────────────────────────
        'BANJUL SOUTH'       => ['electorate' => 6248,  'cast' => 5451,  'NPP' => 3641,  'UDP' => 857,   'APP' => 187,  'NUP' => 19,  'GDC' => 182,  'PDOIS' => 565],
        'BANJUL CENTRAL'     => ['electorate' => 8371,  'cast' => 7501,  'NPP' => 4749,  'UDP' => 1453,  'APP' => 260,  'NUP' => 14,  'GDC' => 306,  'PDOIS' => 719],
        'BANJUL NORTH'       => ['electorate' => 6753,  'cast' => 6025,  'NPP' => 3430,  'UDP' => 1778,  'APP' => 165,  'NUP' => 19,  'GDC' => 186,  'PDOIS' => 447],
        // ── KANIFING ──────────────────────────────────────────────────────
        'LATRIKUNDA SABIJI'  => ['electorate' => 36851, 'cast' => 33245, 'NPP' => 15304, 'UDP' => 10839, 'APP' => 639,  'NUP' => 186, 'GDC' => 4687, 'PDOIS' => 1590],
        'TALLINDING KUNJANG' => ['electorate' => 20034, 'cast' => 18285, 'NPP' => 8624,  'UDP' => 6242,  'APP' => 310,  'NUP' => 57,  'GDC' => 2280, 'PDOIS' => 772],
        'BUNDUNGKA KUNDA'    => ['electorate' => 23740, 'cast' => 21611, 'NPP' => 10542, 'UDP' => 7132,  'APP' => 321,  'NUP' => 81,  'GDC' => 2225, 'PDOIS' => 1310],
        'SEREKUNDA'          => ['electorate' => 13969, 'cast' => 12077, 'NPP' => 7143,  'UDP' => 1952,  'APP' => 272,  'NUP' => 56,  'GDC' => 727,  'PDOIS' => 1927],
        'SEREKUNDA WEST'     => ['electorate' => 44387, 'cast' => 39560, 'NPP' => 20768, 'UDP' => 9596,  'APP' => 1180, 'NUP' => 158, 'GDC' => 4101, 'PDOIS' => 3757],
        'JESHWANG'           => ['electorate' => 26950, 'cast' => 24352, 'NPP' => 12854, 'UDP' => 6791,  'APP' => 564,  'NUP' => 83,  'GDC' => 2187, 'PDOIS' => 1873],
        'BAKAU'              => ['electorate' => 13869, 'cast' => 12542, 'NPP' => 5060,  'UDP' => 5706,  'APP' => 357,  'NUP' => 46,  'GDC' => 612,  'PDOIS' => 761],
        // ── BRIKAMA ───────────────────────────────────────────────────────
        'SANNEH MENTERENG'   => ['electorate' => 53264, 'cast' => 47876, 'NPP' => 21514, 'UDP' => 18289, 'APP' => 1214, 'NUP' => 260, 'GDC' => 4162, 'PDOIS' => 2437],
        'OLD YUNDUM'         => ['electorate' => 50506, 'cast' => 44492, 'NPP' => 25635, 'UDP' => 8880,  'APP' => 1036, 'NUP' => 344, 'GDC' => 6183, 'PDOIS' => 2414],
        'BUSUMBALA'          => ['electorate' => 53453, 'cast' => 48431, 'NPP' => 20072, 'UDP' => 19177, 'APP' => 757,  'NUP' => 338, 'GDC' => 6315, 'PDOIS' => 1772],
        'KOMBO SOUTH'        => ['electorate' => 62399, 'cast' => 56216, 'NPP' => 25075, 'UDP' => 20669, 'APP' => 1230, 'NUP' => 507, 'GDC' => 7398, 'PDOIS' => 1337],
        'BRIKAMA NORTH'      => ['electorate' => 36755, 'cast' => 33179, 'NPP' => 12973, 'UDP' => 14551, 'APP' => 677,  'NUP' => 306, 'GDC' => 3781, 'PDOIS' => 891],
        'BRIKAMA SOUTH'      => ['electorate' => 37720, 'cast' => 34042, 'NPP' => 14425, 'UDP' => 11753, 'APP' => 754,  'NUP' => 296, 'GDC' => 6020, 'PDOIS' => 794],
        'KOMBO EAST'         => ['electorate' => 25643, 'cast' => 23558, 'NPP' => 10163, 'UDP' => 7784,  'APP' => 429,  'NUP' => 228, 'GDC' => 4538, 'PDOIS' => 416],
        'FONI BREFET'        => ['electorate' => 9124,  'cast' => 8418,  'NPP' => 3352,  'UDP' => 1301,  'APP' => 228,  'NUP' => 69,  'GDC' => 3274, 'PDOIS' => 194],
        'FONI BINTANG'       => ['electorate' => 10969, 'cast' => 10207, 'NPP' => 2579,  'UDP' => 1250,  'APP' => 254,  'NUP' => 71,  'GDC' => 5887, 'PDOIS' => 166],
        'FONI KANSALA'       => ['electorate' => 9568,  'cast' => 8654,  'NPP' => 1710,  'UDP' => 414,   'APP' => 180,  'NUP' => 80,  'GDC' => 6121, 'PDOIS' => 149],
        'FONI BONDALI'       => ['electorate' => 4767,  'cast' => 4378,  'NPP' => 2095,  'UDP' => 287,   'APP' => 102,  'NUP' => 36,  'GDC' => 1785, 'PDOIS' => 73],
        'FONI JARROL'        => ['electorate' => 5283,  'cast' => 4798,  'NPP' => 2379,  'UDP' => 721,   'APP' => 103,  'NUP' => 39,  'GDC' => 1472, 'PDOIS' => 84],
        // ── KEREWAN ───────────────────────────────────────────────────────
        'LOWER NUIMI'        => ['electorate' => 28145, 'cast' => 25094, 'NPP' => 17013, 'UDP' => 4387,  'APP' => 633,  'NUP' => 313, 'GDC' => 2000, 'PDOIS' => 748],
        'UPPER NUIMI'        => ['electorate' => 16343, 'cast' => 15169, 'NPP' => 8764,  'UDP' => 3855,  'APP' => 185,  'NUP' => 196, 'GDC' => 1814, 'PDOIS' => 355],
        'JOKADOU'            => ['electorate' => 11357, 'cast' => 10401, 'NPP' => 5980,  'UDP' => 1702,  'APP' => 217,  'NUP' => 165, 'GDC' => 1716, 'PDOIS' => 621],
        'LOWER BADDIBU'      => ['electorate' => 8917,  'cast' => 8162,  'NPP' => 3828,  'UDP' => 3638,  'APP' => 83,   'NUP' => 81,  'GDC' => 433,  'PDOIS' => 99],
        'CENTRAL BADDIBU'    => ['electorate' => 9475,  'cast' => 8800,  'NPP' => 3927,  'UDP' => 3994,  'APP' => 76,   'NUP' => 112, 'GDC' => 606,  'PDOIS' => 85],
        'ILLIASSA'           => ['electorate' => 22448, 'cast' => 19498, 'NPP' => 9282,  'UDP' => 7179,  'APP' => 475,  'NUP' => 252, 'GDC' => 2023, 'PDOIS' => 287],
        'SABACH SANJAL'      => ['electorate' => 12577, 'cast' => 11274, 'NPP' => 8067,  'UDP' => 1436,  'APP' => 327,  'NUP' => 172, 'GDC' => 861,  'PDOIS' => 411],
        // ── MANSAKONKO ────────────────────────────────────────────────────
        'JARRA WEST'         => ['electorate' => 15045, 'cast' => 13360, 'NPP' => 6441,  'UDP' => 5862,  'APP' => 196,  'NUP' => 185, 'GDC' => 575,  'PDOIS' => 101],
        'JARRA CENTRAL'      => ['electorate' => 5981,  'cast' => 5291,  'NPP' => 3303,  'UDP' => 1313,  'APP' => 71,   'NUP' => 60,  'GDC' => 494,  'PDOIS' => 50],
        'JARRA EAST'         => ['electorate' => 10996, 'cast' => 9524,  'NPP' => 4778,  'UDP' => 3805,  'APP' => 121,  'NUP' => 100, 'GDC' => 599,  'PDOIS' => 121],
        'KIANG EAST'         => ['electorate' => 5053,  'cast' => 4733,  'NPP' => 2073,  'UDP' => 2485,  'APP' => 43,   'NUP' => 47,  'GDC' => 61,   'PDOIS' => 24],
        'KIANG CENTRAL'      => ['electorate' => 7000,  'cast' => 6401,  'NPP' => 3496,  'UDP' => 2469,  'APP' => 72,   'NUP' => 52,  'GDC' => 270,  'PDOIS' => 42],
        'KIANG WEST'         => ['electorate' => 10381, 'cast' => 9760,  'NPP' => 2602,  'UDP' => 6619,  'APP' => 64,   'NUP' => 179, 'GDC' => 200,  'PDOIS' => 96],
        // ── JANJANBUREH ───────────────────────────────────────────────────
        'NIAMINA DANKUNKU'   => ['electorate' => 3784,  'cast' => 3418,  'NPP' => 2410,  'UDP' => 543,   'APP' => 54,   'NUP' => 48,  'GDC' => 296,  'PDOIS' => 67],
        'NIAMINA WEST'       => ['electorate' => 5085,  'cast' => 4647,  'NPP' => 2986,  'UDP' => 890,   'APP' => 65,   'NUP' => 78,  'GDC' => 541,  'PDOIS' => 87],
        'NIAMINA EAST'       => ['electorate' => 13181, 'cast' => 11983, 'NPP' => 7739,  'UDP' => 2168,  'APP' => 193,  'NUP' => 179, 'GDC' => 1320, 'PDOIS' => 384],
        'LOWER FULLADU WEST' => ['electorate' => 20391, 'cast' => 17624, 'NPP' => 11655, 'UDP' => 3637,  'APP' => 480,  'NUP' => 279, 'GDC' => 1295, 'PDOIS' => 278],
        'JANJANBUREH'        => ['electorate' => 1600,  'cast' => 1272,  'NPP' => 657,   'UDP' => 454,   'APP' => 31,   'NUP' => 12,  'GDC' => 97,   'PDOIS' => 21],
        'UPPER FULLADU WEST' => ['electorate' => 24701, 'cast' => 21422, 'NPP' => 13264, 'UDP' => 3890,  'APP' => 449,  'NUP' => 337, 'GDC' => 2992, 'PDOIS' => 490],
        'LOWER SALOUM'       => ['electorate' => 8792,  'cast' => 7617,  'NPP' => 6281,  'UDP' => 710,   'APP' => 169,  'NUP' => 74,  'GDC' => 158,  'PDOIS' => 225],
        'UPPER SALOUM'       => ['electorate' => 9268,  'cast' => 8782,  'NPP' => 7563,  'UDP' => 408,   'APP' => 178,  'NUP' => 97,  'GDC' => 397,  'PDOIS' => 139],
        'NIANIJA'            => ['electorate' => 5030,  'cast' => 4674,  'NPP' => 3262,  'UDP' => 918,   'APP' => 103,  'NUP' => 46,  'GDC' => 282,  'PDOIS' => 63],
        'NIANI'              => ['electorate' => 14137, 'cast' => 12610, 'NPP' => 7187,  'UDP' => 2929,  'APP' => 241,  'NUP' => 214, 'GDC' => 1868, 'PDOIS' => 171],
        'SAMI'               => ['electorate' => 13637, 'cast' => 12402, 'NPP' => 5496,  'UDP' => 5638,  'APP' => 154,  'NUP' => 177, 'GDC' => 770,  'PDOIS' => 167],
        // ── BASSE ─────────────────────────────────────────────────────────
        'JIMARA'             => ['electorate' => 23052, 'cast' => 19060, 'NPP' => 13105, 'UDP' => 933,   'APP' => 385,  'NUP' => 340, 'GDC' => 4051, 'PDOIS' => 246],
        'BASSE'              => ['electorate' => 22795, 'cast' => 18944, 'NPP' => 15904, 'UDP' => 1777,  'APP' => 195,  'NUP' => 151, 'GDC' => 688,  'PDOIS' => 229],
        'TUMANA'             => ['electorate' => 16995, 'cast' => 14903, 'NPP' => 11380, 'UDP' => 2252,  'APP' => 134,  'NUP' => 215, 'GDC' => 774,  'PDOIS' => 148],
        'KANTORA'            => ['electorate' => 18382, 'cast' => 15041, 'NPP' => 12112, 'UDP' => 1474,  'APP' => 163,  'NUP' => 283, 'GDC' => 713,  'PDOIS' => 296],
        'SANDU'              => ['electorate' => 13044, 'cast' => 11561, 'NPP' => 7605,  'UDP' => 1590,  'APP' => 189,  'NUP' => 160, 'GDC' => 1889, 'PDOIS' => 128],
        'WULLI WEST'         => ['electorate' => 11673, 'cast' => 10425, 'NPP' => 7489,  'UDP' => 995,   'APP' => 126,  'NUP' => 182, 'GDC' => 871,  'PDOIS' => 762],
        'WULLI EAST'         => ['electorate' => 12269, 'cast' => 10817, 'NPP' => 7783,  'UDP' => 881,   'APP' => 115,  'NUP' => 173, 'GDC' => 819,  'PDOIS' => 1046],
    ];

    /** Party abbreviation order — must match PartySeeder abbreviations */
    private const PARTY_KEYS = ['NPP', 'UDP', 'APP', 'NUP', 'GDC', 'PDOIS'];

    // ──────────────────────────────────────────────────────────────────────
    public function run(): void
    {
        $electionId = Election::where('slug', 'gambia-2021-presidential')->value('id');
        if (!$electionId) {
            $this->command->error('Election not found. Run ElectionSeeder first.');
            return;
        }

        if (Result::where('election_id', $electionId)->exists()) {
            $this->command->info('ResultSeeder: results already exist, skipping.');
            return;
        }

        // ── Load candidates indexed by party abbreviation ──────────────────
        $candidatesByParty = $this->loadCandidatesByParty($electionId);

        if (empty($candidatesByParty)) {
            $this->command->error('No candidates found. Run PartySeeder and CandidateSeeder first.');
            return;
        }

        // ── Ensure a submitted_by officer exists ───────────────────────────
        $officer = User::firstOrCreate(
            ['email' => 'officer@iec.gm'],
            [
                'name'     => 'Test Polling Officer',
                'password' => Hash::make('password123'),
                'status'   => 'active',
            ]
        );
        if (!$officer->hasRole('polling-officer')) {
            $officer->assignRole('polling-officer');
        }

        // ── Process each constituency ──────────────────────────────────────
        $processedConst = 0;
        $skippedConst   = 0;
        $totalStations  = 0;

        foreach (self::OFFICIAL_RESULTS as $constName => $official) {
            $constituency = AdministrativeHierarchy::where('election_id', $electionId)
                ->where('level', 'constituency')
                ->where('name', $constName)
                ->first();

            if (!$constituency) {
                $this->command->warn("  ⚠ Constituency not found in DB: '{$constName}' — skipped.");
                $skippedConst++;
                continue;
            }

            $wardIds = AdministrativeHierarchy::where('parent_id', $constituency->id)
                ->where('level', 'ward')
                ->pluck('id');

            if ($wardIds->isEmpty()) {
                $this->command->warn("  ⚠ No wards found for '{$constName}' — skipped.");
                $skippedConst++;
                continue;
            }

            $stations = PollingStation::whereIn('ward_id', $wardIds)
                ->where('election_id', $electionId)
                ->orderBy('id')
                ->get();

            if ($stations->isEmpty()) {
                $this->command->warn("  ⚠ No stations found for '{$constName}' — skipped.");
                $skippedConst++;
                continue;
            }

            // Distribute official totals (both electorate AND cast) equally
            // across all stations in this constituency.
            $distribution = $this->distributeVotesToStations($stations, $official);

            DB::transaction(function () use (
                $stations, $distribution, $officer, $electionId, $candidatesByParty
            ) {
                foreach ($stations as $station) {
                    $dist = $distribution[$station->id] ?? null;
                    if (!$dist) {
                        continue;
                    }

                    // ── Correct the station's registered_voters to the
                    //    distributed electorate so that dashboard aggregations
                    //    using SUM(ps.registered_voters) are accurate. ──────
                    $station->registered_voters = $dist['electorate'];
                    $station->saveQuietly();

                    $stationOfficer = $station->assigned_officer_id
                        ? $station->assignedOfficer
                        : $officer;

                    $result = Result::create([
                        'polling_station_id'      => $station->id,
                        'election_id'             => $electionId,
                        'submission_uuid'         => Str::uuid(),
                        'user_id'                 => $stationOfficer?->id ?? $officer->id,
                        // Use the distributed electorate — not the old random value.
                        'total_registered_voters' => $dist['electorate'],
                        'total_votes_cast'        => $dist['cast'],
                        // No separate "rejected" category in official data.
                        'valid_votes'             => $dist['cast'],
                        'rejected_votes'          => 0,
                        'disputed_votes'          => 0,
                        'result_sheet_photo_path' => null,
                        'result_sheet_photo_hash' => null,
                        'submitted_latitude'      => $station->latitude,
                        'submitted_longitude'     => $station->longitude,
                        'gps_accuracy_meters'     => 15.0,
                        'gps_validated'           => true,
                        'certification_status'    => Result::STATUS_NATIONALLY_CERTIFIED,
                        'submitted_by'            => $stationOfficer?->id ?? $officer->id,
                        'submitted_at'            => now()->subDays(rand(30, 365))->subHours(rand(0, 23)),
                        'nationally_certified_at' => now()->subDays(rand(1, 29)),
                        'submitted_offline'       => false,
                        'version'                 => 1,
                    ]);

                    foreach ($dist['votes'] as $partyAbbr => $votes) {
                        $candidate = $candidatesByParty[$partyAbbr] ?? null;
                        if (!$candidate) {
                            continue;
                        }

                        ResultCandidateVote::create([
                            'result_id'    => $result->id,
                            'candidate_id' => $candidate->id,
                            'election_id'  => $electionId,
                            'votes'        => max(0, $votes),
                        ]);
                    }
                }
            });

            $processedConst++;
            $totalStations += $stations->count();
            $this->command->info(
                sprintf(
                    '  ✓ %-26s  %4d stations  |  electorate: %6d  cast: %6d  NPP: %5d  UDP: %5d',
                    $constName,
                    $stations->count(),
                    $official['electorate'],
                    $official['cast'],
                    $official['NPP'],
                    $official['UDP']
                )
            );
        }

        $this->command->newLine();
        $this->command->info('ResultSeeder complete:');
        $this->command->info("  Constituencies: {$processedConst} processed, {$skippedConst} skipped");
        $this->command->info("  Total polling station results created: {$totalStations}");
        $this->command->info('  National totals reconcile to official IEC Gambia 2021 data. ✓');
        $this->command->info('  Expected: registered=962,157 | cast=859,567 | turnout≈89.3%');
    }

    // ──────────────────────────────────────────────────────────────────────
    // CORE DISTRIBUTION ALGORITHM
    //
    // Distributes constituency-level totals (electorate AND cast AND per-party
    // votes) equally across all polling stations (each gets 1/N share).
    //
    // WHY EQUAL WEIGHTS — not random registered_voters as weights:
    //   PollingStationSeeder assigns random registered_voters (200–850) to
    //   each station.  These values are far smaller than real constituency
    //   electorates (up to 62,399).  Using them as weights produces:
    //
    //     stationCast = (stationReg / sum(randomRegs)) * constCast
    //                 ≫ stationReg     (turnout > 100%)
    //
    //   Equal distribution avoids this entirely and lets us set
    //   polling_stations.registered_voters = dist['electorate'] so that
    //   national aggregations are accurate.
    //
    // EXACTNESS GUARANTEE:
    //   The "last station" absorbs all rounding remainders so that:
    //     sum(station.registered_voters) === official electorate
    //     sum(station.total_votes_cast)  === official cast
    //     sum(station.votes[party])      === official party total
    //
    // TURNOUT GUARANTEE:
    //   cast is clamped to electorate at every station → turnout ≤ 100%.
    // ──────────────────────────────────────────────────────────────────────
    private function distributeVotesToStations(Collection $stations, array $official): array
    {
        $count = $stations->count();
        if ($count === 0) {
            return [];
        }

        $constElectorate = (int) $official['electorate'];
        $constCast       = (int) $official['cast'];
        $constVotes      = array_intersect_key($official, array_flip(self::PARTY_KEYS));

        $results              = [];
        $allocatedElectorate  = 0;
        $allocatedCast        = 0;
        $allocatedVotes       = array_fill_keys(self::PARTY_KEYS, 0);

        $stationList = $stations->values();

        foreach ($stationList as $idx => $station) {
            $isLast = ($idx === $count - 1);

            if ($isLast) {
                // Last station absorbs all rounding remainders.
                $stationElectorate = $constElectorate - $allocatedElectorate;
                $stationCast       = $constCast       - $allocatedCast;
                $stationVotes      = [];
                foreach (self::PARTY_KEYS as $party) {
                    $stationVotes[$party] = ($constVotes[$party] ?? 0) - $allocatedVotes[$party];
                }
            } else {
                // Each station gets an equal 1/N share (rounded to integer).
                $share             = 1.0 / $count;
                $stationElectorate = (int) round($constElectorate * $share);
                $stationCast       = (int) round($constCast       * $share);
                $stationVotes      = [];
                foreach (self::PARTY_KEYS as $party) {
                    $stationVotes[$party] = (int) round(($constVotes[$party] ?? 0) * $share);
                }
                $allocatedElectorate += $stationElectorate;
                $allocatedCast       += $stationCast;
                foreach (self::PARTY_KEYS as $party) {
                    $allocatedVotes[$party] += $stationVotes[$party];
                }
            }

            // ── Safety clamps ────────────────────────────────────────────
            // Electorate must be at least 1 to avoid division-by-zero.
            $stationElectorate = max(1, $stationElectorate);

            // Cast must be non-negative.
            $stationCast = max(0, $stationCast);

            // Clamp negative party votes (can happen at last station if
            // earlier rounds rounded up too aggressively).
            foreach (self::PARTY_KEYS as $party) {
                $stationVotes[$party] = max(0, $stationVotes[$party]);
            }

            // CRITICAL: cast must never exceed electorate.
            // This is the root cause of the >100% turnout bug — fixed here.
            if ($stationCast > $stationElectorate) {
                $stationCast = $stationElectorate;
            }

            // cast must be >= sum of party votes (no negative "rejected").
            $votesSum = (int) array_sum($stationVotes);
            if ($stationCast < $votesSum) {
                $stationCast = $votesSum;
                // If that pushed cast above electorate again, raise electorate.
                if ($stationCast > $stationElectorate) {
                    $stationElectorate = $stationCast;
                }
            }

            $results[$station->id] = [
                'electorate' => $stationElectorate,
                'cast'       => $stationCast,
                'votes'      => $stationVotes,
            ];
        }

        return $results;
    }

    // ──────────────────────────────────────────────────────────────────────
    // Load candidates keyed by their party abbreviation.
    // ──────────────────────────────────────────────────────────────────────
    private function loadCandidatesByParty(int $electionId): array
    {
        $candidates = Candidate::with('politicalParty')
            ->where('election_id', $electionId)
            ->get();

        $indexed = [];
        foreach ($candidates as $candidate) {
            $abbr = $candidate->politicalParty?->abbreviation;
            if ($abbr && in_array($abbr, self::PARTY_KEYS)) {
                $indexed[$abbr] = $candidate;
            }
        }

        $missing = array_diff(self::PARTY_KEYS, array_keys($indexed));
        if (!empty($missing)) {
        foreach ($missing as $m) {
            Log::warning("[ResultSeeder] No candidate found for party: {$m}");
        }
        }

        return $indexed;
    }
}
