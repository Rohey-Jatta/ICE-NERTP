<?php

namespace Database\Seeders;

use App\Models\AdministrativeHierarchy;
use App\Models\Election;
use App\Models\PollingStation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds all BRIKAMA (West Coast Region) polling stations.
 * Source: Wards (1).pdf — Pages 8-20
 *
 * Constituencies:
 *   SANNEH MENTERENG  301xxx | OLD YUNDUM      302xxx | BUSUMBALA    303xxx
 *   KOMBO SOUTH       304xxx | BRIKAMA NORTH   305xxx | BRIKAMA SOUTH 306xxx
 *   KOMBO EAST        307xxx | FONI BREFET     308xxx | FONI BINTANG  309xxx
 *   FONI KANSALA      310xxx | FONI BONDALI    311xxx | FONI JARROL   312xxx
 *
 * Coordinates from verified geographic resource; stations without published
 * coordinates are marked // estimated.
 */
class BrikamaPollingStationSeeder extends Seeder
{
    private const OFFSET = 0.000018; // ≈ 2 metres at Gambia's latitude

    private int $electionId;
    private int $created = 0;

    // ─── entry point ──────────────────────────────────────────────────────────

    public function run(): void
    {
        $this->electionId = Election::where('slug', 'gambia-2021-presidential')->value('id')
            ?? throw new \RuntimeException('[BrikamaSeeder] Election gambia-2021-presidential not found.');

        $this->command->info('▶  Seeding Brikama (West Coast Region) polling stations...');

        $region = $this->node('admin_area', 'BRIKAMA', 'BRK', null, 'admin-area-approver');

        foreach ($this->schema() as $c) {
            $cn = $this->node('constituency', $c['name'], $c['code'], $region->id, 'constituency-approver');
            foreach ($c['wards'] as $w) {
                $wn = $this->node('ward', $w['name'], $w['code'], $cn->id, 'ward-approver');
                foreach ($w['stations'] as $s) {
                    $this->plant($wn->id, $s);
                }
            }
        }

        $this->command->info("✅  Brikama done — {$this->created} records created/verified.");
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
                'registered_voters'   => $s['voters'] ?? rand(250, 650),
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
            ['parent_id' => $parentId, 'name' => $name, 'slug' => Str::slug("{$name}-brk")]
        );
        if (!$node->assigned_approver_id) {
            $email    = Str::slug($name) . ".{$level}@brikama.iec.local";
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
        $email   = "officer.{$code}@brikama.iec.local";
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
             * 301xxx  SANNEH MENTERENG
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'SANNEH MENTERENG', 'code' => 'BRK-SM',
                'wards' => [
                    [
                        'name' => 'BRUFUT', 'code' => 'BRK-SM-BF',
                        'stations' => [
                            ['name' => 'MEDIANA MOSQUE',                        'lat' => 13.43500, 'lng' => -16.76200, 'ps_codes' => ['301011','301012','301013','301014']],
                            ['name' => 'BRUFUT PRI. SCH. (BEHIND MARKET)',      'lat' => 13.43120, 'lng' => -16.75800, 'ps_codes' => ['301021','301022','301023','301024','301025','301026']],
                            ['name' => 'BRUFUT 2',                              'lat' => 13.42800, 'lng' => -16.75500, 'ps_codes' => ['301031','301032','301033','301034','301035','301036']],
                            ['name' => 'BRUFUT FATHER\'S SCH. (DUTABAKOTO)',    'lat' => 13.42500, 'lng' => -16.75100, 'ps_codes' => ['301041','301042','301043','301044','301045','301046']],
                            ['name' => 'WULING KAMA OPP. ALKALO\'S COMP.',      'lat' => 13.41900, 'lng' => -16.74500, 'ps_codes' => ['301051','301052','301053']],
                            ['name' => 'BRUSUBI ESTATE SAMPLE HOUSE',           'lat' => 13.43800, 'lng' => -16.73200, 'ps_codes' => ['301061','301062','301063']],
                            ['name' => 'BRUSUBI ESTATE PHASE TWO 2',            'lat' => 13.44100, 'lng' => -16.72900, 'ps_codes' => ['301071','301072']],
                            ['name' => 'TRANQUIL MOSQUE',                       'lat' => 13.44500, 'lng' => -16.73500, 'ps_codes' => ['301081','301082']],
                        ],
                    ],
                    [
                        'name' => 'BIJILO', 'code' => 'BRK-SM-BJ',
                        'stations' => [
                            ['name' => 'BIJILO BANTABA',                                    'lat' => 13.42200, 'lng' => -16.77200, 'ps_codes' => ['301091','301092','301093','301094']],
                            ['name' => 'KERR SERIGN (HAMDALAYE) CENTRAL MOSQUE',            'lat' => 13.44400, 'lng' => -16.71800, 'ps_codes' => ['301101','301102','301103','301104']],
                            ['name' => 'SANCHABA SULAY JOBE (OUTSIDE ALKALO\'S COMP.)',     'lat' => 13.43200, 'lng' => -16.70500, 'ps_codes' => ['301111','301112','301113']],
                            ['name' => 'SANCHABA SULAY JOBE (SKILL CENTRE)',                'lat' => 13.43400, 'lng' => -16.70300, 'ps_codes' => ['301121','301122','301123']],
                        ],
                    ],
                    [
                        'name' => 'SUKUTA', 'code' => 'BRK-SM-SK',
                        'stations' => [
                            ['name' => 'SUKUTA CINEMA HALL',                         'lat' => 13.40500, 'lng' => -16.71200, 'ps_codes' => ['301131','301132','301133','301134','301135','301136','301137']],
                            ['name' => 'SUKUTA SEC SCH (LIBRARY)',                   'lat' => 13.40200, 'lng' => -16.71500, 'ps_codes' => ['301141','301142','301143']],
                            ['name' => 'SUKUTA ARABIC SCH.',                         'lat' => 13.39900, 'lng' => -16.71100, 'ps_codes' => ['301151','301152','301153','301154','301155']],
                            ['name' => 'SUKUTA TALABA KOTO (BY THE CENTRAL MOSQUE)', 'lat' => 13.40800, 'lng' => -16.70900, 'ps_codes' => ['301161','301162','301163']],
                            ['name' => 'SUKUTA NEMA BANI ISRAEL MOSQUE',             'lat' => 13.39750, 'lng' => -16.70950, 'ps_codes' => ['301171','301172','301173']], // estimated
                            ['name' => 'SUKUTA JAMISA (BY THE METAL WORKSHOP)',      'lat' => 13.39200, 'lng' => -16.71800, 'ps_codes' => ['301181','301182']],
                            ['name' => 'SALAGI (OPP. NAWEC WATER PLANT)',            'lat' => 13.38500, 'lng' => -16.72100, 'ps_codes' => ['301191','301192','301193']],
                            ['name' => 'SUKUTA TUMBUNGTO BANTABA',                   'lat' => 13.41200, 'lng' => -16.71400, 'ps_codes' => ['301201']],
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             * 302xxx  OLD YUNDUM
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'OLD YUNDUM', 'code' => 'BRK-OY',
                'wards' => [
                    [
                        'name' => 'JABANG', 'code' => 'BRK-OY-JB',
                        'stations' => [
                            ['name' => 'LATRIYA HEALTH CENTRE',     'lat' => 13.36500, 'lng' => -16.69200, 'ps_codes' => ['302011','302012']],
                            ['name' => 'YOUNA BANTABA',             'lat' => 13.35800, 'lng' => -16.70100, 'ps_codes' => ['302021','302022','302023']],
                            ['name' => 'MARIAMA KUNDA BANTABA',     'lat' => 13.37100, 'lng' => -16.71500, 'ps_codes' => ['302031','302032','302033','302034']],
                            ['name' => 'JABANG MOSQUE',             'lat' => 13.37800, 'lng' => -16.69500, 'ps_codes' => ['302041','302042','302043','302044','302045']],
                            ['name' => 'TAWUTO BANTABA',            'lat' => 13.38800, 'lng' => -16.68100, 'ps_codes' => ['302051','302052','302053']],
                            ['name' => 'OLD YUNDUM BANTABA',        'lat' => 13.39100, 'lng' => -16.67400, 'ps_codes' => ['302061','302062','302063','302064','302065']],
                        ],
                    ],
                    [
                        'name' => 'KUNKUJANG KEITA YA', 'code' => 'BRK-OY-KK',
                        'stations' => [
                            ['name' => 'MEDINA SEY KUNDA (SINCHU ALAGI) BANTABA',  'lat' => 13.40500, 'lng' => -16.67100, 'ps_codes' => ['302071','302072','302073','302074','302075','302076']],
                            ['name' => 'MEDINA SEY KUNDA (SINCHU ALAGI) 2',        'lat' => 13.40580, 'lng' => -16.67180, 'ps_codes' => ['302081','302082','302083','302084','302085']], // estimated — distinct compound section
                            ['name' => 'MEDINA BALIYA (SINCHU BALIYA) MOSQUE.',    'lat' => 13.39800, 'lng' => -16.68200, 'ps_codes' => ['302091','302092','302093','302094','302095']], // estimated
                            ['name' => 'SINCHU SORRIE NURSERY SCH.',               'lat' => 13.41200, 'lng' => -16.66200, 'ps_codes' => ['302101','302102','302103','302104','302105']],
                            ['name' => 'KUNKUJANG KEITA YAA CENTRAL MOSQUE',       'lat' => 13.41800, 'lng' => -16.67800, 'ps_codes' => ['302111','302112','302113','302114','302115','302116']],
                        ],
                    ],
                    [
                        'name' => 'WELLINGARA/NEMA', 'code' => 'BRK-OY-WN',
                        'stations' => [
                            ['name' => 'WELLINGARA (BEHIND JAH OIL PETROL STATION)', 'lat' => 13.38250, 'lng' => -16.65350, 'ps_codes' => ['302121','302122','302123','302124','302125','302126']], // estimated
                            ['name' => 'WELLINGARA RED CROSS BANTABA',               'lat' => 13.38400, 'lng' => -16.65200, 'ps_codes' => ['302131','302132','302133','302134']], // estimated
                            ['name' => 'NEMAKUNKU',                                  'lat' => 13.38600, 'lng' => -16.64900, 'ps_codes' => ['302141','302142','302143','302144','302145','302146','302147','302148','302149']], // estimated
                            ['name' => 'NEMA NASIR',                                 'lat' => 13.38700, 'lng' => -16.64800, 'ps_codes' => ['302151','302152','302153']], // estimated
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             * 303xxx  BUSUMBALA
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'BUSUMBALA', 'code' => 'BRK-BU',
                'wards' => [
                    [
                        'name' => 'LAMIN', 'code' => 'BRK-BU-LA',
                        'stations' => [
                            ['name' => 'KUBARIKO BANTABA',                                      'lat' => 13.39000, 'lng' => -16.64000, 'ps_codes' => ['303011']], // estimated
                            ['name' => 'MAKUMBAYA GARAGE',                                      'lat' => 13.38500, 'lng' => -16.63800, 'ps_codes' => ['303021','303022']], // estimated
                            ['name' => 'KUNKUJANG JATTA YAA BANTABA',                          'lat' => 13.38800, 'lng' => -16.63500, 'ps_codes' => ['303031','303032']], // estimated
                            ['name' => 'MANDINARY DUTOKOTO',                                   'lat' => 13.38200, 'lng' => -16.64200, 'ps_codes' => ['303041','303042','303043','303044','303045']], // estimated
                            ['name' => 'KOMBO KEREWAN BANTABA',                                'lat' => 13.37500, 'lng' => -16.61200, 'ps_codes' => ['303051','303052','303053','303054','303055']],
                            ['name' => 'DARANKA VILLAGE HALL',                                 'lat' => 13.39100, 'lng' => -16.62100, 'ps_codes' => ['303061','303062','303063']],
                            ['name' => 'LAMIN ST. PETER\'S PRI SCH (BEHIND PETROL STATION)',   'lat' => 13.38500, 'lng' => -16.63500, 'ps_codes' => ['303071','303072','303073','303074','303075']],
                            ['name' => 'LAMIN GAMTRADE TRAINING CENTRE (OPPOSITE)',             'lat' => 13.38100, 'lng' => -16.63200, 'ps_codes' => ['303081','303082','303083','303084']],
                            ['name' => 'LAMIN SDA SCHOOL(JADUS SCH)',                          'lat' => 13.38000, 'lng' => -16.63600, 'ps_codes' => ['303091','303092']], // estimated
                            ['name' => 'LAMIN BABYLON FOOTBALL FIELD',                         'lat' => 13.37950, 'lng' => -16.63400, 'ps_codes' => ['303101','303102','303103']], // estimated
                            ['name' => 'LAMIN HEALTH CENTRE',                                  'lat' => 13.38080, 'lng' => -16.63320, 'ps_codes' => ['303111','303112','303113','303114']], // estimated
                            ['name' => 'LAMIN CAMBODIA',                                       'lat' => 13.37800, 'lng' => -16.63800, 'ps_codes' => ['303121','303122']], // estimated
                        ],
                    ],
                    [
                        'name' => 'BANJULUNDING', 'code' => 'BRK-BU-BD',
                        'stations' => [
                            ['name' => 'BANJULUNDING SEED STORE',        'lat' => 13.35100, 'lng' => -16.66900, 'ps_codes' => ['303131','303132','303133']], // estimated
                            ['name' => 'BANJULUNDING COMM. CENTRE.',     'lat' => 13.35250, 'lng' => -16.66800, 'ps_codes' => ['303141','303142','303143','303144']], // estimated
                            ['name' => 'YARAM BAMBA ESTATE MOSQUE',      'lat' => 13.35400, 'lng' => -16.66650, 'ps_codes' => ['303151','303152']], // estimated
                            ['name' => 'NEW YUNDUM BANTBA',              'lat' => 13.39000, 'lng' => -16.70000, 'ps_codes' => ['303161','303162','303163','303164']], // estimated
                            ['name' => 'NEW YUNDUM PRI SCH',             'lat' => 13.39120, 'lng' => -16.69950, 'ps_codes' => ['303171','303172','303173','303174']], // estimated
                            ['name' => 'NEW YUNDUM SADINKA',             'lat' => 13.39200, 'lng' => -16.69900, 'ps_codes' => ['303181','303182']], // estimated
                            ['name' => 'BUSUMBALA BANTABA',              'lat' => 13.34800, 'lng' => -16.65800, 'ps_codes' => ['303191','303192','303193','303194','303195','303196','303197']],
                            ['name' => 'BUSUMBALA MODEL SUKOTO BANTABA', 'lat' => 13.34400, 'lng' => -16.66200, 'ps_codes' => ['303201','303202','303203','303204']],
                            ['name' => 'DARU BUSUMBALA BANTABA',         'lat' => 13.35200, 'lng' => -16.65200, 'ps_codes' => ['303211','303212','303213']],
                            ['name' => 'BUSUMBALA GUIGI BANTABA',        'lat' => 13.35050, 'lng' => -16.65600, 'ps_codes' => ['303221','303222','303223','303224']], // estimated
                            ['name' => 'BUSUMBALA TINTINBA MOSQUE',      'lat' => 13.34600, 'lng' => -16.66000, 'ps_codes' => ['303231','303232','303233']], // estimated
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             * 304xxx  KOMBO SOUTH
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'KOMBO SOUTH', 'code' => 'BRK-KS',
                'wards' => [
                    [
                        'name' => 'KARTONG', 'code' => 'BRK-KS-KT',
                        'stations' => [
                            ['name' => 'NYOFELLEH BANTABA',         'lat' => 13.14500, 'lng' => -16.77800, 'ps_codes' => ['304011','304012']], // estimated
                            ['name' => 'GUNJUR KUNKUJANG BANTABA',  'lat' => 13.19000, 'lng' => -16.77000, 'ps_codes' => ['304021','304022','304023']], // estimated
                            ['name' => 'SIFFOE CCF',                'lat' => 13.21000, 'lng' => -16.77500, 'ps_codes' => ['304031','304032','304033','304034','304035']], // estimated
                            ['name' => 'KARTONG HEALTH CENTRE',     'lat' => 13.16500, 'lng' => -16.78200, 'ps_codes' => ['304041','304042','304043','304044']], // estimated
                            ['name' => 'MEDINA SALAM (CENTRE)',     'lat' => 13.17000, 'lng' => -16.77900, 'ps_codes' => ['304051','304052']], // estimated
                            ['name' => 'BERENDING BANTABA',         'lat' => 13.17500, 'lng' => -16.77600, 'ps_codes' => ['304061','304062']], // estimated
                        ],
                    ],
                    [
                        'name' => 'GUNJUR', 'code' => 'BRK-KS-GJ',
                        'stations' => [
                            ['name' => 'GUNJUR HEALTH CENTRE',   'lat' => 13.18300, 'lng' => -16.76300, 'ps_codes' => ['304071','304072','304073','304074','304075','304076','304077','304078','304079']], // estimated
                            ['name' => 'GUNJUR (BY THE PRI SCH)','lat' => 13.18100, 'lng' => -16.76200, 'ps_codes' => ['304081','304082','304083','304084','304085','304086']],
                        ],
                    ],
                    [
                        'name' => 'SANYANG', 'code' => 'BRK-KS-SY',
                        'stations' => [
                            ['name' => 'SANYANG BANTABA',                               'lat' => 13.26200, 'lng' => -16.75100, 'ps_codes' => ['304091','304092','304093','304094','304095','304096','304097','304098','304099']],
                            ['name' => 'TUJERENG BANTABA',                              'lat' => 13.24600, 'lng' => -16.75600, 'ps_codes' => ['304101','304102','304103','304104']], // estimated
                            ['name' => 'TUJERENG (AROUND SEN SEC SCH)',                 'lat' => 13.24500, 'lng' => -16.75450, 'ps_codes' => ['304111','304112']], // estimated
                            ['name' => 'BATOKUNKU SKILL CENTRE (NEAR MOSQUE)',          'lat' => 13.23500, 'lng' => -16.75200, 'ps_codes' => ['304121','304122','304123']], // estimated
                            ['name' => 'BATOKUNKU ( NEAR NURSERY SCH.)',                'lat' => 13.23400, 'lng' => -16.75300, 'ps_codes' => ['304131']], // estimated
                            ['name' => 'TANJI COMM. CENTRE',                           'lat' => 13.32000, 'lng' => -16.74000, 'ps_codes' => ['304141','304142','304143','304144']], // estimated
                            ['name' => 'TANJI B',                                      'lat' => 13.31950, 'lng' => -16.74100, 'ps_codes' => ['304151','304152','304153','304154','304155']], // estimated
                            ['name' => 'KUNKUJANG MARIAMA BANTABA',                    'lat' => 13.29000, 'lng' => -16.73500, 'ps_codes' => ['304161','304162']], // estimated
                            ['name' => 'BANYAKA BANTABA',                              'lat' => 13.25500, 'lng' => -16.74800, 'ps_codes' => ['304171','304172','304173']], // estimated
                            ['name' => 'JAMBANJELLY MARKET',                           'lat' => 13.30500, 'lng' => -16.72800, 'ps_codes' => ['304181','304182','304183','304184','304185']], // estimated
                            ['name' => 'RUMBA BANTABA',                                'lat' => 13.30000, 'lng' => -16.72500, 'ps_codes' => ['304191']], // estimated
                            ['name' => 'JAMBUR BANTABA',                               'lat' => 13.29800, 'lng' => -16.69800, 'ps_codes' => ['304201','304202','304203','304204','304205']],
                            ['name' => 'FARATO MOSQUE',                                'lat' => 13.32800, 'lng' => -16.65100, 'ps_codes' => ['304211','304212','304213','304214','304215']],
                            ['name' => 'FARATO NEW TOWN MOSQUE',                       'lat' => 13.32100, 'lng' => -16.65600, 'ps_codes' => ['304221','304222']],
                            ['name' => 'FARATO NEMA MOSQUE',                           'lat' => 13.31950, 'lng' => -16.65750, 'ps_codes' => ['304231','304232','304233','304234']], // estimated
                            ['name' => 'FARATO (SANCHABA) OUTSIDE ALKALO\'S COMPOUND', 'lat' => 13.31500, 'lng' => -16.65900, 'ps_codes' => ['304241','304242']], // estimated
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             * 305xxx  BRIKAMA NORTH
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'BRIKAMA NORTH', 'code' => 'BRK-BN',
                'wards' => [
                    [
                        'name' => 'KEMBUJEH', 'code' => 'BRK-BN-KJ',
                        'stations' => [
                            ['name' => 'SEREKUNDA NDING SKILL CENTRE',          'lat' => 13.27800, 'lng' => -16.67200, 'ps_codes' => ['305011']], // estimated
                            ['name' => 'KEMBUJEH BANTABA',                      'lat' => 13.27600, 'lng' => -16.67000, 'ps_codes' => ['305021','305022','305023']], // estimated
                            ['name' => 'KEMBUJEH MEDINA SCH GROUND',            'lat' => 13.27500, 'lng' => -16.66850, 'ps_codes' => ['305031','305032']], // estimated
                            ['name' => 'BRIKAMA DARUKHAIRU MOSQUE',             'lat' => 13.27300, 'lng' => -16.66700, 'ps_codes' => ['305041','305042','305043','305044']], // estimated
                            ['name' => 'BRIKAMA MISIRA MOSQUE',                 'lat' => 13.27180, 'lng' => -16.66800, 'ps_codes' => ['305051','305052','305053']], // estimated
                            ['name' => 'BRIKAMA KABAFITA MOSQUE',               'lat' => 13.27500, 'lng' => -16.66900, 'ps_codes' => ['305061','305062','305063','305064']], // estimated
                            ['name' => 'BRIKAMA WELLINGARA PRAYING GROUNDS',    'lat' => 13.27650, 'lng' => -16.67050, 'ps_codes' => ['305071','305072','305073','305074']], // estimated
                            ['name' => 'KUBUNEH SKILL CENTRE',                  'lat' => 13.28200, 'lng' => -16.67300, 'ps_codes' => ['305081','305082']], // estimated
                            ['name' => 'BAFULOTO BANTABA',                      'lat' => 13.28400, 'lng' => -16.67200, 'ps_codes' => ['305091','305092']], // estimated
                            ['name' => 'BAFULOTO NEMA SU',                      'lat' => 13.28320, 'lng' => -16.67150, 'ps_codes' => ['305101','305102']], // estimated
                            ['name' => 'FARATO BOJANG KUNDA (SOTOKOI DARU) BANTABA', 'lat' => 13.31500, 'lng' => -16.64800, 'ps_codes' => ['305111','305112','305113']],
                        ],
                    ],
                    [
                        'name' => 'NYAMBAI', 'code' => 'BRK-BN-NY',
                        'stations' => [
                            ['name' => 'BRIKAMA NYAMBAI COLLEGE MARKET',                        'lat' => 13.28100, 'lng' => -16.65400, 'ps_codes' => ['305121','305122','305123','305124']],
                            ['name' => 'BRIKAMA NYAMBAI BABA GALLEH MOSQUE',                    'lat' => 13.28400, 'lng' => -16.65900, 'ps_codes' => ['305131','305132','305133','305134']],
                            ['name' => 'BRIKAMA NYAMBAI JAMBARR SANNEH (METHODIST SCH GATE)',  'lat' => 13.28550, 'lng' => -16.65700, 'ps_codes' => ['305141','305142','305143']], // estimated
                            ['name' => 'NEW TOWN NORTH (MOTHER ALI NUR. SCH.)',                 'lat' => 13.28700, 'lng' => -16.65600, 'ps_codes' => ['305151','305152']], // estimated
                            ['name' => 'BRIKAMA NEMATABA GIRL GUIDES CENTRE',                  'lat' => 13.28600, 'lng' => -16.65750, 'ps_codes' => ['305161','305162']], // estimated
                            ['name' => 'BRIKAMA NEMA GEEBUNGOTO (COMM DEV.)',                   'lat' => 13.28450, 'lng' => -16.65820, 'ps_codes' => ['305171','305172']], // estimated
                            ['name' => 'JAMISA BANTABA',                                        'lat' => 13.28300, 'lng' => -16.65950, 'ps_codes' => ['305181','305182','305183']], // estimated
                            ['name' => 'JALAMBANG MOSQUE',                                      'lat' => 13.27800, 'lng' => -16.66400, 'ps_codes' => ['305191','305192','305193','305194']], // estimated
                            ['name' => 'KASSAKUNDA BANTABA',                                    'lat' => 13.27700, 'lng' => -16.66500, 'ps_codes' => ['305201','305202']], // estimated
                            ['name' => 'BUSURANDING BANTABA',                                   'lat' => 13.27600, 'lng' => -16.66600, 'ps_codes' => ['305211']], // estimated
                            ['name' => 'TAIBATOU BANTABA',                                      'lat' => 13.27550, 'lng' => -16.66650, 'ps_codes' => ['305221']], // estimated
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             * 306xxx  BRIKAMA SOUTH
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'BRIKAMA SOUTH', 'code' => 'BRK-BS',
                'wards' => [
                    [
                        'name' => 'MARAKISSA', 'code' => 'BRK-BS-MR',
                        'stations' => [
                            ['name' => 'BRIKAMA MEDINA BANTABA',    'lat' => 13.26900, 'lng' => -16.66000, 'ps_codes' => ['306011','306012','306013']], // estimated
                            ['name' => 'JAMWELLY BANTABA',          'lat' => 13.27000, 'lng' => -16.65800, 'ps_codes' => ['306021','306022']], // estimated
                            ['name' => 'MANDUAR BANTABA',           'lat' => 13.26800, 'lng' => -16.65700, 'ps_codes' => ['306031','306032','306033','306034']], // estimated
                            ['name' => 'PENYEM BANTABA',            'lat' => 13.26650, 'lng' => -16.65650, 'ps_codes' => ['306041','306042']], // estimated
                            ['name' => 'BUSURA CLINIC',             'lat' => 13.23500, 'lng' => -16.63500, 'ps_codes' => ['306051','306052','306053']], // estimated
                            ['name' => 'DARSILAMEH HEALTH CENTRE',  'lat' => 13.19500, 'lng' => -16.65100, 'ps_codes' => ['306061','306062','306063']],
                            ['name' => 'KABAKEL OUTSIDE SCH',       'lat' => 13.22800, 'lng' => -16.63100, 'ps_codes' => ['306071']],
                            ['name' => 'MARAKISSA BANTABA',         'lat' => 13.23200, 'lng' => -16.62100, 'ps_codes' => ['306081','306082','306083']],
                            ['name' => 'BAKARY SAMBOU YAA MOSQUE',  'lat' => 13.24100, 'lng' => -16.63800, 'ps_codes' => ['306091','306092']],
                            ['name' => 'KITTY BANTABA',             'lat' => 13.25500, 'lng' => -16.61500, 'ps_codes' => ['306101','306102','306103','306104']],
                        ],
                    ],
                    [
                        'name' => 'SUBA', 'code' => 'BRK-BS-SB',
                        'stations' => [
                            ['name' => 'BRIKAMA SANNEH KUNDA BANTABA',                  'lat' => 13.27200, 'lng' => -16.66500, 'ps_codes' => ['306111','306112','306113']],
                            ['name' => 'BRIKAMA BOJANG KUNDA BANTABA',                  'lat' => 13.26800, 'lng' => -16.65800, 'ps_codes' => ['306121']],
                            ['name' => 'BRIKAMA SUMA KUNDA BANTABA',                    'lat' => 13.27100, 'lng' => -16.65700, 'ps_codes' => ['306131','306132']], // estimated
                            ['name' => 'BRIKAMA HAWLA KUNDA (KABILO HEAD COMP. GATE)',  'lat' => 13.27050, 'lng' => -16.65600, 'ps_codes' => ['306141','306142']], // estimated
                            ['name' => 'BRIKAMA MANSARINGSU BANTABA',                   'lat' => 13.26980, 'lng' => -16.65550, 'ps_codes' => ['306151']], // estimated
                            ['name' => 'BRIKAMA SANTUSO NURSERY SCH.',                  'lat' => 13.26950, 'lng' => -16.65500, 'ps_codes' => ['306161','306162']], // estimated
                            ['name' => 'BRIKAMA PERSEVERANCE BANTABA',                  'lat' => 13.26880, 'lng' => -16.65450, 'ps_codes' => ['306171','306172','306173']], // estimated
                            ['name' => 'BRIKAMA PERSEVERANCE PRAYING GROUND',           'lat' => 13.26820, 'lng' => -16.65400, 'ps_codes' => ['306181','306182','306183']], // estimated
                            ['name' => 'BRIKAMA GIDDA BANTABA',                         'lat' => 13.26750, 'lng' => -16.65350, 'ps_codes' => ['306191','306192','306193','306194']], // estimated
                            ['name' => 'BRIKAMA GIDDA (SCHOOL AREA)',                   'lat' => 13.26680, 'lng' => -16.65300, 'ps_codes' => ['306201','306202','306203','306204']], // estimated
                            ['name' => 'BRIKAMA DARSILAMEH (NEAR ALKALO\'S COMPOUND)',  'lat' => 13.26600, 'lng' => -16.65250, 'ps_codes' => ['306211','306212','306213']], // estimated
                            ['name' => 'BRIKAMA NEWTOWN SOUTH MOSQUE',                  'lat' => 13.26520, 'lng' => -16.65200, 'ps_codes' => ['306221','306222','306223']], // estimated
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             * 307xxx  KOMBO EAST
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'KOMBO EAST', 'code' => 'BRK-KE',
                'wards' => [
                    [
                        'name' => 'PIRANG', 'code' => 'BRK-KE-PR',
                        'stations' => [
                            ['name' => 'KULORO BANTABA',              'lat' => 13.29500, 'lng' => -16.54100, 'ps_codes' => ['307011','307012','307013','307014']],
                            ['name' => 'BONTO KUTA JALOKOTO BANTABA', 'lat' => 13.30800, 'lng' => -16.52800, 'ps_codes' => ['307021']],
                            ['name' => 'PIRANG BANTABA',              'lat' => 13.32100, 'lng' => -16.50500, 'ps_codes' => ['307031','307032']],
                            ['name' => 'PIRANG BERENDING BANTABA',    'lat' => 13.31400, 'lng' => -16.49500, 'ps_codes' => ['307041','307042','307043']],
                            ['name' => 'PIRANG BERENDING DARUSALAM',  'lat' => 13.31100, 'lng' => -16.49100, 'ps_codes' => ['307051']],
                        ],
                    ],
                    [
                        'name' => 'KAFUTA', 'code' => 'BRK-KE-KF',
                        'stations' => [
                            ['name' => 'KAIRABA (FARABA MANOKANG) BANTABA', 'lat' => 13.27500, 'lng' => -16.45200, 'ps_codes' => ['307061','307062']],
                            ['name' => 'FARABA BANTA BANATBA',              'lat' => 13.26100, 'lng' => -16.47800, 'ps_codes' => ['307071','307072','307073']],
                            ['name' => 'MEDINA SOKOTOI BANTABA',            'lat' => 13.25800, 'lng' => -16.47500, 'ps_codes' => ['307081','307082']], // estimated
                            ['name' => 'FARABA SUTU CCF .',                 'lat' => 13.26200, 'lng' => -16.48200, 'ps_codes' => ['307091']], // estimated
                            ['name' => 'KAFUTA BANTABA',                    'lat' => 13.26900, 'lng' => -16.46200, 'ps_codes' => ['307101','307102','307103','307104']], // estimated
                        ],
                    ],
                    [
                        'name' => 'GIBORO', 'code' => 'BRK-KE-GB',
                        'stations' => [
                            ['name' => 'SOHM HEALTH CENTRE',    'lat' => 13.28500, 'lng' => -16.51000, 'ps_codes' => ['307111','307112']], // estimated
                            ['name' => 'OMORTO SKILL CENTRE',   'lat' => 13.29000, 'lng' => -16.50800, 'ps_codes' => ['307121']], // estimated
                            ['name' => 'GIBORO KUTA BANTABA',   'lat' => 13.31000, 'lng' => -16.52000, 'ps_codes' => ['307131','307132','307133','307134']], // estimated
                            ['name' => 'BASORI BANTABA',        'lat' => 13.30500, 'lng' => -16.51500, 'ps_codes' => ['307141','307142','307143']], // estimated
                            ['name' => 'TUBA KUTA BANTABA',     'lat' => 13.29800, 'lng' => -16.51800, 'ps_codes' => ['307151','307152']], // estimated
                            ['name' => 'MANDINABA BANTABA',     'lat' => 13.31500, 'lng' => -16.52300, 'ps_codes' => ['307161','307162','307163']], // estimated
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             * 308xxx  FONI BREFET
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'FONI BREFET', 'code' => 'BRK-FB',
                'wards' => [
                    [
                        'name' => 'SOMITA', 'code' => 'BRK-FB-SO',
                        'stations' => [
                            ['name' => 'SOMITA CCF',             'lat' => 13.26500, 'lng' => -16.21000, 'ps_codes' => ['308011','308012','308013']], // estimated
                            ['name' => 'NDEMBAN TENDA BANTABA',  'lat' => 13.27000, 'lng' => -16.22000, 'ps_codes' => ['308021','308022','308023']], // estimated
                            ['name' => 'BEREFT BANTABA',         'lat' => 13.25800, 'lng' => -16.20500, 'ps_codes' => ['308041']], // estimated
                        ],
                    ],
                    [
                        'name' => 'BULOCK', 'code' => 'BRK-FB-BL',
                        'stations' => [
                            ['name' => 'BESSI CCF',                     'lat' => 13.24500, 'lng' => -16.21500, 'ps_codes' => ['308031','308032']], // estimated
                            ['name' => 'SUTUSINJANG DAY CARE CENTRE',   'lat' => 13.23100, 'lng' => -16.38500, 'ps_codes' => ['308051','308052']],
                            ['name' => 'BAJANA BANTABA',                'lat' => 13.23800, 'lng' => -16.39000, 'ps_codes' => ['308061']], // estimated
                            ['name' => 'BULOCK BANTABA',                'lat' => 13.25000, 'lng' => -16.38000, 'ps_codes' => ['308071','308072','308073','308074']], // estimated
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             * 309xxx  FONI BINTANG
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'FONI BINTANG', 'code' => 'BRK-FBT',
                'wards' => [
                    [
                        'name' => 'KUSAMAI', 'code' => 'BRK-FBT-KS',
                        'stations' => [
                            ['name' => 'ARANGALEN BANTABA',  'lat' => 13.23800, 'lng' => -16.26800, 'ps_codes' => ['309011']], // estimated
                            ['name' => 'BAJAGARR BANTABA',   'lat' => 13.24200, 'lng' => -16.27200, 'ps_codes' => ['309021']], // estimated
                            ['name' => 'BATABUT KANTORA',    'lat' => 13.24600, 'lng' => -16.27600, 'ps_codes' => ['309031','309032']], // estimated
                            ['name' => 'KUSAMAI BANTABA',    'lat' => 13.25000, 'lng' => -16.28000, 'ps_codes' => ['309041','309042']], // estimated
                            ['name' => 'JANACK BANTABA',     'lat' => 13.25300, 'lng' => -16.28400, 'ps_codes' => ['309101','309102']], // estimated
                        ],
                    ],
                    [
                        'name' => 'SIBANOR', 'code' => 'BRK-FBT-SB',
                        'stations' => [
                            ['name' => 'TAMPOTO',                   'lat' => 13.26200, 'lng' => -16.29500, 'ps_codes' => ['309051','309052']], // estimated
                            ['name' => 'KANSANYI ECD',              'lat' => 13.26000, 'lng' => -16.29200, 'ps_codes' => ['309061']], // estimated
                            ['name' => 'SIBANOR BANTABA',           'lat' => 13.26400, 'lng' => -16.29800, 'ps_codes' => ['309071','309072','309073','309074']], // estimated
                            ['name' => 'BINTANG BANTABA',           'lat' => 13.26600, 'lng' => -16.30100, 'ps_codes' => ['309081']], // estimated
                            ['name' => 'BULLANJORR BANTABA',        'lat' => 13.26800, 'lng' => -16.30400, 'ps_codes' => ['309091']], // estimated
                            ['name' => 'BATENDING KAJERA BANTABA',  'lat' => 13.27000, 'lng' => -16.30600, 'ps_codes' => ['309111']], // estimated
                            ['name' => 'JAKOI SIBIRICK',            'lat' => 13.27200, 'lng' => -16.30800, 'ps_codes' => ['309121']], // estimated
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             * 310xxx  FONI KANSALA
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'FONI KANSALA', 'code' => 'BRK-FK',
                'wards' => [
                    [
                        'name' => 'KANILAI', 'code' => 'BRK-FK-KL',
                        'stations' => [
                            ['name' => 'SANGHAJORR HEALTH CENTRE',  'lat' => 13.29500, 'lng' => -16.25000, 'ps_codes' => ['310011','310012']], // estimated
                            ['name' => 'DARSILAMEH BANTABA',        'lat' => 13.29700, 'lng' => -16.25200, 'ps_codes' => ['310021']], // estimated
                            ['name' => 'KANFENDI BANTABA',          'lat' => 13.29900, 'lng' => -16.25400, 'ps_codes' => ['310031','310032']], // estimated
                            ['name' => 'KANILAI PRI. SCH.',         'lat' => 13.30100, 'lng' => -16.25600, 'ps_codes' => ['310041','310042','310043','310044']], // estimated
                            ['name' => 'JEKESI DANDONI BANTABA',    'lat' => 13.30300, 'lng' => -16.25800, 'ps_codes' => ['310051']], // estimated
                        ],
                    ],
                    [
                        'name' => 'BWIAM', 'code' => 'BRK-FK-BW',
                        'stations' => [
                            ['name' => 'BWIAM CCF',                 'lat' => 13.32000, 'lng' => -16.28000, 'ps_codes' => ['310061','310062','310063']], // estimated
                            ['name' => 'DOBONG BANTABA',            'lat' => 13.32200, 'lng' => -16.28200, 'ps_codes' => ['310071']], // estimated
                            ['name' => 'KAMPANT ANGLICAN CHURCH',   'lat' => 13.32400, 'lng' => -16.28400, 'ps_codes' => ['310081','310082']], // estimated
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             * 311xxx  FONI BONDALI
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'FONI BONDALI', 'code' => 'BRK-FBD',
                'wards' => [
                    [
                        'name' => 'BANTANJANG', 'code' => 'BRK-FBD-BT',
                        'stations' => [
                            ['name' => 'BONDALI JOLA CHIEF\'S QUARTER',  'lat' => 13.27800, 'lng' => -16.21500, 'ps_codes' => ['311021','311022']], // estimated
                            ['name' => 'BANTANJANG BANTABA',             'lat' => 13.28000, 'lng' => -16.21700, 'ps_codes' => ['311051']], // estimated
                        ],
                    ],
                    [
                        'name' => 'MAYORK', 'code' => 'BRK-FBD-MY',
                        'stations' => [
                            ['name' => 'CHABAI BANTABA',        'lat' => 13.27500, 'lng' => -16.21000, 'ps_codes' => ['311011']], // estimated
                            ['name' => 'KANKURANG BANTABA',     'lat' => 13.27200, 'lng' => -16.20800, 'ps_codes' => ['311031','311032']], // estimated
                            ['name' => 'MAYORK HEALTH CENTRE',  'lat' => 13.27000, 'lng' => -16.20600, 'ps_codes' => ['311041','311042']], // estimated
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             * 312xxx  FONI JARROL
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'FONI JARROL', 'code' => 'BRK-FJ',
                'wards' => [
                    [
                        'name' => 'SINTET', 'code' => 'BRK-FJ-SN',
                        'stations' => [
                            ['name' => 'KALAGI BANTABA',        'lat' => 13.36800, 'lng' => -16.22000, 'ps_codes' => ['312011','312012']], // estimated
                            ['name' => 'SINTET HEALTH CENTRE',  'lat' => 13.37000, 'lng' => -16.22200, 'ps_codes' => ['312021','312022']], // estimated
                        ],
                    ],
                    [
                        'name' => 'WASSADOU', 'code' => 'BRK-FJ-WS',
                        'stations' => [
                            ['name' => 'KANMAMUDOU ECD',        'lat' => 13.37200, 'lng' => -16.22400, 'ps_codes' => ['312031','312032']], // estimated
                            ['name' => 'WASSADOU BANTABA',      'lat' => 13.37400, 'lng' => -16.22600, 'ps_codes' => ['312041']], // estimated
                            ['name' => 'ARANKON KUNDA BANTABA', 'lat' => 13.37600, 'lng' => -16.22800, 'ps_codes' => ['312051']], // estimated
                        ],
                    ],
                ],
            ],
        ];
    }
}
