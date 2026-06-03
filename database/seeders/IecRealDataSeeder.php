<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * IecRealDataSeeder
 *
 * Replaces synthetic placeholder hierarchy + polling-station data (election 1)
 * with the official IEC Gambia register extracted from Wards.pdf.
 *
 * Source: database/seeders/data/wards.json  (1,554 rows: region, constituency,
 *         ward, ps_code, station — parsed from the IEC Wards PDF register)
 *
 * Safe to re-run: deletes and rebuilds only election_id = 1.
 */
class IecRealDataSeeder extends Seeder
{
    private const ELECTION_ID = 1;

    // Approver user IDs sourced from existing seed users (roles match).
    private const REGION_APPROVER_IDS    = [3, 4, 5, 6, 7, 8, 9];
    private const CONST_APPROVER_START   = 10;   // user IDs 10–62
    private const WARD_APPROVER_START    = 63;   // user IDs 63–183
    private const OFFICER_START          = 183;  // user IDs 183+

    // Region geographic centres + spread for station coordinate offsets.
    private const REGION_GEO = [
        'BANJUL'      => ['lat' =>  13.4531, 'lng' => -16.5795, 'spread' => 0.02],
        'KANIFING'    => ['lat' =>  13.4249, 'lng' => -16.6683, 'spread' => 0.04],
        'BRIKAMA'     => ['lat' =>  13.2827, 'lng' => -16.6637, 'spread' => 0.20],
        'KEREWAN'     => ['lat' =>  13.5774, 'lng' => -16.0833, 'spread' => 0.30],
        'MANSAKONKO'  => ['lat' =>  13.4167, 'lng' => -15.5667, 'spread' => 0.25],
        'JANJANBUREH' => ['lat' =>  13.5333, 'lng' => -14.7667, 'spread' => 0.30],
        'BASSE'       => ['lat' =>  13.3167, 'lng' => -14.2167, 'spread' => 0.35],
    ];

    // Approximate real 2021 presidential vote shares per region (for synthetic results).
    private const REGION_VOTE_SHARES = [
        // [NPP, UDP, GDC, PDOIS, IND/Faal, NUP]
        'BANJUL'      => [0.36, 0.32, 0.12, 0.08, 0.07, 0.05],
        'KANIFING'    => [0.33, 0.40, 0.10, 0.08, 0.06, 0.03],
        'BRIKAMA'     => [0.51, 0.27, 0.11, 0.04, 0.05, 0.02],
        'KEREWAN'     => [0.56, 0.24, 0.09, 0.05, 0.04, 0.02],
        'MANSAKONKO'  => [0.55, 0.22, 0.12, 0.05, 0.04, 0.02],
        'JANJANBUREH' => [0.58, 0.21, 0.11, 0.05, 0.03, 0.02],
        'BASSE'       => [0.40, 0.20, 0.26, 0.06, 0.05, 0.03],  // GDC stronger in Upper River
    ];

    public function run(): void
    {
        $this->command->info('Loading IEC ward register from wards.json…');
        $dataPath = database_path('seeders/data/wards.json');
        $rows     = json_decode(file_get_contents($dataPath), true);
        // Deduplicate by ps_code (the source PDF contains one exact duplicate row).
        $seen = [];
        $rows = array_values(array_filter($rows, function ($r) use (&$seen) {
            if (isset($seen[$r['ps_code']])) return false;
            $seen[$r['ps_code']] = true;
            return true;
        }));
        $this->command->info('  → ' . count($rows) . ' rows loaded (after dedup).');

        $electionId = self::ELECTION_ID;
        $now        = Carbon::now()->toDateTimeString();

        // ── 1. Clear existing data for election 1 ─────────────────────────────
        // Delete in FK-safe order so the seeder is re-runnable on any driver
        // (PostgreSQL enforces FK RESTRICT; SQLite is lenient but also ordered).
        $this->command->info('Clearing existing election-1 data…');

        $resultIds  = DB::table('results')->where('election_id', $electionId)->pluck('id');
        $stationIds = DB::table('polling_stations')->where('election_id', $electionId)->pluck('id');
        $hierIds    = DB::table('administrative_hierarchy')->where('election_id', $electionId)->pluck('id');

        // Leaf tables first (child → parent order)
        if ($resultIds->isNotEmpty()) {
            DB::table('result_candidate_votes')->whereIn('result_id', $resultIds)->delete();
            DB::table('party_acceptances')->whereIn('result_id', $resultIds)->delete();
            DB::table('result_certifications')->whereIn('result_id', $resultIds)->delete();
        }
        DB::table('results')->where('election_id', $electionId)->delete();

        if ($stationIds->isNotEmpty()) {
            DB::table('aggregated_results')->whereIn('polling_station_id', $stationIds)->delete();
        }
        DB::table('polling_stations')->where('election_id', $electionId)->delete();

        if ($hierIds->isNotEmpty()) {
            DB::table('aggregated_results')->whereIn('hierarchy_node_id', $hierIds)->delete();
            DB::table('result_certifications')->whereIn('hierarchy_node_id', $hierIds)->delete();
        }
        DB::table('administrative_hierarchy')->where('election_id', $electionId)->delete();

        $this->command->info('  → Done.');

        // ── 2. Build hierarchical index from flat rows ──────────────────────────
        // regions → constituencies → wards → [stations…]
        $hierarchy = [];
        foreach ($rows as $row) {
            $region = $row['region'];
            $con    = $row['constituency'];
            $ward   = $row['ward'];
            $hierarchy[$region][$con][$ward][] = [
                'ps_code' => $row['ps_code'],
                'station' => $row['station'],
            ];
        }

        // ── 3. Insert admin_areas ──────────────────────────────────────────────
        $this->command->info('Inserting regions, constituencies, wards, stations…');

        $regionIdx     = 0;
        $conIdx        = 0;
        $wardIdx       = 0;
        $officerOffset = 0;
        $stationRows   = [];    // collected for batch insert
        $usedCodes     = [];    // track used codes to avoid UNIQUE violations

        foreach ($hierarchy as $regionName => $constituencies) {
            $geo        = self::REGION_GEO[$regionName] ?? ['lat' => 13.45, 'lng' => -15.3, 'spread' => 0.20];
            $approverId = self::REGION_APPROVER_IDS[$regionIdx % count(self::REGION_APPROVER_IDS)];

            $regionId = DB::table('administrative_hierarchy')->insertGetId([
                'election_id'         => $electionId,
                'level'               => 'admin_area',
                'parent_id'           => null,
                'name'                => Str::title($regionName),
                'code'                => $this->uniqueCode($this->regionCode($regionName), $usedCodes),
                'slug'                => Str::slug($regionName),
                'path'                => null,          // filled after insert
                'depth'               => 0,
                'center_latitude'     => $geo['lat'],
                'center_longitude'    => $geo['lng'],
                'registered_voters'   => 0,
                'assigned_approver_id'=> $approverId,
                'is_active'           => 1,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);
            DB::table('administrative_hierarchy')
                ->where('id', $regionId)
                ->update(['path' => "/{$regionId}/"]);

            // ── 4. Insert constituencies ───────────────────────────────────────
            foreach ($constituencies as $conName => $wards) {
                $conApproverId = self::CONST_APPROVER_START + ($conIdx % 53);
                $conId = DB::table('administrative_hierarchy')->insertGetId([
                    'election_id'         => $electionId,
                    'level'               => 'constituency',
                    'parent_id'           => $regionId,
                    'name'                => Str::title($conName),
                    'code'                => $this->uniqueCode($this->conCode($regionName, $conName), $usedCodes),
                    'slug'                => Str::slug($conName),
                    'path'                => null,
                    'depth'               => 1,
                    'center_latitude'     => $geo['lat'] + (($conIdx % 7 - 3) * 0.02),
                    'center_longitude'    => $geo['lng'] + (($conIdx % 5 - 2) * 0.02),
                    'registered_voters'   => 0,
                    'assigned_approver_id'=> $conApproverId,
                    'is_active'           => 1,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ]);
                DB::table('administrative_hierarchy')
                    ->where('id', $conId)
                    ->update(['path' => "/{$regionId}/{$conId}/"]);

                // ── 5. Insert wards ────────────────────────────────────────────
                foreach ($wards as $wardName => $stations) {
                    $wardApproverId = self::WARD_APPROVER_START + ($wardIdx % 120);
                    $wardId = DB::table('administrative_hierarchy')->insertGetId([
                        'election_id'         => $electionId,
                        'level'               => 'ward',
                        'parent_id'           => $conId,
                        'name'                => Str::title($wardName),
                        'code'                => $this->uniqueCode($this->wardCode($regionName, $conName, $wardName), $usedCodes),
                        'slug'                => Str::slug($wardName),
                        'path'                => null,
                        'depth'               => 2,
                        'center_latitude'     => $geo['lat'] + $this->smallOffset($wardIdx),
                        'center_longitude'    => $geo['lng'] + $this->smallOffset($wardIdx + 1000),
                        'registered_voters'   => 0,
                        'assigned_approver_id'=> $wardApproverId,
                        'is_active'           => 1,
                        'created_at'          => $now,
                        'updated_at'          => $now,
                    ]);
                    DB::table('administrative_hierarchy')
                        ->where('id', $wardId)
                        ->update(['path' => "/{$regionId}/{$conId}/{$wardId}/"]);

                    // ── 6. Collect polling stations ────────────────────────────
                    foreach ($stations as $station) {
                        $officerId = self::OFFICER_START + ($officerOffset % 1555);
                        $stationRows[] = [
                            'election_id'         => $electionId,
                            'ward_id'             => $wardId,
                            'code'                => $station['ps_code'],
                            'name'                => $this->titleCase($station['station']),
                            'address'             => $this->titleCase($station['station']) . ', ' . Str::title($wardName),
                            'latitude'            => round($geo['lat'] + $this->smallOffset($officerOffset), 6),
                            'longitude'           => round($geo['lng'] + $this->smallOffset($officerOffset + 500), 6),
                            'registered_voters'   => rand(300, 1200),
                            'assigned_officer_id' => self::OFFICER_START + ($officerOffset % 1554),
                            'is_active'           => 1,
                            'is_test_station'     => 0,
                            'station_photo_path'  => null,
                            'created_at'          => $now,
                            'updated_at'          => $now,
                        ];
                        $officerOffset++;
                    }

                    $wardIdx++;
                }
                $conIdx++;
            }
            $regionIdx++;
        }

        // ── 7. Batch-insert polling stations ──────────────────────────────────
        $this->command->info('  Inserting ' . count($stationRows) . ' polling stations…');
        foreach (array_chunk($stationRows, 200) as $chunk) {
            DB::table('polling_stations')->insert($chunk);
        }

        // ── 8. Seed synthetic results + candidate votes ────────────────────────
        $this->command->info('Seeding synthetic results…');
        $this->seedResults($hierarchy, $now);

        $this->command->info('');
        $this->command->info('✓  IEC real data seeder complete.');
        $this->command->info('   Regions: 7 | Constituencies: 53 | Wards: ' . $wardIdx . ' | Stations: ' . count($stationRows));
    }

    // ── Synthetic results generator ────────────────────────────────────────────
    private function seedResults(array $hierarchy, string $now): void
    {
        $electionId  = self::ELECTION_ID;
        $candidates  = DB::table('candidates')->where('election_id', $electionId)->orderBy('id')->get();
        $stations    = DB::table('polling_stations')->where('election_id', $electionId)->get(['id', 'ward_id', 'registered_voters', 'code']);

        // Build ward → region lookup via the hierarchy we just inserted.
        $wardToRegion = [];
        $wardRows = DB::table('administrative_hierarchy as w')
            ->join('administrative_hierarchy as c', 'c.id', '=', 'w.parent_id')
            ->join('administrative_hierarchy as r', 'r.id', '=', 'c.parent_id')
            ->where('w.election_id', $electionId)->where('w.level', 'ward')
            ->select('w.id as ward_id', 'r.name as region_name')
            ->get();
        foreach ($wardRows as $wr) {
            $wardToRegion[$wr->ward_id] = strtoupper($wr->region_name);
        }

        // Certification status distribution (mirrors old seeder roughly).
        $certStatuses = [
            'nationally_certified' => 45,
            'admin_area_certified' => 20,
            'pending_national'     => 20,
            'pending_admin_area'   => 15,
        ];
        $statusPool = [];
        foreach ($certStatuses as $status => $weight) {
            for ($i = 0; $i < $weight; $i++) { $statusPool[] = $status; }
        }

        $resultRows = [];
        $rcvRows    = [];
        $uuid       = 1;

        foreach ($stations as $station) {
            $regionName = $wardToRegion[$station->ward_id] ?? 'BRIKAMA';
            // Trim trailing parenthetical e.g. "Kanifing" from "Kanifing"
            $regionKey  = strtoupper(explode(' ', $regionName)[0]);
            // Map title-cased region name back to key
            $geoKey = $this->titleToRegionKey($regionName);
            $shares = self::REGION_VOTE_SHARES[$geoKey] ?? self::REGION_VOTE_SHARES['BRIKAMA'];

            $registered = $station->registered_voters ?: rand(300, 1200);
            $cast       = (int) round($registered * (0.72 + (crc32($station->code) % 100) / 500));
            $rejected   = (int) round($cast * 0.003);
            $valid      = $cast - $rejected;

            $status = $statusPool[crc32($station->code) % count($statusPool)];
            $certAt = in_array($status, ['nationally_certified', 'admin_area_certified'])
                ? Carbon::parse('2025-12-04')->addHours(rand(12, 36))->toDateTimeString()
                : null;

            $submittedById = self::OFFICER_START + (($uuid - 1) * 2 % 1554);

            $resultId = DB::table('results')->insertGetId([
                'polling_station_id'    => $station->id,
                'election_id'           => $electionId,
                'submission_uuid'       => Str::uuid(),
                'total_registered_voters' => $registered,
                'total_votes_cast'      => $cast,
                'valid_votes'           => $valid,
                'rejected_votes'        => $rejected,
                'disputed_votes'        => 0,
                'certification_status'  => $status,
                'rejection_count'       => 0,
                'submitted_offline'     => 0,
                'submitted_by'          => $submittedById,
                'submitted_at'          => Carbon::parse('2025-12-04 17:00:00')->addSeconds($uuid * 17),
                'version'               => 1,
                'nationally_certified_at' => $certAt,
                'created_at'            => $now,
                'updated_at'            => $now,
                'user_id'               => $submittedById,
            ]);

            // Distribute valid votes among candidates using regional shares + noise.
            $remaining   = $valid;
            $candCount   = $candidates->count();
            $shareNoise  = function (float $base) use ($station): float {
                $noise = (crc32($station->code . 'n') % 200 - 100) / 2000;
                return max(0.01, $base + $noise);
            };

            $noisyShares = array_map($shareNoise, $shares);
            // Pad / trim to candidate count
            while (count($noisyShares) < $candCount) { $noisyShares[] = 0.01; }
            $noisyShares = array_slice($noisyShares, 0, $candCount);
            $total       = array_sum($noisyShares);
            $noisyShares = array_map(fn($s) => $s / $total, $noisyShares);

            foreach ($candidates as $idx => $candidate) {
                $isLast  = ($idx === $candCount - 1);
                $votes   = $isLast ? $remaining : (int) round($valid * $noisyShares[$idx]);
                $votes   = min($votes, $remaining);
                $remaining -= $votes;

                $rcvRows[] = [
                    'result_id'    => $resultId,
                    'candidate_id' => $candidate->id,
                    'election_id'  => $electionId,
                    'votes'        => max(0, $votes),
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
            }

            // Batch-insert rcv every 500 results to stay within SQLite limits.
            if (count($rcvRows) >= 500 * $candCount) {
                DB::table('result_candidate_votes')->insert($rcvRows);
                $rcvRows = [];
            }

            $uuid++;
        }

        if (!empty($rcvRows)) {
            DB::table('result_candidate_votes')->insert($rcvRows);
        }

        $this->command->info('  → ' . ($uuid - 1) . ' results + ' . ($uuid - 1) * $candidates->count() . ' candidate votes inserted.');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────
    private function regionCode(string $name): string
    {
        return match ($name) {
            'BANJUL'      => 'BJL',
            'KANIFING'    => 'KMC',
            'BRIKAMA'     => 'WCR',
            'KEREWAN'     => 'NBR',
            'MANSAKONKO'  => 'LRR',
            'JANJANBUREH' => 'CRR',
            'BASSE'       => 'URR',
            default       => strtoupper(substr($name, 0, 3)),
        };
    }

    private function conCode(string $region, string $con): string
    {
        // Use first 3 chars of each word to get a unique-enough code.
        $words = preg_split('/\s+/', strtoupper($con));
        $abbr  = implode('', array_map(fn($w) => substr(preg_replace('/[^A-Z]/', '', $w), 0, 3), $words));
        return $this->regionCode($region) . '-' . substr($abbr, 0, 8);
    }

    private function wardCode(string $region, string $con, string $ward): string
    {
        $words = preg_split('/\s+/', strtoupper($ward));
        $abbr  = implode('', array_map(fn($w) => substr(preg_replace('/[^A-Z]/', '', $w), 0, 2), $words));
        return $this->conCode($region, $con) . '-' . substr($abbr, 0, 6);
    }

    /** Make a code unique within the given used-codes set by appending a suffix. */
    private function uniqueCode(string $base, array &$used): string
    {
        $code = $base;
        $n    = 2;
        while (isset($used[$code])) {
            $code = substr($base, 0, 14) . $n;
            $n++;
        }
        $used[$code] = true;
        return $code;
    }

    /** Deterministic small float offset from an integer seed. */
    private function smallOffset(int $seed): float
    {
        return round((($seed * 1103515245 + 12345) & 0x7fffffff) % 10000 / 10000 * 0.08 - 0.04, 6);
    }

    /** Convert abbreviated station names to Title Case, preserving abbreviations. */
    private function titleCase(string $name): string
    {
        $name = strtolower($name);
        $name = preg_replace_callback('/\b(\w+)\b/', function ($m) {
            $w = $m[1];
            // Keep known abbreviations uppercase
            if (in_array(strtoupper($w), ['PRI', 'SEC', 'JNR', 'SNR', 'SCH', 'GOVT', 'VET', 'JUN', 'SEN', 'UPP'])) {
                return strtoupper($w) . '.';
            }
            return ucfirst($w);
        }, $name);
        // Clean double dots
        return preg_replace('/\.{2,}/', '.', $name);
    }

    private function titleToRegionKey(string $titleName): string
    {
        $name = strtoupper($titleName);
        foreach (array_keys(self::REGION_VOTE_SHARES) as $key) {
            if (str_starts_with($name, $key)) return $key;
        }
        // Fallback mappings for title-cased names
        $map = [
            'BANJUL'  => 'BANJUL',
            'KANIFING'=> 'KANIFING',
            'BRIKAMA' => 'BRIKAMA',
            'KEREWAN' => 'KEREWAN',
            'MANSAKONKO' => 'MANSAKONKO',
            'JANJANBUREH'=> 'JANJANBUREH',
            'BASSE'   => 'BASSE',
        ];
        foreach ($map as $needle => $key) {
            if (stripos($name, $needle) !== false) return $key;
        }
        return 'BRIKAMA';
    }
}
