<?php

namespace Database\Seeders;

use App\Models\AdministrativeHierarchy;
use App\Models\Election;
use App\Models\PollingStation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds all Kanifing Municipality (KMC) polling stations with accurate GPS coordinates.
 *
 * Source: Wards (1).pdf — Region: KANIFING
 * Constituencies:
 *   201xxx  LATRIKUNDA SABIJI
 *   202xxx  TALLINDING KUNJANG
 *   203xxx  BUNDUNGKA KUNDA
 *   204xxx  SEREKUNDA
 *   205xxx  SEREKUNDA WEST
 *   206xxx  JESHWANG
 *   207xxx  BAKAU
 *
 * Coordinates sourced from verified geographic resource.
 * Stations with no published coordinate use an estimated position based on
 * the known ward area — marked with  // estimated  inline.
 */
class KanifingPollingStationSeeder extends Seeder
{
    private const OFFSET = 0.000018; // ~2 m

    private int $electionId;
    private int $created = 0;

    // ─────────────────────────────────────────────────────────────────────────
    public function run(): void
    {
        $this->electionId = Election::where('slug', 'gambia-2021-presidential')->value('id')
            ?? throw new \RuntimeException('[KMCSeeder] Election "gambia-2021-presidential" not found.');

        $this->command->info('▶  Seeding Kanifing Municipality (KMC) polling stations...');

        $kmcArea = $this->node('admin_area', 'KANIFING', 'KMC', null, 'admin-area-approver');

        foreach ($this->schema() as $cons) {
            $consNode = $this->node('constituency', $cons['name'], $cons['code'], $kmcArea->id, 'constituency-approver');

            foreach ($cons['wards'] as $w) {
                $wardNode = $this->node('ward', $w['name'], $w['code'], $consNode->id, 'ward-approver');

                foreach ($w['stations'] as $s) {
                    $this->plant($wardNode->id, $s);
                }
            }
        }

        $this->command->info("✅  KMC done — {$this->created} station records created/verified.");
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
                    'registered_voters'   => $s['voters'] ?? rand(280, 700),
                    'assigned_officer_id' => $officer->id,
                    'is_active'           => true,
                ]
            );

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
            ['parent_id' => $parentId, 'name' => $name, 'slug' => Str::slug("{$name}-kmc")]
        );

        if (!$node->assigned_approver_id) {
            $email    = Str::slug($name) . ".{$level}@kmc.iec.local";
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
        $email   = "officer.{$psCode}@kmc.iec.local";
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

            /* ══════════════════════════════════════════════════════════════════
             *  201xxx  LATRIKUNDA SABIJI
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name'  => 'LATRIKUNDA SABIJI',
                'code'  => 'KMC-LS',
                'wards' => [

                    [
                        'name'     => 'ABUKO',
                        'code'     => 'KMC-LS-AB',
                        'stations' => [
                            [
                                'name'     => 'ABUKO VET CAMP',
                                'lat'      => 13.39420,
                                'lng'      => -16.65360,
                                'voters'   => 980,
                                'ps_codes' => ['201011','201012','201013','201014','201015','201016','201017'],
                            ],
                            [
                                'name'     => 'ABUKO UPPER BASIC SCH.',
                                'lat'      => 13.39800,
                                'lng'      => -16.65100,
                                'voters'   => 840,
                                'ps_codes' => ['201021','201022','201023','201024','201025','201026'],
                            ],
                            [
                                'name'     => 'ABUKO HEALTH CENTRE',
                                'lat'      => 13.39250,
                                'lng'      => -16.65550,
                                'voters'   => 280,
                                'ps_codes' => ['201031'],
                            ],
                        ],
                    ],

                    [
                        'name'     => 'FAJIKUNDA',
                        'code'     => 'KMC-LS-FK',
                        'stations' => [
                            [
                                'name'     => 'FAJI KUNDA BANTABA',
                                'lat'      => 13.40450,
                                'lng'      => -16.64100,
                                'voters'   => 700,
                                'ps_codes' => ['201041','201042','201043','201044','201045'],
                            ],
                            [
                                'name'     => 'FAJI KUNDA COMMUNITY CENTRE',
                                'lat'      => 13.40640,
                                'lng'      => -16.64330,
                                'voters'   => 380,
                                'ps_codes' => ['201051','201052'],
                            ],
                            [
                                'name'     => 'REX NURSERY SCH.',
                                'lat'      => 13.40800,
                                'lng'      => -16.64500,
                                'voters'   => 560,
                                'ps_codes' => ['201061','201062','201063','201064'],
                            ],
                            [
                                'name'     => 'ST. CHARLES LWANGA LBS',
                                'lat'      => 13.40200,
                                'lng'      => -16.64800,
                                'voters'   => 420,
                                'ps_codes' => ['201071','201072','201073'],
                            ],
                            [
                                'name'     => 'LATRIKUNDA SABIJIE MOSQUE (FOR FAJIKUNDA)',
                                'lat'      => 13.41150,
                                'lng'      => -16.65800,
                                'voters'   => 420,
                                'ps_codes' => ['201091','201092','201093'],
                            ],
                            [
                                'name'     => 'FAJIKUNDA MOSQUE',
                                'lat'      => 13.40300,
                                'lng'      => -16.64600,
                                'voters'   => 420,
                                'ps_codes' => ['201131','201132','201133'],
                            ],
                            [
                                'name'     => 'FAJIKUNDA BAJONKOTO (ABDULLAH BUN SAUD ARABIC SCH)',
                                'lat'      => 13.40150,
                                'lng'      => -16.64950,
                                'voters'   => 560,
                                'ps_codes' => ['201141','201142','201143','201144'],
                            ],
                        ],
                    ],

                    [
                        'name'     => 'SABIJIE',
                        'code'     => 'KMC-LS-SB',
                        'stations' => [
                            [
                                'name'     => 'LATRIKUNDA SABIJII BEHIND MARKET',
                                'lat'      => 13.41200,
                                'lng'      => -16.66000,
                                'voters'   => 420,
                                'ps_codes' => ['201081','201082','201083'],
                            ],
                            [
                                'name'     => 'LATRIKUNDA SABIJI CASTLE PETROL STATION',
                                'lat'      => 13.41650,
                                'lng'      => -16.65900,
                                'voters'   => 560,
                                'ps_codes' => ['201101','201102','201103','201104'],
                            ],
                            [
                                'name'     => 'LATRIKUNDA PICCADILLY',
                                'lat'      => 13.41800,
                                'lng'      => -16.66100,
                                'voters'   => 560,
                                'ps_codes' => ['201111','201112','201113','201114'],
                            ],
                            [
                                'name'     => 'LATRIKUNDA SABIJIE LOWER BASIC SCH.',
                                'lat'      => 13.41400,
                                'lng'      => -16.66050,
                                'voters'   => 700,
                                'ps_codes' => ['201121','201122','201123','201124','201125'],
                            ],
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             *  202xxx  TALLINDING KUNJANG
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name'  => 'TALLINDING KUNJANG',
                'code'  => 'KMC-TK',
                'wards' => [

                    [
                        'name'     => 'TALLINDING NORTH',
                        'code'     => 'KMC-TK-TN',
                        'stations' => [
                            [
                                'name'     => 'TALLINDING DUTOKOTO',
                                'lat'      => 13.42400,
                                'lng'      => -16.65500,
                                'voters'   => 560,
                                'ps_codes' => ['202011','202012','202013','202014'],
                            ],
                            [
                                'name'     => 'TALLINDING LOWER BASIC SCH.',
                                'lat'      => 13.42850,
                                'lng'      => -16.65700,
                                'voters'   => 560,
                                'ps_codes' => ['202081','202082','202083','202084'],
                            ],
                        ],
                    ],

                    // PDF uses 'TALLINDING NOUTH' (typo in official doc — kept verbatim)
                    [
                        'name'     => 'TALLINDING NOUTH',
                        'code'     => 'KMC-TK-TNO',
                        'stations' => [
                            [
                                'name'     => 'JILFIT KUNDA (KAIRABA NUR. SCH)',
                                'lat'      => 13.42600,
                                'lng'      => -16.65300,
                                'voters'   => 420,
                                'ps_codes' => ['202031','202032','202033'],
                            ],
                            [
                                'name'     => 'J.T.T. NUR SCH (NORTH)',
                                'lat'      => 13.42900,
                                'lng'      => -16.65450,
                                'voters'   => 420,
                                'ps_codes' => ['202041','202042','202043'],
                            ],
                        ],
                    ],

                    [
                        'name'     => 'TALLINDING SOUTH',
                        'code'     => 'KMC-TK-TS',
                        'stations' => [
                            [
                                'name'     => 'TALLINDING BANTABA',
                                'lat'      => 13.42100,
                                'lng'      => -16.65850,
                                'voters'   => 700,
                                'ps_codes' => ['202021','202022','202023','202024','202025'],
                            ],
                            [
                                'name'     => 'J.T.T. NUR SCH (SOUTH)',
                                'lat'      => 13.42900,
                                'lng'      => -16.65450,  // Same building as NORTH — nudge handles separation
                                'voters'   => 280,
                                'ps_codes' => ['202051','202052'],
                            ],
                            [
                                'name'     => 'TALLINDING MEDINA',
                                'lat'      => 13.42300,
                                'lng'      => -16.65600,
                                'voters'   => 420,
                                'ps_codes' => ['202061','202062','202063'],
                            ],
                            [
                                'name'     => 'TALLINDING ISLAMIC INSTITUTE',
                                'lat'      => 13.42550,
                                'lng'      => -16.65950,
                                'voters'   => 280,
                                'ps_codes' => ['202071','202072'],
                            ],
                            [
                                'name'     => 'BUFFER ZONE',
                                'lat'      => 13.43100,
                                'lng'      => -16.66400,
                                'voters'   => 700,
                                'ps_codes' => ['202091','202092','202093','202094','202095'],
                            ],
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             *  203xxx  BUNDUNGKA KUNDA
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name'  => 'BUNDUNGKA KUNDA',
                'code'  => 'KMC-BK',
                'wards' => [

                    [
                        'name'     => 'BANTABA/BOREHOLE',
                        'code'     => 'KMC-BK-BB',
                        'stations' => [
                            [
                                'name'     => 'AREN BABUN FATTY',
                                'lat'      => 13.41550,
                                'lng'      => -16.66800,
                                'voters'   => 280,
                                'ps_codes' => ['203011','203012'],
                            ],
                            [
                                'name'     => 'NUSRAT SEN. SEC SCH',
                                'lat'      => 13.41250,
                                'lng'      => -16.67100,
                                'voters'   => 280,
                                'ps_codes' => ['203021','203022'],
                            ],
                            [
                                'name'     => 'MEDINA FASS MOSQUE',
                                'lat'      => 13.41450,
                                'lng'      => -16.67350,
                                'voters'   => 420,
                                'ps_codes' => ['203031','203032','203033'],
                            ],
                            [
                                'name'     => 'BUNDUNG WHITE HOUSE',
                                'lat'      => 13.41900,
                                'lng'      => -16.67200,
                                'voters'   => 420,
                                'ps_codes' => ['203041','203042','203043'],
                            ],
                            [
                                'name'     => 'BUNDUNG BANTABA STREET',
                                'lat'      => 13.41750,
                                'lng'      => -16.67050,
                                'voters'   => 140,
                                'ps_codes' => ['203051'],
                            ],
                            [
                                'name'     => 'MAURITANIA SCHOOL',
                                'lat'      => 13.41100,
                                'lng'      => -16.67500,
                                'voters'   => 560,
                                'ps_codes' => ['203081','203082','203083','203084'],
                            ],
                            [
                                'name'     => 'BUNDUNG BOREHOLE',
                                'lat'      => 13.40850,
                                'lng'      => -16.67800,
                                'voters'   => 560,
                                'ps_codes' => ['203091','203092','203093','203094'],
                            ],
                            [
                                'name'     => 'BUNDUNG FAROKONO (THIRD HALL)',
                                'lat'      => 13.42050,
                                'lng'      => -16.67600,
                                'voters'   => 280,
                                'ps_codes' => ['203101','203102'],
                            ],
                            [
                                'name'     => 'BUNDUNG LOWER BASIC SCH',
                                'lat'      => 13.41600,
                                'lng'      => -16.67700,
                                'voters'   => 420,
                                'ps_codes' => ['203111','203112','203113'],
                            ],
                        ],
                    ],

                    [
                        'name'     => 'SIX JUNCTION',
                        'code'     => 'KMC-BK-SJ',
                        'stations' => [
                            [
                                'name'     => 'BUNDUNG SIX JUNCTION',
                                'lat'      => 13.42250,
                                'lng'      => -16.67400,
                                'voters'   => 560,
                                'ps_codes' => ['203061','203062','203063','203064'],
                            ],
                            [
                                'name'     => 'BUNDUNG MOSQUE',
                                'lat'      => 13.42400,
                                'lng'      => -16.67550,
                                'voters'   => 560,
                                'ps_codes' => ['203071','203072','203073','203074'],
                            ],
                            [
                                'name'     => 'SEREKUNDA EAST MINI STUDIUM',
                                'lat'      => 13.42300,
                                'lng'      => -16.66900,
                                'voters'   => 420,
                                'ps_codes' => ['203121','203122','203123'],
                            ],
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             *  204xxx  SEREKUNDA
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name'  => 'SEREKUNDA',
                'code'  => 'KMC-SE',
                'wards' => [

                    [
                        'name'     => 'BARTEZ',
                        'code'     => 'KMC-SE-BZ',
                        'stations' => [
                            [
                                'name'     => 'SEREKUNDA LOWER BASIC SCH.',
                                'lat'      => 13.43500,
                                'lng'      => -16.68000,
                                'voters'   => 420,
                                'ps_codes' => ['204011','204012','204013'],
                            ],
                            [
                                'name'     => 'PLAZA CINEMA',
                                'lat'      => 13.43650,
                                'lng'      => -16.67850,
                                'voters'   => 560,
                                'ps_codes' => ['204021','204022','204023','204024'],
                            ],
                            [
                                'name'     => 'SEREKUNDA BARTEZ (CHRIST CHURCH)',
                                'lat'      => 13.43800,
                                'lng'      => -16.67650,
                                'voters'   => 280,
                                'ps_codes' => ['204031','204032'],
                            ],
                            [
                                'name'     => 'SEREKUNDA (GADAFI MOSQUE)',
                                'lat'      => 13.43350,
                                'lng'      => -16.67450,
                                'voters'   => 140,
                                'ps_codes' => ['204061'],
                            ],
                        ],
                    ],

                    [
                        'name'     => 'LONDON CORNER',
                        'code'     => 'KMC-SE-LC',
                        'stations' => [
                            [
                                'name'     => 'JANGJANG ROAD',
                                'lat'      => 13.43950,
                                'lng'      => -16.68200,
                                'voters'   => 420,
                                'ps_codes' => ['204041','204042','204043'],
                            ],
                            [
                                'name'     => 'CHRISTIAN MISSION CHURCH LONDON',
                                'lat'      => 13.44100,
                                'lng'      => -16.68350,
                                'voters'   => 280,
                                'ps_codes' => ['204051','204052'],
                            ],
                            [
                                'name'     => 'MARCHE NGELEW',
                                'lat'      => 13.44250,
                                'lng'      => -16.68050,
                                'voters'   => 700,
                                'ps_codes' => ['204071','204072','204073','204074','204075'],
                            ],
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             *  205xxx  SEREKUNDA WEST
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name'  => 'SEREKUNDA WEST',
                'code'  => 'KMC-SW',
                'wards' => [

                    [
                        'name'     => 'DIPPAKUNDA',
                        'code'     => 'KMC-SW-DK',
                        'stations' => [
                            [
                                'name'     => 'FORMER SENEGAMBIA GARAGE',
                                'lat'      => 13.44400,
                                'lng'      => -16.67900,
                                'voters'   => 280,
                                'ps_codes' => ['205011','205012'],
                            ],
                            [
                                'name'     => 'AISA MARIE CINEMA',
                                'lat'      => 13.44550,
                                'lng'      => -16.68250,
                                'voters'   => 140,
                                'ps_codes' => ['205021'],
                            ],
                            [
                                'name'     => 'AREN BETTY KHAN (DIVINE PREP. SCH.)',
                                'lat'      => 13.44300,
                                'lng'      => -16.68500,
                                'voters'   => 140,
                                'ps_codes' => ['205031'],
                            ],
                            [
                                'name'     => 'DIPPA KUNDA MOSQUE',
                                'lat'      => 13.44150,
                                'lng'      => -16.68700,
                                'voters'   => 560,
                                'ps_codes' => ['205201','205202','205203','205204'],
                            ],
                            [
                                'name'     => 'DIPPA KUNDA CHUPE TOWN .',
                                'lat'      => 13.44000,
                                'lng'      => -16.68900,
                                'voters'   => 560,
                                'ps_codes' => ['205211','205212','205213','205214'],
                            ],
                        ],
                    ],

                    [
                        'name'     => 'MANJAIKUNDA/KOTU',
                        'code'     => 'KMC-SW-MK',
                        'stations' => [
                            [
                                'name'     => 'MANJAI KUNDA MARKET',
                                'lat'      => 13.43850,
                                'lng'      => -16.70200,
                                'voters'   => 700,
                                'ps_codes' => ['205041','205042','205043','205044','205045'],
                            ],
                            [
                                'name'     => 'MANJAI KUNDA GIDDA',
                                'lat'      => 13.44100,
                                'lng'      => -16.70450,
                                'voters'   => 280,
                                'ps_codes' => ['205081','205082'],
                            ],
                            [
                                'name'     => 'KOTU QUARRY',
                                'lat'      => 13.44650,
                                'lng'      => -16.70100,
                                'voters'   => 560,
                                'ps_codes' => ['205091','205092','205093','205094'],
                            ],
                            [
                                'name'     => 'KOTU POWER STATION (MASJID SARA MACO5)',
                                'lat'      => 13.45150,
                                'lng'      => -16.69800,
                                'voters'   => 140,
                                'ps_codes' => ['205101'],
                            ],
                            [
                                'name'     => 'KOTU LAYOUT (FOOTBALL FIELD)',
                                'lat'      => 13.45500,
                                'lng'      => -16.70300,
                                'voters'   => 420,
                                'ps_codes' => ['205111','205112','205113'],
                            ],
                            [
                                'name'     => 'MANJAI SANCHABA (LEADER\'S LBS)',
                                'lat'      => 13.44350,
                                'lng'      => -16.70650,
                                'voters'   => 140,
                                'ps_codes' => ['205121'],
                            ],
                            [
                                'name'     => 'ERENJANG NURS. SCH',
                                'lat'      => 13.44500,
                                'lng'      => -16.69300,
                                'voters'   => 140,
                                'ps_codes' => ['205131'],
                            ],
                        ],
                    ],

                    [
                        'name'     => 'KOLOLI',
                        'code'     => 'KMC-SW-KO',
                        'stations' => [
                            [
                                'name'     => 'KOTU COMMUNITY CENTRE',
                                'lat'      => 13.45800,
                                'lng'      => -16.70800,
                                'voters'   => 280,
                                'ps_codes' => ['205051','205052'],
                            ],
                            [
                                'name'     => 'KOLOLI MOSQUE',
                                'lat'      => 13.45650,
                                'lng'      => -16.71400,
                                'voters'   => 700,
                                'ps_codes' => ['205061','205062','205063','205064','205065'],
                            ],
                            [
                                'name'     => 'KOLOLI (JOHANESS )',
                                'lat'      => 13.46100,
                                'lng'      => -16.71900,
                                'voters'   => 280,
                                'ps_codes' => ['205071','205072'],
                            ],
                        ],
                    ],

                    [
                        'name'     => 'LATRIKUNDA YIRIGANYA',
                        'code'     => 'KMC-SW-LY',
                        'stations' => [
                            [
                                'name'     => 'LATRIKUNDA YIRINGANYA MOSQUE',
                                'lat'      => 13.44900,
                                'lng'      => -16.67950,
                                'voters'   => 560,
                                'ps_codes' => ['205141','205142','205143','205144'],
                            ],
                            [
                                'name'     => 'LATRIKUNDA YIRINGANYA OPP. MB SARR',
                                'lat'      => 13.44750,
                                'lng'      => -16.67750,
                                'voters'   => 280,
                                'ps_codes' => ['205151','205152'],
                            ],
                            [
                                'name'     => 'CIDER CLUB',
                                'lat'      => 13.45200,
                                'lng'      => -16.67600,
                                'voters'   => 140,
                                'ps_codes' => ['205161'],
                            ],
                            [
                                'name'     => 'PIPELINE MOSQUE',
                                'lat'      => 13.45100,
                                'lng'      => -16.68150,
                                'voters'   => 420,
                                'ps_codes' => ['205171','205172','205173'],
                            ],
                            [
                                'name'     => 'LATRIKUNDA UPPER BASIC SCH .',
                                'lat'      => 13.44850,
                                'lng'      => -16.68300,
                                'voters'   => 140,
                                'ps_codes' => ['205181'],
                            ],
                            [
                                'name'     => 'SEREKUNDA WEST MINI STADIUM',
                                'lat'      => 13.44700,
                                'lng'      => -16.68500,
                                'voters'   => 560,
                                'ps_codes' => ['205191','205192','205193','205194'],
                            ],
                        ],
                    ],

                    [
                        'name'     => 'BAKOTEH',
                        'code'     => 'KMC-SW-BT',
                        'stations' => [
                            [
                                'name'     => 'BAKOTEH BANTABA',
                                'lat'      => 13.42800,
                                'lng'      => -16.69450,
                                'voters'   => 700,
                                'ps_codes' => ['205221','205222','205223','205224','205225'],
                            ],
                            [
                                'name'     => 'BAKOTEH BOREHOLE',
                                'lat'      => 13.42200,
                                'lng'      => -16.69900,
                                'voters'   => 420,
                                'ps_codes' => ['205231','205232','205233'],
                            ],
                            [
                                'name'     => 'BAKOTEH LOWER BASIC SCH.',
                                'lat'      => 13.42500,
                                'lng'      => -16.69700,
                                'voters'   => 420,
                                'ps_codes' => ['205241','205242','205243'],
                            ],
                            [
                                'name'     => 'BAKOTEH LAYOUT',
                                'lat'      => 13.43100,
                                'lng'      => -16.69600,
                                'voters'   => 560,
                                'ps_codes' => ['205251','205252','205253','205254'],
                            ],
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             *  206xxx  JESHWANG
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name'  => 'JESHWANG',
                'code'  => 'KMC-JE',
                'wards' => [

                    [
                        'name'     => 'NEW JESHWANG/EBO TOWN',
                        'code'     => 'KMC-JE-NJ',
                        'stations' => [
                            [
                                'name'     => 'EBO TOWN MOSQUE',
                                'lat'      => 13.42750,
                                'lng'      => -16.64300,
                                'voters'   => 560,
                                'ps_codes' => ['206011','206012','206013','206014'],
                            ],
                            [
                                'name'     => 'KUSSABIA MOSQUE',
                                'lat'      => 13.42900,
                                'lng'      => -16.64150,
                                'voters'   => 280,
                                'ps_codes' => ['206021','206022'],
                            ],
                            [
                                'name'     => 'EBO TOWN BADALA',
                                'lat'      => 13.42600,
                                'lng'      => -16.64500,
                                'voters'   => 280,
                                'ps_codes' => ['206031','206032'],
                            ],
                            [
                                'name'     => 'EBO TOWN BIG TREE',
                                'lat'      => 13.43150,
                                'lng'      => -16.64400,
                                'voters'   => 280,
                                'ps_codes' => ['206041','206042'],
                            ],
                            [
                                'name'     => 'NEW JESHWANG (COTTON STREET)',
                                'lat'      => 13.43550,
                                'lng'      => -16.64800,
                                'voters'   => 420,
                                'ps_codes' => ['206071','206072','206073'],
                            ],
                            [
                                'name'     => 'EBO TOWN SANCHABA',
                                'lat'      => 13.43300,
                                'lng'      => -16.64650,
                                'voters'   => 560,
                                'ps_codes' => ['206081','206082','206083','206084'],
                            ],
                            [
                                'name'     => 'NEW JESHWANG MOSQUE',
                                'lat'      => 13.43800,
                                'lng'      => -16.65100,
                                'voters'   => 280,
                                'ps_codes' => ['206131','206132'],
                            ],
                            [
                                'name'     => 'ABC SCHOOLS',
                                'lat'      => 13.44050,
                                'lng'      => -16.64950,
                                'voters'   => 140,
                                'ps_codes' => ['206141'],
                            ],
                            [
                                'name'     => 'NURU ARABIC SCH (SANKUNG SILLAH)',
                                'lat'      => 13.43600,
                                'lng'      => -16.65300,
                                'voters'   => 140,
                                'ps_codes' => ['206151'],
                            ],
                            [
                                'name'     => 'NEW JESHWANG BANTABA',
                                'lat'      => 13.43900,
                                'lng'      => -16.65500,
                                'voters'   => 420,
                                'ps_codes' => ['206181','206182','206183'],
                            ],
                            [
                                'name'     => 'NEW JESHWANG (KANDIBA) LOWER BASIC SCH.',
                                'lat'      => 13.44150,
                                'lng'      => -16.65250,
                                'voters'   => 420,
                                'ps_codes' => ['206191','206192','206193'],
                            ],
                        ],
                    ],

                    [
                        'name'     => 'OLD JESHWANG',
                        'code'     => 'KMC-JE-OJ',
                        'stations' => [
                            [
                                'name'     => 'KANIFING LAYOUT',
                                'lat'      => 13.44800,
                                'lng'      => -16.66200,
                                'voters'   => 280,
                                'ps_codes' => ['206051','206052'],
                            ],
                            [
                                'name'     => 'KANIFING ESTATE COMM. CENTRE',
                                'lat'      => 13.44600,
                                'lng'      => -16.66600,
                                'voters'   => 420,
                                'ps_codes' => ['206061','206062','206063'],
                            ],
                            // estimated — no published coordinate
                            [
                                'name'     => 'OLD JESHWANG JALATALI (BLACK SCHOOL ARABIC)',
                                'lat'      => 13.44250, // estimated
                                'lng'      => -16.65950, // estimated
                                'voters'   => 140,
                                'ps_codes' => ['206111'],
                            ],
                            [
                                'name'     => 'OLD JESHWANG (LIVING CHILDREN ACADEMY)',
                                'lat'      => 13.44220, // estimated
                                'lng'      => -16.65880, // estimated
                                'voters'   => 280,
                                'ps_codes' => ['206121','206122'],
                            ],
                            [
                                'name'     => 'OLD JESHWANG LOWER BASIC SCH',
                                'lat'      => 13.44180, // estimated
                                'lng'      => -16.65820, // estimated
                                'voters'   => 560,
                                'ps_codes' => ['206161','206162','206163','206164'],
                            ],
                        ],
                    ],

                    [
                        'name'     => 'KANIFING',
                        'code'     => 'KMC-JE-KF',
                        'stations' => [
                            // estimated
                            [
                                'name'     => 'SEREKUNDA POST OFFICE',
                                'lat'      => 13.44350, // estimated
                                'lng'      => -16.67720, // estimated
                                'voters'   => 140,
                                'ps_codes' => ['206091'],
                            ],
                            [
                                'name'     => 'LATRIKUNDA LOWER BASIC SCH.',
                                'lat'      => 13.44920, // estimated
                                'lng'      => -16.68080, // estimated
                                'voters'   => 140,
                                'ps_codes' => ['206101'],
                            ],
                            [
                                'name'     => 'KANIFING SOUTH MOSQUE',
                                'lat'      => 13.44750, // estimated
                                'lng'      => -16.66450, // estimated
                                'voters'   => 420,
                                'ps_codes' => ['206171','206172','206173'],
                            ],
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             *  207xxx  BAKAU
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name'  => 'BAKAU',
                'code'  => 'KMC-BA',
                'wards' => [

                    [
                        'name'     => 'BAKAU/CAPE POINT',
                        'code'     => 'KMC-BA-CP',
                        'stations' => [
                            // estimated — near Bakau Cape Point area
                            [
                                'name'     => 'WASULUNKUNDA BANTANG KOTO',
                                'lat'      => 13.47480, // estimated
                                'lng'      => -16.66980, // estimated
                                'voters'   => 280,
                                'ps_codes' => ['207011','207012'],
                            ],
                            [
                                'name'     => 'KACHIKALLY CINEMA',
                                'lat'      => 13.47550,
                                'lng'      => -16.67050,
                                'voters'   => 280,
                                'ps_codes' => ['207021','207022'],
                            ],
                            [
                                'name'     => 'BAKAU FAROKONO',
                                'lat'      => 13.47950,
                                'lng'      => -16.66900,
                                'voters'   => 280,
                                'ps_codes' => ['207031','207032'],
                            ],
                            [
                                'name'     => 'MAMA KOTO ROAD',
                                'lat'      => 13.48150,
                                'lng'      => -16.66750,
                                'voters'   => 420,
                                'ps_codes' => ['207041','207042','207043'],
                            ],
                            [
                                'name'     => 'CAPE POINT',
                                'lat'      => 13.48600,
                                'lng'      => -16.65800,
                                'voters'   => 280,
                                'ps_codes' => ['207051','207052'],
                            ],
                            [
                                'name'     => 'OLD BAKAU BANTABA',
                                'lat'      => 13.47700,
                                'lng'      => -16.67400,
                                'voters'   => 140,
                                'ps_codes' => ['207061'],
                            ],
                            [
                                'name'     => 'BAKAU COMMUNITY CENTRE',
                                'lat'      => 13.47450,
                                'lng'      => -16.67150,
                                'voters'   => 280,
                                'ps_codes' => ['207071','207072'],
                            ],
                        ],
                    ],

                    [
                        'name'     => 'BAKAU NEW TOWN/FAJARA',
                        'code'     => 'KMC-BA-NF',
                        'stations' => [
                            [
                                'name'     => 'BAKAU UBS (INDEPENDENCE STADIUM)',
                                'lat'      => 13.46800,
                                'lng'      => -16.67600,
                                'voters'   => 420,
                                'ps_codes' => ['207081','207082','207083'],
                            ],
                            [
                                'name'     => 'BAKAU LOWER BASIC SCH',
                                'lat'      => 13.47800,
                                'lng'      => -16.67200,
                                'voters'   => 140,
                                'ps_codes' => ['207091'],
                            ],
                            [
                                'name'     => 'NEW TOWN LOWER BASIC SCH.',
                                'lat'      => 13.47150,
                                'lng'      => -16.68100,
                                'voters'   => 420,
                                'ps_codes' => ['207101','207102','207103'],
                            ],
                            [
                                'name'     => 'FAJARA (AROUND SABENA JUNCTION)',
                                'lat'      => 13.46950,
                                'lng'      => -16.68500,
                                'voters'   => 280,
                                'ps_codes' => ['207111','207112'],
                            ],
                            [
                                'name'     => 'FAJARA HOTEL',
                                'lat'      => 13.47200,
                                'lng'      => -16.68900,
                                'voters'   => 140,
                                'ps_codes' => ['207121'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
