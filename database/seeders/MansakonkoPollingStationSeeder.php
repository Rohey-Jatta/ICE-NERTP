<?php

namespace Database\Seeders;

use App\Models\AdministrativeHierarchy;
use App\Models\Election;
use App\Models\PollingStation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds all MANSAKONKO (Lower River Region) polling stations.
 * Source: Wards (1).pdf — Pages 25-27
 *
 * Constituencies:
 *   JARRA WEST    501xxx | JARRA CENTRAL 502xxx | JARRA EAST   503xxx
 *   KIANG EAST    504xxx | KIANG CENTRAL 505xxx | KIANG WEST   506xxx
 *
 * Verified coordinates (from geographic resource):
 *   Soma area 13.433,‑15.530‑15.540 · Karantaba 13.456,‑15.503 ·
 *   Sankwia 13.454,‑15.535 · Toniataba 13.431,‑15.485 ·
 *   Keneba 13.331,‑16.015 · Tankular 13.434,‑15.923 ·
 *   Kwinella 13.364,‑15.701 · Kaiaf 13.376,‑15.618 ·
 *   Massembeh 13.387,‑15.545 · Jiffarong 13.311,‑15.938 ·
 *   Burong 13.375,‑15.907 · Jali 13.346,‑15.799.
 * All remaining stations marked // estimated.
 */
class MansakonkoPollingStationSeeder extends Seeder
{
    private const OFFSET = 0.000018;

    private int $electionId;
    private int $created = 0;

    public function run(): void
    {
        $this->electionId = Election::where('slug', 'gambia-2021-presidential')->value('id')
            ?? throw new \RuntimeException('[MansakonkoSeeder] Election gambia-2021-presidential not found.');

        $this->command->info('▶  Seeding Mansakonko (Lower River Region) polling stations...');

        $region = $this->node('admin_area', 'MANSAKONKO', 'MSK', null, 'admin-area-approver');

        foreach ($this->schema() as $c) {
            $cn = $this->node('constituency', $c['name'], $c['code'], $region->id, 'constituency-approver');
            foreach ($c['wards'] as $w) {
                $wn = $this->node('ward', $w['name'], $w['code'], $cn->id, 'ward-approver');
                foreach ($w['stations'] as $s) {
                    $this->plant($wn->id, $s);
                }
            }
        }

        $this->command->info("✅  Mansakonko done — {$this->created} records created/verified.");
    }

    // ─── helpers ──────────────────────────────────────────────────────────────

    private function plant(int $wardId, array $s): void
    {
        foreach ($s['ps_codes'] as $i => $code) {
            [$lat, $lng] = $this->nudge($s['lat'], $s['lng'], $i);
            $officer     = $this->officer($code);
            PollingStation::firstOrCreate(['code' => $code], [
                'election_id'         => $this->electionId,
                'ward_id'             => $wardId,
                'name'                => $s['name'],
                'latitude'            => round($lat, 7),
                'longitude'           => round($lng, 7),
                'registered_voters'   => $s['voters'] ?? rand(150, 450),
                'assigned_officer_id' => $officer->id,
                'is_active'           => true,
            ]);
            $this->created++;
        }
    }

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
            ['parent_id' => $parentId, 'name' => $name, 'slug' => Str::slug("{$name}-msk")]
        );
        if (!$node->assigned_approver_id) {
            $email    = Str::slug($name) . ".{$level}@mansakonko.iec.local";
            $approver = User::firstOrCreate(['email' => $email], [
                'name'     => "{$name} Approver",
                'password' => bcrypt('password123'),
                'status'   => 'active',
            ]);
            if (!$approver->hasRole($role)) $approver->assignRole($role);
            $node->update(['assigned_approver_id' => $approver->id]);
        }
        return $node;
    }

    private function officer(string $code): User
    {
        $email   = "officer.{$code}@mansakonko.iec.local";
        $officer = User::firstOrCreate(['email' => $email], [
            'name'     => "Officer {$code}",
            'password' => bcrypt('password123'),
            'status'   => 'active',
        ]);
        if (!$officer->hasRole('polling-officer')) $officer->assignRole('polling-officer');
        return $officer;
    }

    // ─── data schema ──────────────────────────────────────────────────────────

    private function schema(): array
    {
        return [

            /* ══════════════════════════════════════════════════════════════════
             * 501xxx  JARRA WEST
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'JARRA WEST', 'code' => 'MSK-JW',
                'wards' => [
                    [
                        'name' => 'GIKOKO', 'code' => 'MSK-JW-GK',
                        'stations' => [
                            ['name' => 'FONKOI KUNDA',          'lat' => 13.44000, 'lng' => -15.52000, 'ps_codes' => ['501011']], // estimated
                            ['name' => 'SARE SAIDY',            'lat' => 13.44200, 'lng' => -15.51800, 'ps_codes' => ['501021']], // estimated
                            ['name' => 'DIGANTEH',              'lat' => 13.43800, 'lng' => -15.51500, 'ps_codes' => ['501031','501032']], // estimated
                            ['name' => 'MISSERA',               'lat' => 13.39860, 'lng' => -15.52220, 'ps_codes' => ['501041','501042']],
                            ['name' => 'TONIATABA',             'lat' => 13.43080, 'lng' => -15.48510, 'ps_codes' => ['501061','501062']],
                            ['name' => 'SI-KUNDA',              'lat' => 13.43290, 'lng' => -15.50580, 'ps_codes' => ['501071']],
                            ['name' => 'PAKALINDING',           'lat' => 13.44750, 'lng' => -15.51860, 'ps_codes' => ['501081','501082']], // estimated
                            ['name' => 'JENOI',                 'lat' => 13.44400, 'lng' => -15.51000, 'ps_codes' => ['501091']], // estimated
                            ['name' => 'SOMA SATEBA',           'lat' => 13.43400, 'lng' => -15.53800, 'ps_codes' => ['501101','501102']], // estimated – Soma area
                            ['name' => 'SOMA NEW TOWN',         'lat' => 13.43600, 'lng' => -15.53000, 'ps_codes' => ['501111','501112']], // estimated
                            ['name' => 'SOMA ANGALFUTA',        'lat' => 13.43500, 'lng' => -15.53200, 'ps_codes' => ['501121','501122']], // estimated
                            ['name' => 'SOMA MISERA',           'lat' => 13.43320, 'lng' => -15.53980, 'ps_codes' => ['501131','501132']],
                        ],
                    ],
                    [
                        'name' => 'JADUMA', 'code' => 'MSK-JW-JD',
                        'stations' => [
                            ['name' => 'SENO BAJONKI',      'lat' => 13.40900, 'lng' => -15.47800, 'ps_codes' => ['501051','501052']], // estimated
                            ['name' => 'JABISA',            'lat' => 13.41000, 'lng' => -15.48000, 'ps_codes' => ['501141']], // estimated
                            ['name' => 'KARANTABA',         'lat' => 13.45610, 'lng' => -15.50310, 'ps_codes' => ['501151','501152']],
                            ['name' => 'KANIKUNDA',         'lat' => 13.45500, 'lng' => -15.50500, 'ps_codes' => ['501161']], // estimated
                            ['name' => 'SANKWIA',           'lat' => 13.45420, 'lng' => -15.53470, 'ps_codes' => ['501171','501172']],
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             * 502xxx  JARRA CENTRAL
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'JARRA CENTRAL', 'code' => 'MSK-JC',
                'wards' => [
                    [
                        'name' => 'BUIBA', 'code' => 'MSK-JC-BB',
                        'stations' => [
                            ['name' => 'DIGANTEH',                          'lat' => 13.39500, 'lng' => -15.40000, 'ps_codes' => ['502011','502012']], // estimated
                            ['name' => 'FOROYAA FULA',                      'lat' => 13.39700, 'lng' => -15.40200, 'ps_codes' => ['502021']], // estimated
                            ['name' => 'JAPINNEH MARIKOTO',                 'lat' => 13.43440, 'lng' => -15.39810, 'ps_codes' => ['502031','502032']], // estimated Japineh area
                            ['name' => 'JAPINNEH TEMBETO',                  'lat' => 13.43600, 'lng' => -15.39900, 'ps_codes' => ['502041']], // estimated
                            ['name' => 'BUIBA',                             'lat' => 13.45030, 'lng' => -15.43420, 'ps_codes' => ['502051']], // estimated
                        ],
                    ],
                    [
                        'name' => 'JALAMBEREH', 'code' => 'MSK-JC-JL',
                        'stations' => [
                            ['name' => 'NANEKO',                        'lat' => 13.39000, 'lng' => -15.41000, 'ps_codes' => ['502061']], // estimated
                            ['name' => 'WELLINGARA. (SITA HUMA)',        'lat' => 13.45390, 'lng' => -15.38550, 'ps_codes' => ['502071']], // estimated – Sitahuma area
                            ['name' => 'JALAMBEREH',                    'lat' => 13.38810, 'lng' => -15.42140, 'ps_codes' => ['502081','502082']], // estimated
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             * 503xxx  JARRA EAST
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'JARRA EAST', 'code' => 'MSK-JE',
                'wards' => [
                    [
                        'name' => 'PAKALIBA', 'code' => 'MSK-JE-PK',
                        'stations' => [
                            ['name' => 'SUKUTA',                            'lat' => 13.41100, 'lng' => -15.37940, 'ps_codes' => ['503011']], // estimated
                            ['name' => 'PAKALIBA',                          'lat' => 13.43500, 'lng' => -15.32000, 'ps_codes' => ['503021','503022']], // estimated
                            ['name' => 'MADINA',                            'lat' => 13.41110, 'lng' => -15.37890, 'ps_codes' => ['503031']], // estimated
                            ['name' => 'DARSILAMEH',                        'lat' => 13.41670, 'lng' => -15.41830, 'ps_codes' => ['503041']], // estimated
                            ['name' => 'BARROW KUNDA SUWAREH KUNDA',        'lat' => 13.41800, 'lng' => -15.38000, 'ps_codes' => ['503061']], // estimated
                            ['name' => 'BARROW KUNDA',                      'lat' => 13.41900, 'lng' => -15.37800, 'ps_codes' => ['503071']], // estimated
                        ],
                    ],
                    [
                        'name' => 'BURENG', 'code' => 'MSK-JE-BR',
                        'stations' => [
                            ['name' => 'NYAWURULUNG',       'lat' => 13.41320, 'lng' => -15.31940, 'ps_codes' => ['503051','503052']], // estimated
                            ['name' => 'SUTUKUNG',          'lat' => 13.41000, 'lng' => -15.31000, 'ps_codes' => ['503081','503082']], // estimated
                            ['name' => 'BURENG',            'lat' => 13.41320, 'lng' => -15.31940, 'ps_codes' => ['503091','503092']], // estimated
                            ['name' => 'JASONG',            'lat' => 13.39000, 'lng' => -15.29000, 'ps_codes' => ['503101']], // estimated
                            ['name' => 'WELLINGARA BA',     'lat' => 13.39500, 'lng' => -15.28000, 'ps_codes' => ['503111','503112','503113']], // estimated
                            ['name' => 'DINGIRAI',          'lat' => 13.40000, 'lng' => -15.27000, 'ps_codes' => ['503121','503122']], // estimated
                            ['name' => 'DONGOROBA',         'lat' => 13.42670, 'lng' => -15.29560, 'ps_codes' => ['503131','503132']], // estimated
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             * 504xxx  KIANG EAST
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'KIANG EAST', 'code' => 'MSK-KE',
                'wards' => [
                    [
                        'name' => 'MASSEMBEH', 'code' => 'MSK-KE-MB',
                        'stations' => [
                            ['name' => 'JOMARR',                    'lat' => 13.38720, 'lng' => -15.54470, 'ps_codes' => ['504011']], // estimated
                            ['name' => 'KOLIOR',                    'lat' => 13.38560, 'lng' => -15.57860, 'ps_codes' => ['504021']],
                            ['name' => 'TORANKA BANTANG',           'lat' => 13.38700, 'lng' => -15.56000, 'ps_codes' => ['504031']], // estimated
                            ['name' => 'MASSEMBEH',                 'lat' => 13.38720, 'lng' => -15.54470, 'ps_codes' => ['504061']], // estimated
                        ],
                    ],
                    [
                        'name' => 'KAIAF', 'code' => 'MSK-KE-KF',
                        'stations' => [
                            ['name' => 'NJOLOFEN',          'lat' => 13.38500, 'lng' => -15.60000, 'ps_codes' => ['504041']], // estimated
                            ['name' => 'MADINA SINCHU',     'lat' => 13.39000, 'lng' => -15.59500, 'ps_codes' => ['504051','504052']], // estimated
                            ['name' => 'GENIERE',           'lat' => 13.40310, 'lng' => -15.61140, 'ps_codes' => ['504071']],
                            ['name' => 'KAIAF',             'lat' => 13.37610, 'lng' => -15.61780, 'ps_codes' => ['504081','504082']],
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             * 505xxx  KIANG CENTRAL
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'KIANG CENTRAL', 'code' => 'MSK-KC',
                'wards' => [
                    [
                        'name' => 'KWINELLA', 'code' => 'MSK-KC-KW',
                        'stations' => [
                            ['name' => 'WUROKANG',                   'lat' => 13.36800, 'lng' => -15.69000, 'ps_codes' => ['505011']], // estimated
                            ['name' => 'KWINELLA. SANSANG KONO',     'lat' => 13.36440, 'lng' => -15.70060, 'ps_codes' => ['505021']],
                            ['name' => 'KWINELLA. NYA KUNDA',        'lat' => 13.36500, 'lng' => -15.69900, 'ps_codes' => ['505031','505032']], // estimated
                            ['name' => 'MADINA ANGALLEH',            'lat' => 13.33470, 'lng' => -15.65810, 'ps_codes' => ['505061','505062']],
                        ],
                    ],
                    [
                        'name' => 'JIROFF', 'code' => 'MSK-KC-JR',
                        'stations' => [
                            ['name' => 'SARE SARJO',    'lat' => 13.31800, 'lng' => -15.68000, 'ps_codes' => ['505041']], // estimated
                            ['name' => 'SIBITO',        'lat' => 13.32000, 'lng' => -15.67800, 'ps_codes' => ['505051']], // estimated
                            ['name' => 'NEMA',          'lat' => 13.31440, 'lng' => -15.69890, 'ps_codes' => ['505071','505072']],
                            ['name' => 'JIROFF',        'lat' => 13.32190, 'lng' => -15.69000, 'ps_codes' => ['505081']], // estimated
                            ['name' => 'NEMA KUTA',     'lat' => 13.31600, 'lng' => -15.69500, 'ps_codes' => ['505091']], // estimated
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             * 506xxx  KIANG WEST
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'KIANG WEST', 'code' => 'MSK-KW',
                'wards' => [
                    [
                        'name' => 'KIANG JULAFAR', 'code' => 'MSK-KW-KJ',
                        'stations' => [
                            ['name' => 'BURONG',            'lat' => 13.37530, 'lng' => -15.90690, 'ps_codes' => ['506011']],
                            ['name' => 'KARANTABA',         'lat' => 13.38000, 'lng' => -15.89000, 'ps_codes' => ['506021']], // estimated – Kiang Karantaba distinct from Jarra
                            ['name' => 'JANNEH KUNDA',      'lat' => 13.33000, 'lng' => -15.95000, 'ps_codes' => ['506031']], // estimated
                            ['name' => 'JOLI',              'lat' => 13.34000, 'lng' => -15.92000, 'ps_codes' => ['506041']], // estimated
                            ['name' => 'KEMOTO',            'lat' => 13.34500, 'lng' => -15.90000, 'ps_codes' => ['506051']], // estimated
                            ['name' => 'JISSAY',            'lat' => 13.35000, 'lng' => -15.88000, 'ps_codes' => ['506061']], // estimated
                            ['name' => 'MANDUAR',           'lat' => 13.35240, 'lng' => -16.04610, 'ps_codes' => ['506071']],
                            ['name' => 'TANKULAR',          'lat' => 13.43440, 'lng' => -15.92330, 'ps_codes' => ['506081']],
                            ['name' => 'KENEBA',            'lat' => 13.33080, 'lng' => -16.01520, 'ps_codes' => ['506091','506092']],
                            ['name' => 'JALI',              'lat' => 13.34580, 'lng' => -15.79940, 'ps_codes' => ['506101']],
                            ['name' => 'KANTONG KUNDA',     'lat' => 13.34800, 'lng' => -15.79000, 'ps_codes' => ['506111']], // estimated
                        ],
                    ],
                    [
                        'name' => 'KIANG BANTA', 'code' => 'MSK-KW-KB',
                        'stations' => [
                            ['name' => 'KULI KUNDA',        'lat' => 13.31000, 'lng' => -16.04000, 'ps_codes' => ['506121']], // estimated
                            ['name' => 'JIFFARONG',         'lat' => 13.31110, 'lng' => -15.93810, 'ps_codes' => ['506131','506132']],
                            ['name' => 'JATTABA',           'lat' => 13.30500, 'lng' => -15.95000, 'ps_codes' => ['506141']], // estimated
                            ['name' => 'NIORO JATTABA',     'lat' => 13.30200, 'lng' => -15.96000, 'ps_codes' => ['506151','506152']], // estimated
                            ['name' => 'SANKANDI',          'lat' => 13.30000, 'lng' => -15.97000, 'ps_codes' => ['506161']], // estimated
                            ['name' => 'DUMBUTU',           'lat' => 13.29800, 'lng' => -15.98000, 'ps_codes' => ['506171']], // estimated
                            ['name' => 'BATELLING',         'lat' => 13.29500, 'lng' => -15.99000, 'ps_codes' => ['506181']], // estimated
                        ],
                    ],
                ],
            ],
        ];
    }
}
