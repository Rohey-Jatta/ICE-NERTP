<?php

namespace Database\Seeders;

use App\Models\AdministrativeHierarchy;
use App\Models\Election;
use App\Models\PollingStation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds all Banjul Municipality polling stations with accurate GPS coordinates.
 *
 * Source: Wards (1).pdf — Region: BANJUL
 * Constituencies:  BANJUL SOUTH (101xxx) | BANJUL CENTRAL (102xxx) | BANJUL NORTH (103xxx)
 *
 * Micro-offset strategy: each subsequent stream at the same venue receives a
 * ~2-metre directional nudge so markers never overlap on a map.
 */
class BanjulPollingStationSeeder extends Seeder
{
    /** ~2 metres in decimal degrees at Banjul's latitude (≈13.45 °N) */
    private const OFFSET = 0.000018;

    private int $electionId;
    private int $created = 0;

    // ─────────────────────────────────────────────────────────────────────────
    public function run(): void
    {
        $this->electionId = Election::where('slug', 'gambia-2021-presidential')->value('id')
            ?? throw new \RuntimeException('[BanjulSeeder] Election "gambia-2021-presidential" not found.');

        $this->command->info('▶  Seeding Banjul Municipality polling stations...');

        $banjulArea = $this->node('admin_area', 'BANJUL', 'BJL', null, 'admin-area-approver');

        foreach ($this->schema() as $cons) {
            $consNode = $this->node('constituency', $cons['name'], $cons['code'], $banjulArea->id, 'constituency-approver');

            foreach ($cons['wards'] as $w) {
                $wardNode = $this->node('ward', $w['name'], $w['code'], $consNode->id, 'ward-approver');

                foreach ($w['stations'] as $s) {
                    $this->plant($wardNode->id, $s);
                }
            }
        }

        $this->command->info("✅  Banjul done — {$this->created} station records created/verified.");
    }

    // ─── Core helpers ─────────────────────────────────────────────────────────

    private function plant(int $wardId, array $s): void
    {
        foreach ($s['ps_codes'] as $idx => $code) {
            [$lat, $lng] = $this->nudge($s['lat'], $s['lng'], $idx);

            $officer = $this->officer($code);

            PollingStation::firstOrCreate(
                ['code' => $code],
                [
                    'election_id'         => $this->electionId,
                    'ward_id'             => $wardId,
                    'name'                => $s['name'],
                    'latitude'            => round($lat, 7),
                    'longitude'           => round($lng, 7),
                    'registered_voters'   => $s['voters'] ?? rand(280, 650),
                    'assigned_officer_id' => $officer->id,
                    'is_active'           => true,
                ]
            );

            $this->created++;
        }
    }

    /**
     * 8-direction compass rose offset; magnitude increases for streams > 8.
     *  0=base, 1=N, 2=E, 3=S, 4=W, 5=NE, 6=SE, 7=SW, (8 wraps back to NW with ×2)
     */
    private function nudge(float $lat, float $lng, int $i): array
    {
        if ($i === 0) return [$lat, $lng];

        $mag  = self::OFFSET * (intdiv($i - 1, 8) + 1);
        $diag = $mag * 0.707;

        return match ($i % 8) {
            1 => [$lat + $mag,  $lng        ],
            2 => [$lat,         $lng + $mag ],
            3 => [$lat - $mag,  $lng        ],
            4 => [$lat,         $lng - $mag ],
            5 => [$lat + $diag, $lng + $diag],
            6 => [$lat - $diag, $lng + $diag],
            7 => [$lat - $diag, $lng - $diag],
            0 => [$lat + $diag, $lng - $diag],
        };
    }

    private function node(string $level, string $name, string $code, ?int $parentId, string $role): AdministrativeHierarchy
    {
        $node = AdministrativeHierarchy::firstOrCreate(
            ['election_id' => $this->electionId, 'level' => $level, 'code' => $code],
            ['parent_id' => $parentId, 'name' => $name, 'slug' => Str::slug("{$name}-bjl")]
        );

        if (!$node->assigned_approver_id) {
            $email    = Str::slug($name) . ".{$level}@banjul.iec.local";
            $approver = User::firstOrCreate(
                ['email' => $email],
                ['name' => "{$name} Approver", 'password' => bcrypt('password123'), 'status' => 'active']
            );
            if (!$approver->hasRole($role)) $approver->assignRole($role);
            $node->update(['assigned_approver_id' => $approver->id]);
        }

        return $node;
    }

    private function officer(string $psCode): User
    {
        $email   = "officer.{$psCode}@banjul.iec.local";
        $officer = User::firstOrCreate(
            ['email' => $email],
            ['name' => "Officer {$psCode}", 'password' => bcrypt('password123'), 'status' => 'active']
        );
        if (!$officer->hasRole('polling-officer')) $officer->assignRole('polling-officer');
        return $officer;
    }

    // ─── Station schema ────────────────────────────────────────────────────────

    private function schema(): array
    {
        return [

            /* ══════════════════════════════════════════════════════════════
             *  BANJUL SOUTH   (PS codes 101xxx)
             * ══════════════════════════════════════════════════════════════ */
            [
                'name'  => 'BANJUL SOUTH',
                'code'  => 'BJL-BS',
                'wards' => [

                    [
                        'name'     => 'JOLLOF TOWN',
                        'code'     => 'BJL-BS-JT',
                        'stations' => [
                            [
                                'name'     => 'WESLEY PRI.SCH.',
                                'lat'      => 13.44750,
                                'lng'      => -16.57720,
                                'voters'   => 420,
                                'ps_codes' => ['101021', '101022'],
                            ],
                            [
                                'name'     => 'LASSO WHARF MARKET',
                                'lat'      => 13.45000,
                                'lng'      => -16.58000,
                                'voters'   => 380,
                                'ps_codes' => ['101031', '101032'],
                            ],
                        ],
                    ],

                    [
                        'name'     => 'PORTUGUESE TOWN',
                        'code'     => 'BJL-BS-PT',
                        'stations' => [
                            // Wesley Annex is in the same compound as Wesley Prim; base coords
                            // shifted ~9 m north so the two venue markers are distinguishable.
                            [
                                'name'     => 'METHODIST PRI. SCH.( WESLEY ANNEX)',
                                'lat'      => 13.44758,
                                'lng'      => -16.57722,
                                'voters'   => 340,
                                'ps_codes' => ['101011', '101012'],
                            ],
                            [
                                'name'     => 'ST. AUG. JNR. SEC. SCH.',
                                'lat'      => 13.45110,
                                'lng'      => -16.57460,
                                'voters'   => 290,
                                'ps_codes' => ['101041'],
                            ],
                            [
                                'name'     => 'MUHAMMADAN PRI. SCH.',
                                'lat'      => 13.45040,
                                'lng'      => -16.57500,
                                'voters'   => 410,
                                'ps_codes' => ['101051', '101052'],
                            ],
                        ],
                    ],

                    [
                        'name'     => 'HALF DIE',
                        'code'     => 'BJL-BS-HD',
                        'stations' => [
                            [
                                'name'     => 'BANJUL MINI STADIUM',
                                'lat'      => 13.44780,
                                'lng'      => -16.58220,
                                'voters'   => 630,
                                'ps_codes' => ['101061', '101062', '101063'],
                            ],
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════
             *  BANJUL CENTRAL   (PS codes 102xxx)
             * ══════════════════════════════════════════════════════════════ */
            [
                'name'  => 'BANJUL CENTRAL',
                'code'  => 'BJL-BC',
                'wards' => [

                    [
                        'name'     => 'SOLDIER TOWN',
                        'code'     => 'BJL-BC-ST',
                        'stations' => [
                            [
                                'name'     => 'BANJUL. CITY COUNCIL',
                                'lat'      => 13.45390,
                                'lng'      => -16.57610,
                                'voters'   => 260,
                                'ps_codes' => ['102011'],
                            ],
                            [
                                'name'     => '22ND JULY SQUARE .',
                                'lat'      => 13.44940,
                                'lng'      => -16.57750,
                                'voters'   => 820,
                                'ps_codes' => ['102041', '102042', '102043', '102044'],
                            ],
                        ],
                    ],

                    [
                        'name'     => 'NEW TOWN WEST',
                        'code'     => 'BJL-BC-NTW',
                        'stations' => [
                            [
                                'name'     => 'WELLESLEY MACDONALD ST. JUNC.',
                                'lat'      => 13.45261,
                                'lng'      => -16.57715,
                                'voters'   => 460,
                                'ps_codes' => ['102021', '102022'],
                            ],
                            [
                                'name'     => 'ODEON CINEMA',
                                'lat'      => 13.45180,
                                'lng'      => -16.58010,
                                'voters'   => 310,
                                'ps_codes' => ['102031'],
                            ],
                            [
                                'name'     => 'BETHEL CHURCH',
                                'lat'      => 13.44910,
                                'lng'      => -16.57490,
                                'voters'   => 280,
                                'ps_codes' => ['102061'],
                            ],
                        ],
                    ],

                    [
                        'name'     => 'NEW TOWN EAST',
                        'code'     => 'BJL-BC-NTE',
                        'stations' => [
                            [
                                'name'     => 'SAM JACK TERRACE',
                                'lat'      => 13.45420,
                                'lng'      => -16.57940,
                                'voters'   => 840,
                                'ps_codes' => ['102051', '102052', '102053', '102054'],
                            ],
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════
             *  BANJUL NORTH   (PS codes 103xxx)
             * ══════════════════════════════════════════════════════════════ */
            [
                'name'  => 'BANJUL NORTH',
                'code'  => 'BJL-BN',
                'wards' => [

                    [
                        'name'     => 'BOX BAR',
                        'code'     => 'BJL-BN-BB',
                        'stations' => [
                            [
                                'name'     => 'GAMBIA SEN. SEC. SCH. (NEXT TO ARCH 22)',
                                'lat'      => 13.46010,
                                'lng'      => -16.58190,
                                'voters'   => 620,
                                'ps_codes' => ['103011', '103012', '103013'],
                            ],
                        ],
                    ],

                    [
                        'name'     => 'CAMPAMA',
                        'code'     => 'BJL-BN-CA',
                        'stations' => [
                            [
                                'name'     => 'CAMPAMA PRI. SCH.',
                                'lat'      => 13.45680,
                                'lng'      => -16.58720,
                                'voters'   => 840,
                                'ps_codes' => ['103021', '103022', '103023', '103024'],
                            ],
                            [
                                'name'     => 'ST. JOSEPH SEN. SEC. SCH.',
                                'lat'      => 13.44820,
                                'lng'      => -16.57960,
                                'voters'   => 310,
                                'ps_codes' => ['103031'],
                            ],
                        ],
                    ],

                    [
                        'name'     => 'CRAB ISLAND',
                        'code'     => 'BJL-BN-CI',
                        'stations' => [
                            [
                                'name'     => 'POLICE BARRACKS',
                                'lat'      => 13.45140,
                                'lng'      => -16.57810,
                                'voters'   => 420,
                                'ps_codes' => ['103041', '103042'],
                            ],
                            [
                                'name'     => 'CRAB ISLAND JUN. SCH.',
                                'lat'      => 13.45920,
                                'lng'      => -16.58910,
                                'voters'   => 290,
                                'ps_codes' => ['103051'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
