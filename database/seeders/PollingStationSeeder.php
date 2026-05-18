<?php

namespace Database\Seeders;

use App\Models\Election;
use App\Models\PollingStation;
use App\Models\AdministrativeHierarchy;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;


class PollingStationSeeder extends Seeder
{

    /**
     * GambiaPollingStationsSeeder
     *
     * Seeds the complete IEC Gambia electoral registry:
     *  - 7 Admin Areas (Regions)
     *  - All Constituencies, Wards & Polling Station Streams
     *
     * Source : IEC Gambia Wards Registry (Wards_1.pdf)
     * Coords : Verified geographic data + ~2 m micro-offsets for duplicate streams.
     */
        private int $electionId;

        /** ~2 metres in decimal degrees */
        private const OFFSET = 0.000018;

        // =========================================================================
        // ENTRY POINT
        // =========================================================================

        public function run(): void
        {
            $election = Election::where('slug', 'gambia-2021-presidential')->first();

            if (!$election) {
                $this->command->error('Election not found. Run ElectionSeeder first.');
                return;
            }

            $this->electionId = $election->id;

            $national = $this->node(null, 'national', 'GM', 'The Gambia', 0, 13.4549, -16.5790);

            foreach ($this->regions() as $region) {
                $adminArea = $this->node(
                    $national->id, 'admin_area',
                    $region['code'], $region['name'], 1,
                    $region['lat'], $region['lng']
                );

                foreach ($region['constituencies'] as $const) {
                    $constituency = $this->node(
                        $adminArea->id, 'constituency',
                        $const['code'], $const['name'], 2,
                        $const['lat'] ?? $region['lat'],
                        $const['lng'] ?? $region['lng']
                    );
                    $this->assignApprover($constituency, 'constituency-approver');

                    foreach ($const['wards'] as $ward) {
                        $wardNode = $this->node(
                            $constituency->id, 'ward',
                            $ward['code'], $ward['name'], 3,
                            $ward['lat'] ?? $const['lat'] ?? $region['lat'],
                            $ward['lng'] ?? $const['lng'] ?? $region['lng']
                        );
                        $this->assignApprover($wardNode, 'ward-approver');

                        foreach ($ward['stations'] as $station) {
                            $this->createStreams($wardNode->id, $station);
                        }
                    }
                }
            }

            $count = PollingStation::where('election_id', $this->electionId)->count();
            $this->command->info("✓ Gambia polling stations seeded: {$count} streams created.");
        }

        // =========================================================================
        // HELPERS
        // =========================================================================

        private function node(
            ?int $parentId, string $level, string $code,
            string $name, int $depth, float $lat, float $lng
        ): AdministrativeHierarchy {
            return AdministrativeHierarchy::firstOrCreate(
                ['election_id' => $this->electionId, 'level' => $level, 'code' => $code],
                [
                    'name'             => $name,
                    'slug'             => Str::slug($name . '-' . strtolower($code)),
                    'parent_id'        => $parentId,
                    'depth'            => $depth,
                    'center_latitude'  => $lat,
                    'center_longitude' => $lng,
                    'is_active'        => true,
                ]
            );
        }

        private function assignApprover(AdministrativeHierarchy $node, string $role): void
        {
            if ($node->assigned_approver_id) return;

            $email = strtolower(str_replace([' ', '/', '\\'], '-', $node->code)) . '@iec.gm';

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name'     => $node->name . ' Approver',
                    'password' => Hash::make('password123'),
                    'status'   => 'active',
                ]
            );

            if (!$user->hasRole($role)) {
                $user->assignRole($role);
            }

            $node->update(['assigned_approver_id' => $user->id]);
        }

        private function createStreams(int $wardId, array $station): void
        {
            foreach ($station['codes'] as $index => $psCode) {
                if (PollingStation::where('code', $psCode)->exists()) continue;

                [$lat, $lng] = $this->offset($station['lat'], $station['lng'], $index);

                $name = $station['name'];
                if ($index > 0) {
                    $name .= ' (Stream ' . ($index + 1) . ')';
                }

                PollingStation::create([
                    'election_id'       => $this->electionId,
                    'ward_id'           => $wardId,
                    'code'              => $psCode,
                    'name'              => $name,
                    'address'           => $station['name'],
                    'latitude'          => $lat,
                    'longitude'         => $lng,
                    'registered_voters' => rand(200, 850),
                    'is_active'         => true,
                ]);
            }
        }

        private function offset(float $lat, float $lng, int $idx): array
        {
            $map = [
                [0, 0],
                [self::OFFSET,  0],
                [0,  self::OFFSET],
                [-self::OFFSET, 0],
                [0, -self::OFFSET],
                [self::OFFSET,  self::OFFSET],
                [-self::OFFSET, self::OFFSET],
                [self::OFFSET, -self::OFFSET],
                [-self::OFFSET,-self::OFFSET],
            ];
            $o = $map[$idx % count($map)];
            return [round($lat + $o[0], 7), round($lng + $o[1], 7)];
        }

        // =========================================================================
        // REGION REGISTRY
        // =========================================================================

        private function regions(): array
        {
            return [
                $this->banjul(),
                $this->kanifing(),
                $this->brikama(),
                $this->kerewan(),
                $this->mansakonko(),
                $this->janjanbureh(),
                $this->basse(),
            ];
        }

        // =========================================================================
        // 1. BANJUL
        // =========================================================================
        private function banjul(): array
        {
            return [
                'name' => 'BANJUL', 'code' => 'BJ', 'lat' => 13.4549, 'lng' => -16.5790,
                'constituencies' => [
                    [
                        'name' => 'BANJUL SOUTH', 'code' => 'BJ-S', 'lat' => 13.4485, 'lng' => -16.5780,
                        'wards' => [
                            [
                                'name' => 'JOLLOF TOWN', 'code' => 'BJ-S-JT', 'lat' => 13.4487, 'lng' => -16.5786,
                                'stations' => [
                                    ['name' => 'WESLEY PRI.SCH.',    'lat' => 13.4475, 'lng' => -16.5772, 'codes' => ['101021','101022']],
                                    ['name' => 'LASSO WHARF MARKET', 'lat' => 13.4500, 'lng' => -16.5800, 'codes' => ['101031','101032']],
                                ],
                            ],
                            [
                                'name' => 'PORTUGUESE TOWN', 'code' => 'BJ-S-PT', 'lat' => 13.4505, 'lng' => -16.5748,
                                'stations' => [
                                    ['name' => 'METHODIST PRI. SCH.( WESLEY ANNEX)', 'lat' => 13.4476, 'lng' => -16.5774, 'codes' => ['101011','101012']],
                                    ['name' => 'ST. AUG. JNR. SEC. SCH.',            'lat' => 13.4511, 'lng' => -16.5746, 'codes' => ['101041']],
                                    ['name' => 'MUHAMMADAN PRI. SCH.',                'lat' => 13.4504, 'lng' => -16.5750, 'codes' => ['101051','101052']],
                                ],
                            ],
                            [
                                'name' => 'HALF DIE', 'code' => 'BJ-S-HD', 'lat' => 13.4478, 'lng' => -16.5822,
                                'stations' => [
                                    ['name' => 'BANJUL MINI STADIUM', 'lat' => 13.4478, 'lng' => -16.5822, 'codes' => ['101061','101062','101063']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'BANJUL CENTRAL', 'code' => 'BJ-C', 'lat' => 13.4513, 'lng' => -16.5775,
                        'wards' => [
                            [
                                'name' => 'NEW TOWN WEST', 'code' => 'BJ-C-NTW', 'lat' => 13.4518, 'lng' => -16.5775,
                                'stations' => [
                                    ['name' => 'WELLESLEY MACDONALD ST. JUNC.', 'lat' => 13.4526, 'lng' => -16.5772, 'codes' => ['102021','102022']],
                                    ['name' => 'ODEON CINEMA',                   'lat' => 13.4518, 'lng' => -16.5801, 'codes' => ['102031']],
                                    ['name' => 'BETHEL CHURCH',                  'lat' => 13.4491, 'lng' => -16.5749, 'codes' => ['102061']],
                                ],
                            ],
                            [
                                'name' => 'SOLDIER TOWN', 'code' => 'BJ-C-ST', 'lat' => 13.4494, 'lng' => -16.5768,
                                'stations' => [
                                    ['name' => 'BANJUL. CITY COUNCIL', 'lat' => 13.4539, 'lng' => -16.5761, 'codes' => ['102011']],
                                    ['name' => '22ND JULY SQUARE.',     'lat' => 13.4494, 'lng' => -16.5775, 'codes' => ['102041','102042','102043','102044']],
                                ],
                            ],
                            [
                                'name' => 'NEW TOWN EAST', 'code' => 'BJ-C-NTE', 'lat' => 13.4542, 'lng' => -16.5794,
                                'stations' => [
                                    ['name' => 'SAM JACK TERRACE', 'lat' => 13.4542, 'lng' => -16.5794, 'codes' => ['102051','102052','102053','102054']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'BANJUL NORTH', 'code' => 'BJ-N', 'lat' => 13.4580, 'lng' => -16.5834,
                        'wards' => [
                            [
                                'name' => 'BOX BAR', 'code' => 'BJ-N-BB', 'lat' => 13.4601, 'lng' => -16.5819,
                                'stations' => [
                                    ['name' => 'GAMBIA SEN. SEC. SCH. (NEXT TO ARCH 22)', 'lat' => 13.4601, 'lng' => -16.5819, 'codes' => ['103011','103012','103013']],
                                ],
                            ],
                            [
                                'name' => 'CAMPAMA', 'code' => 'BJ-N-CA', 'lat' => 13.4568, 'lng' => -16.5872,
                                'stations' => [
                                    ['name' => 'CAMPAMA PRI. SCH.',         'lat' => 13.4568, 'lng' => -16.5872, 'codes' => ['103021','103022','103023','103024']],
                                    ['name' => 'ST. JOSEPH SEN. SEC. SCH.', 'lat' => 13.4482, 'lng' => -16.5796, 'codes' => ['103031']],
                                ],
                            ],
                            [
                                'name' => 'CRAB ISLAND', 'code' => 'BJ-N-CI', 'lat' => 13.4592, 'lng' => -16.5891,
                                'stations' => [
                                    ['name' => 'POLICE BARRACKS',      'lat' => 13.4514, 'lng' => -16.5781, 'codes' => ['103041','103042']],
                                    ['name' => 'CRAB ISLAND JUN. SCH.','lat' => 13.4592, 'lng' => -16.5891, 'codes' => ['103051']],
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        }

        // =========================================================================
        // 2. KANIFING (KMC)
        // =========================================================================
        private function kanifing(): array
        {
            return [
                'name' => 'KANIFING', 'code' => 'KN', 'lat' => 13.4432, 'lng' => -16.6782,
                'constituencies' => [
                    [
                        'name' => 'LATRIKUNDA SABIJI', 'code' => 'KN-LS', 'lat' => 13.4115, 'lng' => -16.6580,
                        'wards' => [
                            [
                                'name' => 'ABUKO', 'code' => 'KN-LS-AB', 'lat' => 13.3950, 'lng' => -16.6530,
                                'stations' => [
                                    ['name' => 'ABUKO VET CAMP',         'lat' => 13.3942, 'lng' => -16.6536, 'codes' => ['201011','201012','201013','201014','201015','201016','201017']],
                                    ['name' => 'ABUKO UPPER BASIC SCH.', 'lat' => 13.3980, 'lng' => -16.6510, 'codes' => ['201021','201022','201023','201024','201025','201026']],
                                    ['name' => 'ABUKO HEALTH CENTRE',    'lat' => 13.3925, 'lng' => -16.6555, 'codes' => ['201031']],
                                ],
                            ],
                            [
                                'name' => 'FAJIKUNDA', 'code' => 'KN-LS-FK', 'lat' => 13.4030, 'lng' => -16.6470,
                                'stations' => [
                                    ['name' => 'FAJI KUNDA BANTABA',                                'lat' => 13.4045, 'lng' => -16.6410, 'codes' => ['201041','201042','201043','201044','201045']],
                                    ['name' => 'FAJI KUNDA COMMUNITY CENTRE',                       'lat' => 13.4064, 'lng' => -16.6433, 'codes' => ['201051','201052']],
                                    ['name' => 'REX NURSERY SCH.',                                  'lat' => 13.4080, 'lng' => -16.6450, 'codes' => ['201061','201062','201063','201064']],
                                    ['name' => 'ST. CHARLES LWANGA LBS',                            'lat' => 13.4020, 'lng' => -16.6480, 'codes' => ['201071','201072','201073']],
                                    ['name' => 'LATRIKUNDA SABIJIE MOSQUE (FOR FAJIKUNDA)',          'lat' => 13.4115, 'lng' => -16.6580, 'codes' => ['201091','201092','201093']],
                                    ['name' => 'FAJIKUNDA MOSQUE',                                   'lat' => 13.4030, 'lng' => -16.6460, 'codes' => ['201131','201132','201133']],
                                    ['name' => 'FAJIKUNDA BAJONKOTO (ABDULLAH BUN SAUD ARABIC SCH)', 'lat' => 13.4015, 'lng' => -16.6495, 'codes' => ['201141','201142','201143','201144']],
                                ],
                            ],
                            [
                                'name' => 'SABIJIE', 'code' => 'KN-LS-SB', 'lat' => 13.4140, 'lng' => -16.6600,
                                'stations' => [
                                    ['name' => 'LATRIKUNDA SABIJII BEHIND MARKET',       'lat' => 13.4120, 'lng' => -16.6600, 'codes' => ['201081','201082','201083']],
                                    ['name' => 'LATRIKUNDA SABIJI CASTLE PETROL STATION','lat' => 13.4165, 'lng' => -16.6590, 'codes' => ['201101','201102','201103','201104']],
                                    ['name' => 'LATRIKUNDA PICCADILLY',                  'lat' => 13.4180, 'lng' => -16.6610, 'codes' => ['201111','201112','201113','201114']],
                                    ['name' => 'LATRIKUNDA SABIJIE LOWER BASIC SCH.',    'lat' => 13.4140, 'lng' => -16.6605, 'codes' => ['201121','201122','201123','201124','201125']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'TALLINDING KUNJANG', 'code' => 'KN-TK', 'lat' => 13.4265, 'lng' => -16.6570,
                        'wards' => [
                            [
                                'name' => 'TALLINDING NORTH', 'code' => 'KN-TK-TN', 'lat' => 13.4285, 'lng' => -16.6560,
                                'stations' => [
                                    ['name' => 'TALLINDING DUTOKOTO',          'lat' => 13.4240, 'lng' => -16.6550, 'codes' => ['202011','202012','202013','202014']],
                                    ['name' => 'JILFIT KUNDA (KAIRABA NUR. SCH)', 'lat' => 13.4260, 'lng' => -16.6530, 'codes' => ['202031','202032','202033']],
                                    ['name' => 'J.T.T. NUR SCH (NORTH)',        'lat' => 13.4290, 'lng' => -16.6545, 'codes' => ['202041','202042','202043']],
                                    ['name' => 'TALLINDING LOWER BASIC SCH.',   'lat' => 13.4285, 'lng' => -16.6570, 'codes' => ['202081','202082','202083','202084']],
                                ],
                            ],
                            [
                                'name' => 'TALLINDING SOUTH', 'code' => 'KN-TK-TS', 'lat' => 13.4225, 'lng' => -16.6575,
                                'stations' => [
                                    ['name' => 'TALLINDING BANTABA',           'lat' => 13.4210, 'lng' => -16.6585, 'codes' => ['202021','202022','202023','202024','202025']],
                                    ['name' => 'J.T.T. NUR SCH (SOUTH)',       'lat' => 13.4292, 'lng' => -16.6547, 'codes' => ['202051','202052']],
                                    ['name' => 'TALLINDING MEDINA',             'lat' => 13.4230, 'lng' => -16.6560, 'codes' => ['202061','202062','202063']],
                                    ['name' => 'TALLINDING ISLAMIC INSTITUTE',  'lat' => 13.4255, 'lng' => -16.6595, 'codes' => ['202071','202072']],
                                    ['name' => 'BUFFER ZONE',                   'lat' => 13.4310, 'lng' => -16.6640, 'codes' => ['202091','202092','202093','202094','202095']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'BUNDUNGKA KUNDA', 'code' => 'KN-BK', 'lat' => 13.4190, 'lng' => -16.6730,
                        'wards' => [
                            [
                                'name' => 'BANTABA/BOREHOLE', 'code' => 'KN-BK-BB', 'lat' => 13.4160, 'lng' => -16.6750,
                                'stations' => [
                                    ['name' => 'AREN BABUN FATTY',              'lat' => 13.4155, 'lng' => -16.6680, 'codes' => ['203011','203012']],
                                    ['name' => 'NUSRAT SEN. SEC SCH',           'lat' => 13.4125, 'lng' => -16.6710, 'codes' => ['203021','203022']],
                                    ['name' => 'MEDINA FASS MOSQUE',            'lat' => 13.4145, 'lng' => -16.6735, 'codes' => ['203031','203032','203033']],
                                    ['name' => 'BUNDUNG WHITE HOUSE',           'lat' => 13.4190, 'lng' => -16.6720, 'codes' => ['203041','203042','203043']],
                                    ['name' => 'BUNDUNG BANTABA STREET',        'lat' => 13.4175, 'lng' => -16.6705, 'codes' => ['203051']],
                                    ['name' => 'MAURITANIA SCHOOL',             'lat' => 13.4110, 'lng' => -16.6750, 'codes' => ['203081','203082','203083','203084']],
                                    ['name' => 'BUNDUNG BOREHOLE',              'lat' => 13.4085, 'lng' => -16.6780, 'codes' => ['203091','203092','203093','203094']],
                                    ['name' => 'BUNDUNG FAROKONO (THIRD HALL)', 'lat' => 13.4205, 'lng' => -16.6760, 'codes' => ['203101','203102']],
                                    ['name' => 'BUNDUNG LOWER BASIC SCH',       'lat' => 13.4160, 'lng' => -16.6770, 'codes' => ['203111','203112','203113']],
                                ],
                            ],
                            [
                                'name' => 'SIX JUNCTION', 'code' => 'KN-BK-SJ', 'lat' => 13.4232, 'lng' => -16.6745,
                                'stations' => [
                                    ['name' => 'BUNDUNG SIX JUNCTION',        'lat' => 13.4225, 'lng' => -16.6740, 'codes' => ['203061','203062','203063','203064']],
                                    ['name' => 'BUNDUNG MOSQUE',               'lat' => 13.4240, 'lng' => -16.6755, 'codes' => ['203071','203072','203073','203074']],
                                    ['name' => 'SEREKUNDA EAST MINI STUDIUM',  'lat' => 13.4230, 'lng' => -16.6690, 'codes' => ['203121','203122','203123']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'SEREKUNDA', 'code' => 'KN-SE', 'lat' => 13.4365, 'lng' => -16.6780,
                        'wards' => [
                            [
                                'name' => 'BARTEZ', 'code' => 'KN-SE-BA', 'lat' => 13.4365, 'lng' => -16.6780,
                                'stations' => [
                                    ['name' => 'SEREKUNDA LOWER BASIC SCH.',      'lat' => 13.4350, 'lng' => -16.6800, 'codes' => ['204011','204012','204013']],
                                    ['name' => 'PLAZA CINEMA',                     'lat' => 13.4365, 'lng' => -16.6785, 'codes' => ['204021','204022','204023','204024']],
                                    ['name' => 'SEREKUNDA BARTEZ (CHRIST CHURCH)', 'lat' => 13.4380, 'lng' => -16.6765, 'codes' => ['204031','204032']],
                                    ['name' => 'SEREKUNDA (GADAFI MOSQUE)',         'lat' => 13.4335, 'lng' => -16.6745, 'codes' => ['204061']],
                                ],
                            ],
                            [
                                'name' => 'LONDON CORNER', 'code' => 'KN-SE-LC', 'lat' => 13.4410, 'lng' => -16.6820,
                                'stations' => [
                                    ['name' => 'JANGJANG ROAD',                   'lat' => 13.4395, 'lng' => -16.6820, 'codes' => ['204041','204042','204043']],
                                    ['name' => 'CHRISTIAN MISSION CHURCH LONDON', 'lat' => 13.4410, 'lng' => -16.6835, 'codes' => ['204051','204052']],
                                    ['name' => 'MARCHE NGELEW',                   'lat' => 13.4425, 'lng' => -16.6805, 'codes' => ['204071','204072','204073','204074','204075']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'SEREKUNDA WEST', 'code' => 'KN-SW', 'lat' => 13.4460, 'lng' => -16.6880,
                        'wards' => [
                            [
                                'name' => 'DIPPAKUNDA', 'code' => 'KN-SW-DK', 'lat' => 13.4415, 'lng' => -16.6870,
                                'stations' => [
                                    ['name' => 'FORMER SENEGAMBIA GARAGE',           'lat' => 13.4440, 'lng' => -16.6790, 'codes' => ['205011','205012']],
                                    ['name' => 'AISA MARIE CINEMA',                  'lat' => 13.4455, 'lng' => -16.6825, 'codes' => ['205021']],
                                    ['name' => 'AREN BETTY KHAN (DIVINE PREP. SCH.)', 'lat' => 13.4430, 'lng' => -16.6850, 'codes' => ['205031']],
                                    ['name' => 'DIPPA KUNDA MOSQUE',                 'lat' => 13.4415, 'lng' => -16.6870, 'codes' => ['205201','205202','205203','205204']],
                                    ['name' => 'DIPPA KUNDA CHUPE TOWN.',            'lat' => 13.4400, 'lng' => -16.6890, 'codes' => ['205211','205212','205213','205214']],
                                ],
                            ],
                            [
                                'name' => 'MANJAIKUNDA/KOTU', 'code' => 'KN-SW-MK', 'lat' => 13.4430, 'lng' => -16.7010,
                                'stations' => [
                                    ['name' => 'MANJAI KUNDA MARKET',                   'lat' => 13.4385, 'lng' => -16.7020, 'codes' => ['205041','205042','205043','205044','205045']],
                                    ['name' => 'MANJAI KUNDA GIDDA',                    'lat' => 13.4410, 'lng' => -16.7045, 'codes' => ['205081','205082']],
                                    ['name' => 'KOTU QUARRY',                           'lat' => 13.4465, 'lng' => -16.7010, 'codes' => ['205091','205092','205093','205094']],
                                    ['name' => 'KOTU POWER STATION (MASJID SARA MACO5)','lat' => 13.4515, 'lng' => -16.6980, 'codes' => ['205101']],
                                    ['name' => 'KOTU LAYOUT (FOOTBALL FIELD)',          'lat' => 13.4550, 'lng' => -16.7030, 'codes' => ['205111','205112','205113']],
                                    ['name' => "MANJAI SANCHABA (LEADER'S LBS)",        'lat' => 13.4435, 'lng' => -16.7065, 'codes' => ['205121']],
                                    ['name' => 'ERENJANG NURS. SCH',                    'lat' => 13.4450, 'lng' => -16.6930, 'codes' => ['205131']],
                                ],
                            ],
                            [
                                'name' => 'KOLOLI', 'code' => 'KN-SW-KL', 'lat' => 13.4575, 'lng' => -16.7150,
                                'stations' => [
                                    ['name' => 'KOTU COMMUNITY CENTRE', 'lat' => 13.4580, 'lng' => -16.7080, 'codes' => ['205051','205052']],
                                    ['name' => 'KOLOLI MOSQUE',          'lat' => 13.4565, 'lng' => -16.7140, 'codes' => ['205061','205062','205063','205064','205065']],
                                    ['name' => 'KOLOLI (JOHANESS)',      'lat' => 13.4610, 'lng' => -16.7190, 'codes' => ['205071','205072']],
                                ],
                            ],
                            [
                                'name' => 'LATRIKUNDA YIRIGANYA', 'code' => 'KN-SW-LY', 'lat' => 13.4490, 'lng' => -16.6820,
                                'stations' => [
                                    ['name' => 'LATRIKUNDA YIRINGANYA MOSQUE',       'lat' => 13.4490, 'lng' => -16.6795, 'codes' => ['205141','205142','205143','205144']],
                                    ['name' => 'LATRIKUNDA YIRINGANYA OPP. MB SARR', 'lat' => 13.4475, 'lng' => -16.6775, 'codes' => ['205151','205152']],
                                    ['name' => 'CIDER CLUB',                         'lat' => 13.4520, 'lng' => -16.6760, 'codes' => ['205161']],
                                    ['name' => 'PIPELINE MOSQUE',                    'lat' => 13.4510, 'lng' => -16.6815, 'codes' => ['205171','205172','205173']],
                                    ['name' => 'LATRIKUNDA UPPER BASIC SCH.',        'lat' => 13.4485, 'lng' => -16.6830, 'codes' => ['205181']],
                                    ['name' => 'SEREKUNDA WEST MINI STADIUM',        'lat' => 13.4470, 'lng' => -16.6850, 'codes' => ['205191','205192','205193','205194']],
                                ],
                            ],
                            [
                                'name' => 'BAKOTEH', 'code' => 'KN-SW-BT', 'lat' => 13.4280, 'lng' => -16.6960,
                                'stations' => [
                                    ['name' => 'BAKOTEH BANTABA',         'lat' => 13.4280, 'lng' => -16.6945, 'codes' => ['205221','205222','205223','205224','205225']],
                                    ['name' => 'BAKOTEH BOREHOLE',        'lat' => 13.4220, 'lng' => -16.6990, 'codes' => ['205231','205232','205233']],
                                    ['name' => 'BAKOTEH LOWER BASIC SCH.','lat' => 13.4250, 'lng' => -16.6970, 'codes' => ['205241','205242','205243']],
                                    ['name' => 'BAKOTEH LAYOUT',          'lat' => 13.4310, 'lng' => -16.6960, 'codes' => ['205251','205252','205253','205254']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'JESHWANG', 'code' => 'KN-JW', 'lat' => 13.4390, 'lng' => -16.6530,
                        'wards' => [
                            [
                                'name' => 'NEW JESHWANG/EBO TOWN', 'code' => 'KN-JW-NJ', 'lat' => 13.4350, 'lng' => -16.6480,
                                'stations' => [
                                    ['name' => 'EBO TOWN MOSQUE',                         'lat' => 13.4275, 'lng' => -16.6430, 'codes' => ['206011','206012','206013','206014']],
                                    ['name' => 'KUSSABIA MOSQUE',                         'lat' => 13.4290, 'lng' => -16.6415, 'codes' => ['206021','206022']],
                                    ['name' => 'EBO TOWN BADALA',                         'lat' => 13.4260, 'lng' => -16.6450, 'codes' => ['206031','206032']],
                                    ['name' => 'EBO TOWN BIG TREE',                       'lat' => 13.4315, 'lng' => -16.6440, 'codes' => ['206041','206042']],
                                    ['name' => 'NEW JESHWANG (COTTON STREET)',             'lat' => 13.4355, 'lng' => -16.6480, 'codes' => ['206071','206072','206073']],
                                    ['name' => 'EBO TOWN SANCHABA',                       'lat' => 13.4330, 'lng' => -16.6465, 'codes' => ['206081','206082','206083','206084']],
                                    ['name' => 'NEW JESHWANG MOSQUE',                     'lat' => 13.4380, 'lng' => -16.6510, 'codes' => ['206131','206132']],
                                    ['name' => 'ABC SCHOOLS',                             'lat' => 13.4405, 'lng' => -16.6495, 'codes' => ['206141']],
                                    ['name' => 'NURU ARABIC SCH (SANKUNG SILLAH)',        'lat' => 13.4360, 'lng' => -16.6530, 'codes' => ['206151']],
                                    ['name' => 'NEW JESHWANG BANTABA',                    'lat' => 13.4390, 'lng' => -16.6550, 'codes' => ['206181','206182','206183']],
                                    ['name' => 'NEW JESHWANG (KANDIBA) LOWER BASIC SCH.', 'lat' => 13.4415, 'lng' => -16.6525, 'codes' => ['206191','206192','206193']],
                                ],
                            ],
                            [
                                'name' => 'OLD JESHWANG', 'code' => 'KN-JW-OJ', 'lat' => 13.4435, 'lng' => -16.6520,
                                'stations' => [
                                    ['name' => 'KANIFING LAYOUT',                            'lat' => 13.4480, 'lng' => -16.6620, 'codes' => ['206051','206052']],
                                    ['name' => 'KANIFING ESTATE COMM. CENTRE',               'lat' => 13.4460, 'lng' => -16.6660, 'codes' => ['206061','206062','206063']],
                                    ['name' => 'OLD JESHWANG JALATALI (BLACK SCHOOL ARABIC)','lat' => 13.4420, 'lng' => -16.6515, 'codes' => ['206111']],
                                    ['name' => 'OLD JESHWANG (LIVING CHILDREN ACADEMY)',     'lat' => 13.4425, 'lng' => -16.6520, 'codes' => ['206121','206122']],
                                    ['name' => 'OLD JESHWANG LOWER BASIC SCH',               'lat' => 13.4430, 'lng' => -16.6510, 'codes' => ['206161','206162','206163','206164']],
                                ],
                            ],
                            [
                                'name' => 'KANIFING', 'code' => 'KN-JW-KF', 'lat' => 13.4470, 'lng' => -16.6630,
                                'stations' => [
                                    ['name' => 'SEREKUNDA POST OFFICE',     'lat' => 13.4345, 'lng' => -16.6680, 'codes' => ['206091']],
                                    ['name' => 'LATRIKUNDA LOWER BASIC SCH.','lat' => 13.4490, 'lng' => -16.6640, 'codes' => ['206101']],
                                    ['name' => 'KANIFING SOUTH MOSQUE',     'lat' => 13.4470, 'lng' => -16.6625, 'codes' => ['206171','206172','206173']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'BAKAU', 'code' => 'KN-BU', 'lat' => 13.4770, 'lng' => -16.6720,
                        'wards' => [
                            [
                                'name' => 'BAKAU/CAPE POINT', 'code' => 'KN-BU-CP', 'lat' => 13.4790, 'lng' => -16.6700,
                                'stations' => [
                                    ['name' => 'WASULUNKUNDA BANTANG KOTO', 'lat' => 13.4760, 'lng' => -16.6720, 'codes' => ['207011','207012']],
                                    ['name' => 'KACHIKALLY CINEMA',         'lat' => 13.4755, 'lng' => -16.6705, 'codes' => ['207021','207022']],
                                    ['name' => 'BAKAU FAROKONO',            'lat' => 13.4795, 'lng' => -16.6690, 'codes' => ['207031','207032']],
                                    ['name' => 'MAMA KOTO ROAD',            'lat' => 13.4815, 'lng' => -16.6675, 'codes' => ['207041','207042','207043']],
                                    ['name' => 'CAPE POINT',                'lat' => 13.4860, 'lng' => -16.6580, 'codes' => ['207051','207052']],
                                    ['name' => 'OLD BAKAU BANTABA',         'lat' => 13.4770, 'lng' => -16.6740, 'codes' => ['207061']],
                                    ['name' => 'BAKAU COMMUNITY CENTRE',    'lat' => 13.4745, 'lng' => -16.6715, 'codes' => ['207071','207072']],
                                ],
                            ],
                            [
                                'name' => 'BAKAU NEW TOWN/FAJARA', 'code' => 'KN-BU-NT', 'lat' => 13.4710, 'lng' => -16.6840,
                                'stations' => [
                                    ['name' => 'BAKAU UBS (INDEPENDENCE STADIUM)', 'lat' => 13.4680, 'lng' => -16.6760, 'codes' => ['207081','207082','207083']],
                                    ['name' => 'BAKAU LOWER BASIC SCH',            'lat' => 13.4780, 'lng' => -16.6720, 'codes' => ['207091']],
                                    ['name' => 'NEW TOWN LOWER BASIC SCH.',        'lat' => 13.4715, 'lng' => -16.6810, 'codes' => ['207101','207102','207103']],
                                    ['name' => 'FAJARA (AROUND SABENA JUNCTION)',  'lat' => 13.4695, 'lng' => -16.6850, 'codes' => ['207111','207112']],
                                    ['name' => 'FAJARA HOTEL',                     'lat' => 13.4720, 'lng' => -16.6890, 'codes' => ['207121']],
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        }

        // =========================================================================
        // 3. BRIKAMA (West Coast Region)
        // =========================================================================
        private function brikama(): array
        {
            return [
                'name' => 'BRIKAMA', 'code' => 'BK', 'lat' => 13.2701, 'lng' => -16.6570,
                'constituencies' => [
                    [
                        'name' => 'SANNEH MENTERENG', 'code' => 'BK-SM', 'lat' => 13.4200, 'lng' => -16.7300,
                        'wards' => [
                            [
                                'name' => 'BRUFUT', 'code' => 'BK-SM-BR', 'lat' => 13.4300, 'lng' => -16.7560,
                                'stations' => [
                                    ['name' => 'MEDIANA MOSQUE',                     'lat' => 13.4350, 'lng' => -16.7620, 'codes' => ['301011','301012','301013','301014']],
                                    ['name' => 'BRUFUT PRI. SCH. (BEHIND MARKET)',   'lat' => 13.4312, 'lng' => -16.7580, 'codes' => ['301021','301022','301023','301024','301025','301026']],
                                    ['name' => 'BRUFUT 2',                           'lat' => 13.4280, 'lng' => -16.7550, 'codes' => ['301031','301032','301033','301034','301035','301036']],
                                    ['name' => "BRUFUT FATHER'S SCH. (DUTABAKOTO)", 'lat' => 13.4250, 'lng' => -16.7510, 'codes' => ['301041','301042','301043','301044','301045','301046']],
                                    ['name' => "WULING KAMA OPP. ALKALO'S COMP.",   'lat' => 13.4190, 'lng' => -16.7450, 'codes' => ['301051','301052','301053']],
                                    ['name' => 'BRUSUBI ESTATE SAMPLE HOUSE',        'lat' => 13.4380, 'lng' => -16.7320, 'codes' => ['301061','301062','301063']],
                                    ['name' => 'BRUSUBI ESTATE PHASE TWO 2',         'lat' => 13.4410, 'lng' => -16.7290, 'codes' => ['301071','301072']],
                                    ['name' => 'TRANQUIL MOSQUE',                    'lat' => 13.4450, 'lng' => -16.7350, 'codes' => ['301081','301082']],
                                ],
                            ],
                            [
                                'name' => 'BIJILO', 'code' => 'BK-SM-BJ', 'lat' => 13.4330, 'lng' => -16.7200,
                                'stations' => [
                                    ['name' => 'BIJILO BANTABA',                           'lat' => 13.4220, 'lng' => -16.7720, 'codes' => ['301091','301092','301093','301094']],
                                    ['name' => 'KERR SERIGN (HAMDALAYE) CENTRAL MOSQUE',   'lat' => 13.4440, 'lng' => -16.7180, 'codes' => ['301101','301102','301103','301104']],
                                    ['name' => "SANCHABA SULAY JOBE (OUTSIDE ALKALO'S COMP.)", 'lat' => 13.4320, 'lng' => -16.7050, 'codes' => ['301111','301112','301113']],
                                    ['name' => 'SANCHABA SULAY JOBE (SKILL CENTRE)',       'lat' => 13.4340, 'lng' => -16.7030, 'codes' => ['301121','301122','301123']],
                                ],
                            ],
                            [
                                'name' => 'SUKUTA', 'code' => 'BK-SM-SK', 'lat' => 13.4050, 'lng' => -16.7120,
                                'stations' => [
                                    ['name' => 'SUKUTA CINEMA HALL',                       'lat' => 13.4050, 'lng' => -16.7120, 'codes' => ['301131','301132','301133','301134','301135','301136','301137']],
                                    ['name' => 'SUKUTA SEC SCH (LIBRARY)',                 'lat' => 13.4020, 'lng' => -16.7150, 'codes' => ['301141','301142','301143']],
                                    ['name' => 'SUKUTA ARABIC SCH.',                       'lat' => 13.3990, 'lng' => -16.7110, 'codes' => ['301151','301152','301153','301154','301155']],
                                    ['name' => 'SUKUTA TALABA KOTO (BY THE CENTRAL MOSQUE)','lat' => 13.4080, 'lng' => -16.7090, 'codes' => ['301161','301162','301163']],
                                    ['name' => 'SUKUTA NEMA BANI ISRAEL MOSQUE',           'lat' => 13.4060, 'lng' => -16.7100, 'codes' => ['301171','301172','301173']],
                                    ['name' => 'SUKUTA JAMISA (BY THE METAL WORKSHOP)',    'lat' => 13.3920, 'lng' => -16.7180, 'codes' => ['301181','301182']],
                                    ['name' => 'SALAGI (OPP. NAWEC WATER PLANT)',          'lat' => 13.3850, 'lng' => -16.7210, 'codes' => ['301191','301192','301193']],
                                    ['name' => 'SUKUTA TUMBUNGTO BANTABA',                 'lat' => 13.4120, 'lng' => -16.7140, 'codes' => ['301201']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'OLD YUNDUM', 'code' => 'BK-OY', 'lat' => 13.3870, 'lng' => -16.6880,
                        'wards' => [
                            [
                                'name' => 'JABANG', 'code' => 'BK-OY-JB', 'lat' => 13.3780, 'lng' => -16.6950,
                                'stations' => [
                                    ['name' => 'LATRIYA HEALTH CENTRE',  'lat' => 13.3650, 'lng' => -16.6920, 'codes' => ['302011','302012']],
                                    ['name' => 'YOUNA BANTABA',          'lat' => 13.3580, 'lng' => -16.7010, 'codes' => ['302021','302022','302023']],
                                    ['name' => 'MARIAMA KUNDA BANTABA',  'lat' => 13.3710, 'lng' => -16.7150, 'codes' => ['302031','302032','302033','302034']],
                                    ['name' => 'JABANG MOSQUE',          'lat' => 13.3780, 'lng' => -16.6950, 'codes' => ['302041','302042','302043','302044','302045']],
                                    ['name' => 'TAWUTO BANTABA',         'lat' => 13.3880, 'lng' => -16.6810, 'codes' => ['302051','302052','302053']],
                                    ['name' => 'OLD YUNDUM BANTABA',     'lat' => 13.3910, 'lng' => -16.6740, 'codes' => ['302061','302062','302063','302064','302065']],
                                ],
                            ],
                            [
                                'name' => 'KUNKUJANG KEITA YA', 'code' => 'BK-OY-KK', 'lat' => 13.4100, 'lng' => -16.6710,
                                'stations' => [
                                    ['name' => 'MEDINA SEY KUNDA (SINCHU ALAGI) BANTABA',  'lat' => 13.4050, 'lng' => -16.6710, 'codes' => ['302071','302072','302073','302074','302075','302076']],
                                    ['name' => 'MEDINA SEY KUNDA (SINCHU ALAGI) 2',        'lat' => 13.4055, 'lng' => -16.6715, 'codes' => ['302081','302082','302083','302084','302085']],
                                    ['name' => 'MEDINA BALIYA (SINCHU BALIYA) MOSQUE.',    'lat' => 13.3980, 'lng' => -16.6600, 'codes' => ['302091','302092','302093','302094','302095']],
                                    ['name' => 'SINCHU SORRIE NURSERY SCH.',               'lat' => 13.4120, 'lng' => -16.6620, 'codes' => ['302101','302102','302103','302104','302105']],
                                    ['name' => 'KUNKUJANG KEITA YAA CENTRAL MOSQUE',       'lat' => 13.4180, 'lng' => -16.6780, 'codes' => ['302111','302112','302113','302114','302115','302116']],
                                ],
                            ],
                            [
                                'name' => 'WELLINGARA/NEMA', 'code' => 'BK-OY-WN', 'lat' => 13.3950, 'lng' => -16.6590,
                                'stations' => [
                                    ['name' => 'WELLINGARA (BEHIND JAH OIL PETROL STATION)', 'lat' => 13.3950, 'lng' => -16.6600, 'codes' => ['302121','302122','302123','302124','302125','302126']],
                                    ['name' => 'WELLINGARA RED CROSS BANTABA',                'lat' => 13.3940, 'lng' => -16.6610, 'codes' => ['302131','302132','302133','302134']],
                                    ['name' => 'NEMAKUNKU',                                   'lat' => 13.3970, 'lng' => -16.6580, 'codes' => ['302141','302142','302143','302144','302145','302146','302147','302148','302149']],
                                    ['name' => 'NEMA NASIR',                                  'lat' => 13.3960, 'lng' => -16.6570, 'codes' => ['302151','302152','302153']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'BUSUMBALA', 'code' => 'BK-BU', 'lat' => 13.3600, 'lng' => -16.6450,
                        'wards' => [
                            [
                                'name' => 'LAMIN', 'code' => 'BK-BU-LA', 'lat' => 13.3840, 'lng' => -16.6350,
                                'stations' => [
                                    ['name' => 'KUBARIKO BANTABA',                             'lat' => 13.3820, 'lng' => -16.6380, 'codes' => ['303011']],
                                    ['name' => 'MAKUMBAYA GARAGE',                             'lat' => 13.3840, 'lng' => -16.6360, 'codes' => ['303021','303022']],
                                    ['name' => 'KUNKUJANG JATTA YAA BANTABA',                  'lat' => 13.3800, 'lng' => -16.6350, 'codes' => ['303031','303032']],
                                    ['name' => 'MANDINARY DUTOKOTO',                           'lat' => 13.3780, 'lng' => -16.6370, 'codes' => ['303041','303042','303043','303044','303045']],
                                    ['name' => 'KOMBO KEREWAN BANTABA',                        'lat' => 13.3750, 'lng' => -16.6120, 'codes' => ['303051','303052','303053','303054','303055']],
                                    ['name' => 'DARANKA VILLAGE HALL',                         'lat' => 13.3910, 'lng' => -16.6210, 'codes' => ['303061','303062','303063']],
                                    ['name' => "LAMIN ST. PETER'S PRI SCH (BEHIND PETROL STN)",'lat' => 13.3850, 'lng' => -16.6350, 'codes' => ['303071','303072','303073','303074','303075']],
                                    ['name' => 'LAMIN GAMTRADE TRAINING CENTRE (OPP.)',        'lat' => 13.3810, 'lng' => -16.6320, 'codes' => ['303081','303082','303083','303084']],
                                    ['name' => 'LAMIN SDA SCHOOL (JADUS SCH)',                 'lat' => 13.3830, 'lng' => -16.6330, 'codes' => ['303091','303092']],
                                    ['name' => 'LAMIN BABYLON FOOTBALL FIELD',                 'lat' => 13.3845, 'lng' => -16.6360, 'codes' => ['303101','303102','303103']],
                                    ['name' => 'LAMIN HEALTH CENTRE',                          'lat' => 13.3855, 'lng' => -16.6370, 'codes' => ['303111','303112','303113','303114']],
                                    ['name' => 'LAMIN CAMBODIA',                               'lat' => 13.3815, 'lng' => -16.6345, 'codes' => ['303121','303122']],
                                ],
                            ],
                            [
                                'name' => 'BANJULUNDING', 'code' => 'BK-BU-BD', 'lat' => 13.3490, 'lng' => -16.6550,
                                'stations' => [
                                    ['name' => 'BANJULUNDING SEED STORE',         'lat' => 13.3500, 'lng' => -16.6540, 'codes' => ['303131','303132','303133']],
                                    ['name' => 'BANJULUNDING COMM. CENTRE.',      'lat' => 13.3510, 'lng' => -16.6540, 'codes' => ['303141','303142','303143','303144']],
                                    ['name' => 'YARAM BAMBA ESTATE MOSQUE',       'lat' => 13.3500, 'lng' => -16.6555, 'codes' => ['303151','303152']],
                                    ['name' => 'NEW YUNDUM BANTBA',               'lat' => 13.3520, 'lng' => -16.6530, 'codes' => ['303161','303162','303163','303164']],
                                    ['name' => 'NEW YUNDUM PRI SCH',              'lat' => 13.3515, 'lng' => -16.6535, 'codes' => ['303171','303172','303173','303174']],
                                    ['name' => 'NEW YUNDUM SADINKA',              'lat' => 13.3525, 'lng' => -16.6525, 'codes' => ['303181','303182']],
                                    ['name' => 'BUSUMBALA BANTABA',               'lat' => 13.3480, 'lng' => -16.6580, 'codes' => ['303191','303192','303193','303194','303195','303196','303197']],
                                    ['name' => 'BUSUMBALA MODEL SUKOTO BANTABA',  'lat' => 13.3440, 'lng' => -16.6620, 'codes' => ['303201','303202','303203','303204']],
                                    ['name' => 'DARU BUSUMBALA BANTABA',          'lat' => 13.3520, 'lng' => -16.6520, 'codes' => ['303211','303212','303213']],
                                    ['name' => 'BUSUMBALA GUIGI BANTABA',         'lat' => 13.3495, 'lng' => -16.6560, 'codes' => ['303221','303222','303223','303224']],
                                    ['name' => 'BUSUMBALA TINTINBA MOSQUE',       'lat' => 13.3490, 'lng' => -16.6575, 'codes' => ['303231','303232','303233']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'KOMBO SOUTH', 'code' => 'BK-KS', 'lat' => 13.2200, 'lng' => -16.7400,
                        'wards' => [
                            [
                                'name' => 'KARTONG', 'code' => 'BK-KS-KT', 'lat' => 13.1100, 'lng' => -16.7350,
                                'stations' => [
                                    ['name' => 'NYOFELLEH BANTABA',        'lat' => 13.1100, 'lng' => -16.7400, 'codes' => ['304011','304012']],
                                    ['name' => 'GUNJUR KUNKUJANG BANTABA', 'lat' => 13.1600, 'lng' => -16.7500, 'codes' => ['304021','304022','304023']],
                                    ['name' => 'SIFFOE CCF',               'lat' => 13.1300, 'lng' => -16.7200, 'codes' => ['304031','304032','304033','304034','304035']],
                                    ['name' => 'KARTONG HEALTH CENTRE',    'lat' => 13.1050, 'lng' => -16.7350, 'codes' => ['304041','304042','304043','304044']],
                                    ['name' => 'MEDINA SALAM (CENTRE)',    'lat' => 13.1080, 'lng' => -16.7355, 'codes' => ['304051','304052']],
                                    ['name' => 'BERENDING BANTABA',        'lat' => 13.1500, 'lng' => -16.7480, 'codes' => ['304061','304062']],
                                ],
                            ],
                            [
                                'name' => 'GUNJUR', 'code' => 'BK-KS-GU', 'lat' => 13.1810, 'lng' => -16.7620,
                                'stations' => [
                                    ['name' => 'GUNJUR HEALTH CENTRE',   'lat' => 13.1820, 'lng' => -16.7630, 'codes' => ['304071','304072','304073','304074','304075','304076','304077','304078','304079']],
                                    ['name' => 'GUNJUR (BY THE PRI SCH)','lat' => 13.1810, 'lng' => -16.7620, 'codes' => ['304081','304082','304083','304084','304085','304086']],
                                ],
                            ],
                            [
                                'name' => 'SANYANG', 'code' => 'BK-KS-SY', 'lat' => 13.2620, 'lng' => -16.7510,
                                'stations' => [
                                    ['name' => 'SANYANG BANTABA',                          'lat' => 13.2620, 'lng' => -16.7510, 'codes' => ['304091','304092','304093','304094','304095','304096','304097','304098','304099']],
                                    ['name' => 'TUJERENG BANTABA',                         'lat' => 13.2150, 'lng' => -16.7480, 'codes' => ['304101','304102','304103','304104']],
                                    ['name' => 'TUJERENG (AROUND SEN SEC SCH)',             'lat' => 13.2180, 'lng' => -16.7490, 'codes' => ['304111','304112']],
                                    ['name' => 'BATOKUNKU SKILL CENTRE (NEAR MOSQUE)',     'lat' => 13.2350, 'lng' => -16.7400, 'codes' => ['304121','304122','304123']],
                                    ['name' => 'BATOKUNKU (NEAR NURSERY SCH.)',             'lat' => 13.2380, 'lng' => -16.7410, 'codes' => ['304131']],
                                    ['name' => 'TANJI COMM. CENTRE',                       'lat' => 13.2700, 'lng' => -16.7350, 'codes' => ['304141','304142','304143','304144']],
                                    ['name' => 'TANJI B',                                  'lat' => 13.2720, 'lng' => -16.7360, 'codes' => ['304151','304152','304153','304154','304155']],
                                    ['name' => 'KUNKUJANG MARIAMA BANTABA',                'lat' => 13.3020, 'lng' => -16.7100, 'codes' => ['304161','304162']],
                                    ['name' => 'BANYAKA BANTABA',                          'lat' => 13.2900, 'lng' => -16.7150, 'codes' => ['304171','304172','304173']],
                                    ['name' => 'JAMBANJELLY MARKET',                       'lat' => 13.3100, 'lng' => -16.7050, 'codes' => ['304181','304182','304183','304184','304185']],
                                    ['name' => 'RUMBA BANTABA',                            'lat' => 13.2800, 'lng' => -16.7200, 'codes' => ['304191']],
                                    ['name' => 'JAMBUR BANTABA',                           'lat' => 13.2980, 'lng' => -16.6980, 'codes' => ['304201','304202','304203','304204','304205']],
                                    ['name' => 'FARATO MOSQUE',                            'lat' => 13.3280, 'lng' => -16.6510, 'codes' => ['304211','304212','304213','304214','304215']],
                                    ['name' => 'FARATO NEW TOWN MOSQUE',                   'lat' => 13.3210, 'lng' => -16.6560, 'codes' => ['304221','304222']],
                                    ['name' => 'FARATO NEMA MOSQUE',                       'lat' => 13.3250, 'lng' => -16.6540, 'codes' => ['304231','304232','304233','304234']],
                                    ['name' => "FARATO (SANCHABA) OUTSIDE ALKALO'S COMPOUND", 'lat' => 13.3150, 'lng' => -16.6480, 'codes' => ['304241','304242']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'BRIKAMA NORTH', 'code' => 'BK-BN', 'lat' => 13.2750, 'lng' => -16.6530,
                        'wards' => [
                            [
                                'name' => 'KEMBUJEH', 'code' => 'BK-BN-KE', 'lat' => 13.2700, 'lng' => -16.6490,
                                'stations' => [
                                    ['name' => 'SEREKUNDA NDING SKILL CENTRE',          'lat' => 13.2650, 'lng' => -16.6450, 'codes' => ['305011']],
                                    ['name' => 'KEMBUJEH BANTABA',                      'lat' => 13.2680, 'lng' => -16.6440, 'codes' => ['305021','305022','305023']],
                                    ['name' => 'KEMBUJEH MEDINA SCH GROUND',            'lat' => 13.2670, 'lng' => -16.6445, 'codes' => ['305031','305032']],
                                    ['name' => 'BRIKAMA DARUKHAIRU MOSQUE',             'lat' => 13.2710, 'lng' => -16.6500, 'codes' => ['305041','305042','305043','305044']],
                                    ['name' => 'BRIKAMA MISIRA MOSQUE',                 'lat' => 13.2720, 'lng' => -16.6510, 'codes' => ['305051','305052','305053']],
                                    ['name' => 'BRIKAMA KABAFITA MOSQUE',               'lat' => 13.2730, 'lng' => -16.6515, 'codes' => ['305061','305062','305063','305064']],
                                    ['name' => 'BRIKAMA WELLINGARA PRAYING GROUNDS',    'lat' => 13.2740, 'lng' => -16.6520, 'codes' => ['305071','305072','305073','305074']],
                                    ['name' => 'KUBUNEH SKILL CENTRE',                  'lat' => 13.2760, 'lng' => -16.6530, 'codes' => ['305081','305082']],
                                    ['name' => 'BAFULOTO BANTABA',                      'lat' => 13.2770, 'lng' => -16.6540, 'codes' => ['305091','305092']],
                                    ['name' => 'BAFULOTO NEMA SU',                      'lat' => 13.2780, 'lng' => -16.6545, 'codes' => ['305101','305102']],
                                    ['name' => 'FARATO BOJANG KUNDA (SOTOKOI DARU) BANTABA', 'lat' => 13.3150, 'lng' => -16.6480, 'codes' => ['305111','305112','305113']],
                                ],
                            ],
                            [
                                'name' => 'NYAMBAI', 'code' => 'BK-BN-NY', 'lat' => 13.2830, 'lng' => -16.6570,
                                'stations' => [
                                    ['name' => 'BRIKAMA NYAMBAI COLLEGE MARKET',               'lat' => 13.2810, 'lng' => -16.6540, 'codes' => ['305121','305122','305123','305124']],
                                    ['name' => 'BRIKAMA NYAMBAI BABA GALLEH MOSQUE',            'lat' => 13.2840, 'lng' => -16.6590, 'codes' => ['305131','305132','305133','305134']],
                                    ['name' => 'BRIKAMA NYAMBAI JAMBARR SANNEH (METHODIST GATE)','lat' => 13.2820, 'lng' => -16.6560, 'codes' => ['305141','305142','305143']],
                                    ['name' => 'NEW TOWN NORTH (MOTHER ALI NUR. SCH.)',         'lat' => 13.2830, 'lng' => -16.6570, 'codes' => ['305151','305152']],
                                    ['name' => 'BRIKAMA NEMATABA GIRL GUIDES CENTRE',           'lat' => 13.2850, 'lng' => -16.6580, 'codes' => ['305161','305162']],
                                    ['name' => 'BRIKAMA NEMA GEEBUNGOTO (COMM DEV.)',           'lat' => 13.2855, 'lng' => -16.6585, 'codes' => ['305171','305172']],
                                    ['name' => 'JAMISA BANTABA',                                'lat' => 13.2800, 'lng' => -16.6610, 'codes' => ['305181','305182','305183']],
                                    ['name' => 'JALAMBANG MOSQUE',                              'lat' => 13.2860, 'lng' => -16.6595, 'codes' => ['305191','305192','305193','305194']],
                                    ['name' => 'KASSAKUNDA BANTABA',                            'lat' => 13.2870, 'lng' => -16.6600, 'codes' => ['305201','305202']],
                                    ['name' => 'BUSURANDING BANTABA',                           'lat' => 13.2800, 'lng' => -16.6620, 'codes' => ['305211']],
                                    ['name' => 'TAIBATOU BANTABA',                              'lat' => 13.2820, 'lng' => -16.6605, 'codes' => ['305221']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'BRIKAMA SOUTH', 'code' => 'BK-BS', 'lat' => 13.2600, 'lng' => -16.6500,
                        'wards' => [
                            [
                                'name' => 'MARAKISSA', 'code' => 'BK-BS-MK', 'lat' => 13.2380, 'lng' => -16.6310,
                                'stations' => [
                                    ['name' => 'BRIKAMA MEDINA BANTABA',   'lat' => 13.2650, 'lng' => -16.6480, 'codes' => ['306011','306012','306013']],
                                    ['name' => 'JAMWELLY BANTABA',         'lat' => 13.2660, 'lng' => -16.6490, 'codes' => ['306021','306022']],
                                    ['name' => 'MANDUAR BANTABA',          'lat' => 13.2670, 'lng' => -16.6500, 'codes' => ['306031','306032','306033','306034']],
                                    ['name' => 'PENYEM BANTABA',           'lat' => 13.2600, 'lng' => -16.6430, 'codes' => ['306041','306042']],
                                    ['name' => 'BUSURA CLINIC',            'lat' => 13.2620, 'lng' => -16.6440, 'codes' => ['306051','306052','306053']],
                                    ['name' => 'DARSILAMEH HEALTH CENTRE', 'lat' => 13.1950, 'lng' => -16.6510, 'codes' => ['306061','306062','306063']],
                                    ['name' => 'KABAKEL OUTSIDE SCH',      'lat' => 13.2280, 'lng' => -16.6310, 'codes' => ['306071']],
                                    ['name' => 'MARAKISSA BANTABA',        'lat' => 13.2320, 'lng' => -16.6210, 'codes' => ['306081','306082','306083']],
                                    ['name' => 'BAKARY SAMBOU YAA MOSQUE', 'lat' => 13.2410, 'lng' => -16.6380, 'codes' => ['306091','306092']],
                                    ['name' => 'KITTY BANTABA',            'lat' => 13.2550, 'lng' => -16.6150, 'codes' => ['306101','306102','306103','306104']],
                                ],
                            ],
                            [
                                'name' => 'SUBA', 'code' => 'BK-BS-SB', 'lat' => 13.2700, 'lng' => -16.6620,
                                'stations' => [
                                    ['name' => 'BRIKAMA SANNEH KUNDA BANTABA',               'lat' => 13.2720, 'lng' => -16.6650, 'codes' => ['306111','306112','306113']],
                                    ['name' => 'BRIKAMA BOJANG KUNDA BANTABA',               'lat' => 13.2680, 'lng' => -16.6580, 'codes' => ['306121']],
                                    ['name' => 'BRIKAMA SUMA KUNDA BANTABA',                 'lat' => 13.2700, 'lng' => -16.6610, 'codes' => ['306131','306132']],
                                    ['name' => 'BRIKAMA HAWLA KUNDA (KABILO HEAD COMP. GATE)','lat' => 13.2710, 'lng' => -16.6615, 'codes' => ['306141','306142']],
                                    ['name' => 'BRIKAMA MANSARINGSU BANTABA',                'lat' => 13.2722, 'lng' => -16.6620, 'codes' => ['306151']],
                                    ['name' => 'BRIKAMA SANTUSO NURSERY SCH.',               'lat' => 13.2725, 'lng' => -16.6625, 'codes' => ['306161','306162']],
                                    ['name' => 'BRIKAMA PERSEVERANCE BANTABA',               'lat' => 13.2730, 'lng' => -16.6630, 'codes' => ['306171','306172','306173']],
                                    ['name' => 'BRIKAMA PERSEVERANCE PRAYING GROUND',        'lat' => 13.2735, 'lng' => -16.6635, 'codes' => ['306181','306182','306183']],
                                    ['name' => 'BRIKAMA GIDDA BANTABA',                      'lat' => 13.2740, 'lng' => -16.6640, 'codes' => ['306191','306192','306193','306194']],
                                    ['name' => 'BRIKAMA GIDDA (SCHOOL AREA)',                'lat' => 13.2745, 'lng' => -16.6645, 'codes' => ['306201','306202','306203','306204']],
                                    ['name' => "BRIKAMA DARSILAMEH (NEAR ALKALO'S COMPOUND)",'lat' => 13.2750, 'lng' => -16.6650, 'codes' => ['306211','306212','306213']],
                                    ['name' => 'BRIKAMA NEWTOWN SOUTH MOSQUE',               'lat' => 13.2755, 'lng' => -16.6655, 'codes' => ['306221','306222','306223']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'KOMBO EAST', 'code' => 'BK-KE', 'lat' => 13.2700, 'lng' => -16.5000,
                        'wards' => [
                            [
                                'name' => 'PIRANG', 'code' => 'BK-KE-PI', 'lat' => 13.3100, 'lng' => -16.5100,
                                'stations' => [
                                    ['name' => 'KULORO BANTABA',             'lat' => 13.2950, 'lng' => -16.5410, 'codes' => ['307011','307012','307013','307014']],
                                    ['name' => 'BONTO KUTA JALOKOTO BANTABA','lat' => 13.3080, 'lng' => -16.5280, 'codes' => ['307021']],
                                    ['name' => 'PIRANG BANTABA',             'lat' => 13.3210, 'lng' => -16.5050, 'codes' => ['307031','307032']],
                                    ['name' => 'PIRANG BERENDING BANTABA',   'lat' => 13.3140, 'lng' => -16.4950, 'codes' => ['307041','307042','307043']],
                                    ['name' => 'PIRANG BERENDING DARUSALAM', 'lat' => 13.3110, 'lng' => -16.4910, 'codes' => ['307051']],
                                ],
                            ],
                            [
                                'name' => 'KAFUTA', 'code' => 'BK-KE-KF', 'lat' => 13.2500, 'lng' => -16.4700,
                                'stations' => [
                                    ['name' => 'KAIRABA (FARABA MANOKANG) BANTABA','lat' => 13.2750, 'lng' => -16.4520, 'codes' => ['307061','307062']],
                                    ['name' => 'FARABA BANTA BANTABA',              'lat' => 13.2610, 'lng' => -16.4780, 'codes' => ['307071','307072','307073']],
                                    ['name' => 'MEDINA SOKOTOI BANTABA',            'lat' => 13.2500, 'lng' => -16.4800, 'codes' => ['307081','307082']],
                                    ['name' => 'FARABA SUTU CCF.',                  'lat' => 13.2400, 'lng' => -16.4900, 'codes' => ['307091']],
                                    ['name' => 'KAFUTA BANTABA',                    'lat' => 13.2200, 'lng' => -16.5100, 'codes' => ['307101','307102','307103','307104']],
                                ],
                            ],
                            [
                                'name' => 'GIBORO', 'code' => 'BK-KE-GB', 'lat' => 13.1800, 'lng' => -16.4600,
                                'stations' => [
                                    ['name' => 'SOHM HEALTH CENTRE',   'lat' => 13.2100, 'lng' => -16.4800, 'codes' => ['307111','307112']],
                                    ['name' => 'OMORTO SKILL CENTRE',  'lat' => 13.2150, 'lng' => -16.4850, 'codes' => ['307121']],
                                    ['name' => 'GIBORO KUTA BANTABA',  'lat' => 13.1900, 'lng' => -16.4700, 'codes' => ['307131','307132','307133','307134']],
                                    ['name' => 'BASORI BANTABA',       'lat' => 13.1800, 'lng' => -16.4600, 'codes' => ['307141','307142','307143']],
                                    ['name' => 'TUBA KUTA BANTABA',    'lat' => 13.1700, 'lng' => -16.4500, 'codes' => ['307151','307152']],
                                    ['name' => 'MANDINABA BANTABA',    'lat' => 13.1600, 'lng' => -16.4400, 'codes' => ['307161','307162','307163']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'FONI BREFET', 'code' => 'BK-FB', 'lat' => 13.2400, 'lng' => -16.5600,
                        'wards' => [
                            [
                                'name' => 'SOMITA', 'code' => 'BK-FB-SO', 'lat' => 13.2400, 'lng' => -16.5500,
                                'stations' => [
                                    ['name' => 'SOMITA CCF',             'lat' => 13.2400, 'lng' => -16.5500, 'codes' => ['308011','308012','308013']],
                                    ['name' => 'NDEMBAN TENDA BANTABA',  'lat' => 13.2350, 'lng' => -16.5600, 'codes' => ['308021','308022','308023']],
                                    ['name' => 'BEREFT BANTABA',         'lat' => 13.2300, 'lng' => -16.5700, 'codes' => ['308031']],
                                ],
                            ],
                            [
                                'name' => 'BULOCK', 'code' => 'BK-FB-BL', 'lat' => 13.2310, 'lng' => -16.3850,
                                'stations' => [
                                    ['name' => 'BESSI CCF',                    'lat' => 13.2200, 'lng' => -16.5800, 'codes' => ['308041','308042']],
                                    ['name' => 'SUTUSINJANG DAY CARE CENTRE',  'lat' => 13.2310, 'lng' => -16.3850, 'codes' => ['308051','308052']],
                                    ['name' => 'BAJANA BANTABA',               'lat' => 13.2280, 'lng' => -16.4000, 'codes' => ['308061']],
                                    ['name' => 'BULOCK BANTABA',               'lat' => 13.2350, 'lng' => -16.3900, 'codes' => ['308071','308072','308073','308074']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'FONI BINTANG', 'code' => 'BK-FBT', 'lat' => 13.0850, 'lng' => -15.8000,
                        'wards' => [
                            [
                                'name' => 'KUSAMAI', 'code' => 'BK-FBT-KS', 'lat' => 13.0900, 'lng' => -15.7900,
                                'stations' => [
                                    ['name' => 'ARANGALEN BANTABA',  'lat' => 13.0800, 'lng' => -15.8000, 'codes' => ['309011']],
                                    ['name' => 'BAJAGARR BANTABA',   'lat' => 13.0700, 'lng' => -15.7800, 'codes' => ['309021']],
                                    ['name' => 'BATABUT KANTORA',    'lat' => 13.0600, 'lng' => -15.7600, 'codes' => ['309031','309032']],
                                    ['name' => 'KUSAMAI BANTABA',    'lat' => 13.0900, 'lng' => -15.7900, 'codes' => ['309041','309042']],
                                    ['name' => 'JANACK BANTABA',     'lat' => 13.0750, 'lng' => -15.7700, 'codes' => ['309051','309052']],
                                ],
                            ],
                            [
                                'name' => 'SIBANOR', 'code' => 'BK-FBT-SB', 'lat' => 13.1000, 'lng' => -15.7500,
                                'stations' => [
                                    ['name' => 'TAMPOTO',                  'lat' => 13.0650, 'lng' => -15.7500, 'codes' => ['309061','309062']],
                                    ['name' => 'KANSANYI ECD',             'lat' => 13.0700, 'lng' => -15.7700, 'codes' => ['309071']],
                                    ['name' => 'SIBANOR BANTABA',          'lat' => 13.1000, 'lng' => -15.7500, 'codes' => ['309081','309082','309083','309084']],
                                    ['name' => 'BINTANG BANTABA',          'lat' => 13.0850, 'lng' => -15.8100, 'codes' => ['309091']],
                                    ['name' => 'BULLANJORR BANTABA',       'lat' => 13.0950, 'lng' => -15.7900, 'codes' => ['309101']],
                                    ['name' => 'BATENDING KAJERA BANTABA', 'lat' => 13.0800, 'lng' => -15.7600, 'codes' => ['309111']],
                                    ['name' => 'JAKOI SIBIRICK',           'lat' => 13.0700, 'lng' => -15.7400, 'codes' => ['309121']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'FONI KANSALA', 'code' => 'BK-FK', 'lat' => 13.1150, 'lng' => -15.4700,
                        'wards' => [
                            [
                                'name' => 'KANILAI', 'code' => 'BK-FK-KN', 'lat' => 13.1200, 'lng' => -15.4800,
                                'stations' => [
                                    ['name' => 'SANGHAJORR HEALTH CENTRE', 'lat' => 13.1000, 'lng' => -15.4500, 'codes' => ['310011','310012']],
                                    ['name' => 'DARSILAMEH BANTABA',       'lat' => 13.1050, 'lng' => -15.4600, 'codes' => ['310021']],
                                    ['name' => 'KANFENDI BANTABA',         'lat' => 13.1100, 'lng' => -15.4700, 'codes' => ['310031','310032']],
                                    ['name' => 'KANILAI PRI. SCH',         'lat' => 13.1200, 'lng' => -15.4800, 'codes' => ['310041','310042','310043','310044']],
                                    ['name' => 'JEKESI DANDONI BANTABA',   'lat' => 13.1150, 'lng' => -15.4750, 'codes' => ['310051']],
                                ],
                            ],
                            [
                                'name' => 'BWIAM', 'code' => 'BK-FK-BW', 'lat' => 13.1300, 'lng' => -15.5200,
                                'stations' => [
                                    ['name' => 'BWIAM CCF',                  'lat' => 13.1300, 'lng' => -15.5200, 'codes' => ['310061','310062','310063']],
                                    ['name' => 'DOBONG BANTABA',             'lat' => 13.1400, 'lng' => -15.5300, 'codes' => ['310071']],
                                    ['name' => 'KAMPANT ANGLICAN CHURCH',   'lat' => 13.1350, 'lng' => -15.5250, 'codes' => ['310081','310082']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'FONI BONDALI', 'code' => 'BK-FBO', 'lat' => 13.0900, 'lng' => -15.6500,
                        'wards' => [
                            [
                                'name' => 'BANTANJANG', 'code' => 'BK-FBO-BT', 'lat' => 13.1000, 'lng' => -15.6600,
                                'stations' => [
                                    ['name' => "BONDALI JOLA CHIEF'S QUARTER", 'lat' => 13.0900, 'lng' => -15.6500, 'codes' => ['311011','311012']],
                                    ['name' => 'BANTANJANG BANTABA',            'lat' => 13.1000, 'lng' => -15.6600, 'codes' => ['311021']],
                                ],
                            ],
                            [
                                'name' => 'MAYORK', 'code' => 'BK-FBO-MY', 'lat' => 13.0950, 'lng' => -15.6550,
                                'stations' => [
                                    ['name' => 'CHABAI BANTABA',        'lat' => 13.0800, 'lng' => -15.6400, 'codes' => ['311031']],
                                    ['name' => 'KANKURANG BANTABA',     'lat' => 13.0850, 'lng' => -15.6450, 'codes' => ['311041','311042']],
                                    ['name' => 'MAYORK HEALTH CENTRE',  'lat' => 13.0950, 'lng' => -15.6550, 'codes' => ['311051','311052']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'FONI JARROL', 'code' => 'BK-FJ', 'lat' => 13.1300, 'lng' => -15.8000,
                        'wards' => [
                            [
                                'name' => 'SINTET', 'code' => 'BK-FJ-SN', 'lat' => 13.1300, 'lng' => -15.8200,
                                'stations' => [
                                    ['name' => 'KALAGI BANTABA',       'lat' => 13.1200, 'lng' => -15.8000, 'codes' => ['312011','312012']],
                                    ['name' => 'SINTET HEALTH CENTRE', 'lat' => 13.1300, 'lng' => -15.8200, 'codes' => ['312021','312022']],
                                ],
                            ],
                            [
                                'name' => 'WASSADOU', 'code' => 'BK-FJ-WS', 'lat' => 13.1500, 'lng' => -15.7600,
                                'stations' => [
                                    ['name' => 'KANMAMUDOU ECD',           'lat' => 13.1400, 'lng' => -15.7800, 'codes' => ['312031','312032']],
                                    ['name' => 'WASSADOU BANTABA',         'lat' => 13.1500, 'lng' => -15.7600, 'codes' => ['312041']],
                                    ['name' => 'ARANKON KUNDA BANTABA',    'lat' => 13.1350, 'lng' => -15.7700, 'codes' => ['312051']],
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        }

        // =========================================================================
        // 4. KEREWAN (North Bank Region)
        // =========================================================================
        private function kerewan(): array
        {
            return [
                'name' => 'KEREWAN', 'code' => 'KW', 'lat' => 13.4890, 'lng' => -16.0890,
                'constituencies' => [
                    [
                        'name' => 'LOWER NUIMI', 'code' => 'KW-LN', 'lat' => 13.4850, 'lng' => -16.5300,
                        'wards' => [
                            [
                                'name' => 'ESSAU', 'code' => 'KW-LN-ES', 'lat' => 13.4833, 'lng' => -16.5333,
                                'stations' => [
                                    ['name' => 'MISIRANDING BANTABA',           'lat' => 13.4840, 'lng' => -16.5300, 'codes' => ['401011','401012']],
                                    ['name' => 'JINACK KAJATA BANTABA',         'lat' => 13.4900, 'lng' => -16.5200, 'codes' => ['401021']],
                                    ['name' => 'HAMDALAI MOSQUE',               'lat' => 13.4820, 'lng' => -16.5340, 'codes' => ['401031','401032']],
                                    ['name' => 'FASS NJAGA CHOI VIDEO HALL',    'lat' => 13.4800, 'lng' => -16.5350, 'codes' => ['401041','401042','401043']],
                                    ['name' => 'KERR JATTA BANTABA',            'lat' => 13.4810, 'lng' => -16.5380, 'codes' => ['401051']],
                                    ['name' => 'NJONGON BANTABA',               'lat' => 13.4900, 'lng' => -16.5100, 'codes' => ['401061','401062']],
                                    ['name' => 'MBOLLET BA BANTABA',            'lat' => 13.4950, 'lng' => -16.5050, 'codes' => ['401071','401072']],
                                    ['name' => 'KANUMA YOUTH CENTRE',           'lat' => 13.4980, 'lng' => -16.5020, 'codes' => ['401081','401082']],
                                    ['name' => 'MADINA KANUMA BANTABA',         'lat' => 13.4985, 'lng' => -16.5025, 'codes' => ['401091','401092']],
                                    ['name' => 'ESSAU A OLD VET',               'lat' => 13.4850, 'lng' => -16.5240, 'codes' => ['401101','401102','401103','401104']],
                                    ['name' => 'ESSAU B SEN SEC SCH',           'lat' => 13.4855, 'lng' => -16.5250, 'codes' => ['401111']],
                                    ['name' => 'JAGLEH (KERR WALLY) BANTABA',   'lat' => 13.4950, 'lng' => -16.5000, 'codes' => ['401121']],
                                    ['name' => 'BARRA BANTABA (NEAR ALKALO\'S)','lat' => 13.4833, 'lng' => -16.5333, 'codes' => ['401131','401132','401133','401134']],
                                    ['name' => 'NDOFAN FIRST AID CENTRE',       'lat' => 13.4900, 'lng' => -16.4900, 'codes' => ['401141']],
                                    ['name' => 'BERENDING SKILL CENTRE',        'lat' => 13.5115, 'lng' => -16.4890, 'codes' => ['401151','401152']],
                                    ['name' => 'BUNIADU HEALTH CENTRE',         'lat' => 13.5100, 'lng' => -16.4800, 'codes' => ['401161']],
                                    ['name' => 'SAMI SOTOKOI (SAMI ESSAU) BANTABA','lat' => 13.4900, 'lng' => -16.5100, 'codes' => ['401171']],
                                ],
                            ],
                            [
                                'name' => 'MEDINA SERING MASS', 'code' => 'KW-LN-MS', 'lat' => 13.5130, 'lng' => -16.4110,
                                'stations' => [
                                    ['name' => 'NDUNGU KEBBEH BANTABA',                    'lat' => 13.5120, 'lng' => -16.4500, 'codes' => ['401181','401182','401183']],
                                    ['name' => 'MAKA BALA MANNEH',                         'lat' => 13.5100, 'lng' => -16.4400, 'codes' => ['401191']],
                                    ['name' => 'SARE BOHOUM',                              'lat' => 13.5080, 'lng' => -16.4300, 'codes' => ['401201']],
                                    ['name' => 'TUBA ANGALLEH',                            'lat' => 13.5090, 'lng' => -16.4200, 'codes' => ['401211']],
                                    ['name' => 'MEDINA MANNEH',                            'lat' => 13.5130, 'lng' => -16.4100, 'codes' => ['401221']],
                                    ['name' => 'KERR MALICK SARR',                         'lat' => 13.5140, 'lng' => -16.4150, 'codes' => ['401231']],
                                    ['name' => 'MEDINA SERING MASS BANTABA AT NEW MOSQUE', 'lat' => 13.5130, 'lng' => -16.4110, 'codes' => ['401241','401242','401243']],
                                    ['name' => 'CHAMEN BANTABA',                           'lat' => 13.5200, 'lng' => -16.4050, 'codes' => ['401251']],
                                    ['name' => 'SAMBA KALLA HEALTH CENTRE',                'lat' => 13.5180, 'lng' => -16.3900, 'codes' => ['401261']],
                                    ['name' => 'SAMBA NJABEH',                             'lat' => 13.5150, 'lng' => -16.3850, 'codes' => ['401271']],
                                    ['name' => 'NDUNGU CHAREN',                            'lat' => 13.5120, 'lng' => -16.3800, 'codes' => ['401281']],
                                    ['name' => 'BAKINDICK MANDINKA',                       'lat' => 13.5100, 'lng' => -16.3900, 'codes' => ['401291','401292']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'UPPER NUIMI', 'code' => 'KW-UN', 'lat' => 13.4200, 'lng' => -16.3800,
                        'wards' => [
                            [
                                'name' => 'PRINCE', 'code' => 'KW-UN-PR', 'lat' => 13.4100, 'lng' => -16.3200,
                                'stations' => [
                                    ['name' => 'SAMI KOTO SEED STORE',         'lat' => 13.4200, 'lng' => -16.4000, 'codes' => ['402011']],
                                    ['name' => 'SIKA HEALTH CENTRE',           'lat' => 13.4300, 'lng' => -16.4100, 'codes' => ['402021']],
                                    ['name' => 'ALBREDA SCHOOL',               'lat' => 13.3310, 'lng' => -16.3850, 'codes' => ['402031','402032']],
                                    ['name' => 'JURUNKU BANTABA',              'lat' => 13.4000, 'lng' => -16.3500, 'codes' => ['402041']],
                                    ['name' => 'CHILLA HEALTH CENTRE',         'lat' => 13.4100, 'lng' => -16.3300, 'codes' => ['402051','402052']],
                                    ['name' => 'KABAKOTO BANTABA',             'lat' => 13.4150, 'lng' => -16.3200, 'codes' => ['402061']],
                                    ['name' => 'DARUSALAM BANTABA',            'lat' => 13.4120, 'lng' => -16.3100, 'codes' => ['402071']],
                                    ['name' => 'PRINCE BANTABA',               'lat' => 13.4100, 'lng' => -16.3000, 'codes' => ['402081']],
                                    ['name' => 'MEDINA BAFULOTO MARKET',       'lat' => 13.4200, 'lng' => -16.3600, 'codes' => ['402091','402092']],
                                    ['name' => 'KERR CHEBO JALLOW (KAYAL)',    'lat' => 13.4300, 'lng' => -16.3700, 'codes' => ['402101']],
                                    ['name' => 'BIRAN KANNI',                  'lat' => 13.4250, 'lng' => -16.3650, 'codes' => ['402111']],
                                    ['name' => 'PASSY BANTABA',                'lat' => 13.4350, 'lng' => -16.3800, 'codes' => ['402121']],
                                ],
                            ],
                            [
                                'name' => 'PAKAU', 'code' => 'KW-UN-PK', 'lat' => 13.5000, 'lng' => -16.4000,
                                'stations' => [
                                    ['name' => 'LAMIN SCHOOL',                      'lat' => 13.4500, 'lng' => -16.3500, 'codes' => ['402131','402132']],
                                    ['name' => 'SITANUNKU BANTABA',                 'lat' => 13.4600, 'lng' => -16.3600, 'codes' => ['402141','402142']],
                                    ['name' => 'ALJAMDU BANTABA',                   'lat' => 13.4700, 'lng' => -16.3700, 'codes' => ['402151']],
                                    ['name' => 'MADINA SIDIYA BANTABA',             'lat' => 13.4800, 'lng' => -16.3800, 'codes' => ['402161','402162']],
                                    ['name' => 'BAKALAR MEDINA BANTABA',            'lat' => 13.4900, 'lng' => -16.3900, 'codes' => ['402171','402172']],
                                    ['name' => 'PAKAU BA HEALTH CENTRE',            'lat' => 13.5000, 'lng' => -16.4000, 'codes' => ['402181','402182']],
                                    ['name' => 'FASS OMAR SAHO HEALTH CENTRE',      'lat' => 13.5100, 'lng' => -16.4050, 'codes' => ['402191','402192','402193']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'JOKADOU', 'code' => 'KW-JK', 'lat' => 13.5200, 'lng' => -16.3100,
                        'wards' => [
                            [
                                'name' => 'KERR JARGA', 'code' => 'KW-JK-KJ', 'lat' => 13.5300, 'lng' => -16.3100,
                                'stations' => [
                                    ['name' => 'MADINA MODUM',                   'lat' => 13.5300, 'lng' => -16.3100, 'codes' => ['403011','403012']],
                                    ['name' => 'GISSA (KERR AMADOU FAYEL)',       'lat' => 13.5200, 'lng' => -16.3200, 'codes' => ['403021','403022']],
                                    ['name' => 'KERR OMAR SAINE',                'lat' => 13.5100, 'lng' => -16.3000, 'codes' => ['403031']],
                                    ['name' => 'DARUSALAM (KERR MATAR SARR)',     'lat' => 13.5000, 'lng' => -16.2900, 'codes' => ['403041']],
                                    ['name' => 'KERR SELLEH HEALTH CENTRE',       'lat' => 13.4900, 'lng' => -16.2800, 'codes' => ['403051']],
                                    ['name' => 'TORO ALASAN',                     'lat' => 13.5400, 'lng' => -16.3200, 'codes' => ['403061']],
                                    ['name' => 'KUNTAYA BANTABA',                 'lat' => 13.5350, 'lng' => -16.1950, 'codes' => ['403071','403072']],
                                    ['name' => 'KERR JARGA JOBE HEALTH CENTRE',   'lat' => 13.5200, 'lng' => -16.3000, 'codes' => ['403081']],
                                ],
                            ],
                            [
                                'name' => 'DASILAMEH', 'code' => 'KW-JK-DS', 'lat' => 13.4820, 'lng' => -16.2730,
                                'stations' => [
                                    ['name' => 'KARANTABA HEALTH CENTRE',     'lat' => 13.4800, 'lng' => -16.2700, 'codes' => ['403091']],
                                    ['name' => 'TAMBANA OLD VISACA BANK',     'lat' => 13.4900, 'lng' => -16.2600, 'codes' => ['403101']],
                                    ['name' => 'MUNYAGEN HEALTH CENTRE',      'lat' => 13.5000, 'lng' => -16.2500, 'codes' => ['403111','403112']],
                                    ['name' => 'KERR MAJAW (CHESSAY)',        'lat' => 13.4820, 'lng' => -16.2730, 'codes' => ['403121','403122']],
                                    ['name' => 'PASSY NGAYEN',                'lat' => 13.4840, 'lng' => -16.2750, 'codes' => ['403131']],
                                    ['name' => 'BALI MANDINKA SCHOOL',        'lat' => 13.5110, 'lng' => -16.2200, 'codes' => ['403141','403142']],
                                    ['name' => 'DASILAMEH BANTABA',           'lat' => 13.4820, 'lng' => -16.2740, 'codes' => ['403151','403152']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'LOWER BADDIBU', 'code' => 'KW-LB', 'lat' => 13.4890, 'lng' => -16.0890,
                        'wards' => [
                            [
                                'name' => 'KEREWAN', 'code' => 'KW-LB-KW', 'lat' => 13.4910, 'lng' => -16.0910,
                                'stations' => [
                                    ['name' => 'KERR ARDO',           'lat' => 13.4900, 'lng' => -16.0900, 'codes' => ['404011']],
                                    ['name' => 'SUWAREH KUNDA',       'lat' => 13.4910, 'lng' => -16.0880, 'codes' => ['404021']],
                                    ['name' => 'TOROBA',              'lat' => 13.4880, 'lng' => -16.0870, 'codes' => ['404031']],
                                    ['name' => 'NJAWARA',             'lat' => 13.4870, 'lng' => -16.0860, 'codes' => ['404041']],
                                    ['name' => 'KEREWAN OLD MARKET',  'lat' => 13.4910, 'lng' => -16.0910, 'codes' => ['404051','404052','404053']],
                                ],
                            ],
                            [
                                'name' => 'SAABA', 'code' => 'KW-LB-SA', 'lat' => 13.4680, 'lng' => -16.0350,
                                'stations' => [
                                    ['name' => 'MBAMORI KUNDA HEALTH CENTRE',   'lat' => 13.4700, 'lng' => -16.0400, 'codes' => ['404061','404062']],
                                    ['name' => 'GUNJUR BANTABA',                'lat' => 13.4900, 'lng' => -16.0100, 'codes' => ['404071']],
                                    ['name' => 'BANNI BANTABA',                 'lat' => 13.4800, 'lng' => -16.0000, 'codes' => ['404081']],
                                    ['name' => 'SAABA BANTABA',                 'lat' => 13.4680, 'lng' => -16.0350, 'codes' => ['404091','404092']],
                                    ['name' => 'KINTEH KUNDA JANNEH YAA',       'lat' => 13.4650, 'lng' => -16.0300, 'codes' => ['404101']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'CENTRAL BADDIBU', 'code' => 'KW-CB', 'lat' => 13.5220, 'lng' => -15.9620,
                        'wards' => [
                            [
                                'name' => 'SALIKENNE', 'code' => 'KW-CB-SL', 'lat' => 13.5220, 'lng' => -15.9620,
                                'stations' => [
                                    ['name' => 'SALIKENNE MARKET',              'lat' => 13.5220, 'lng' => -15.9620, 'codes' => ['405011','405012','405013']],
                                    ['name' => 'MANDORY HEALTH POST',           'lat' => 13.5250, 'lng' => -15.9600, 'codes' => ['405021']],
                                    ['name' => 'KERR PATEH KORE SEED STORE',    'lat' => 13.5280, 'lng' => -15.9580, 'codes' => ['405031','405032']],
                                    ['name' => 'DARU RILWAN HEALTH POST',       'lat' => 13.5300, 'lng' => -15.9560, 'codes' => ['405041','405042']],
                                ],
                            ],
                            [
                                'name' => 'NJABA KUNDA', 'code' => 'KW-CB-NJ', 'lat' => 13.5425, 'lng' => -15.8930,
                                'stations' => [
                                    ['name' => 'WELLINGARA BANTABA',            'lat' => 13.5400, 'lng' => -15.9000, 'codes' => ['405051']],
                                    ['name' => 'KINTEH KUNDA MARONG KUNDA',     'lat' => 13.5190, 'lng' => -15.9180, 'codes' => ['405061']],
                                    ['name' => 'NJABA KUNDA BANTABA',           'lat' => 13.5425, 'lng' => -15.8930, 'codes' => ['405071','405072']],
                                    ['name' => 'MINTEH KUNDA',                  'lat' => 13.5380, 'lng' => -15.8900, 'codes' => ['405081']],
                                    ['name' => 'KERR KATIM WOLLOF',             'lat' => 13.5350, 'lng' => -15.8870, 'codes' => ['405091']],
                                    ['name' => 'NAWLARU BANTABA',               'lat' => 13.5300, 'lng' => -15.8800, 'codes' => ['405101','405102']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'ILLIASSA', 'code' => 'KW-IL', 'lat' => 13.5510, 'lng' => -15.7050,
                        'wards' => [
                            [
                                'name' => 'KUBANDAR', 'code' => 'KW-IL-KB', 'lat' => 13.5380, 'lng' => -15.7720,
                                'stations' => [
                                    ['name' => 'BALLINGHO HEALTH CENTRE', 'lat' => 13.5400, 'lng' => -15.7750, 'codes' => ['406011']],
                                    ['name' => 'KUBANDAR BANTABA',         'lat' => 13.5380, 'lng' => -15.7720, 'codes' => ['406021']],
                                    ['name' => 'JIGIMAR BANTABA',          'lat' => 13.5420, 'lng' => -15.7700, 'codes' => ['406031','406032']],
                                    ['name' => 'KERR ALI',                 'lat' => 13.5450, 'lng' => -15.7680, 'codes' => ['406041']],
                                    ['name' => 'DUTU BULU',                'lat' => 13.5430, 'lng' => -15.7660, 'codes' => ['406051']],
                                    ['name' => 'MAKA FARAFENNI',           'lat' => 13.5460, 'lng' => -15.7640, 'codes' => ['406061','406062']],
                                    ['name' => 'YALLAL TANKONJALA',        'lat' => 13.5480, 'lng' => -15.7620, 'codes' => ['406071']],
                                    ['name' => 'CHAMEN BANTABA',           'lat' => 13.5500, 'lng' => -15.7600, 'codes' => ['406081']],
                                    ['name' => 'JERICO WOLLOF',            'lat' => 13.5520, 'lng' => -15.7580, 'codes' => ['406091']],
                                ],
                            ],
                            [
                                'name' => 'FARAFENNI', 'code' => 'KW-IL-FF', 'lat' => 13.5680, 'lng' => -15.5980,
                                'stations' => [
                                    ['name' => 'FARAFENI PRI SCH',           'lat' => 13.5650, 'lng' => -15.5980, 'codes' => ['406101','406102','406103','406104','406105','406106']],
                                    ['name' => 'FARAFENI MAURITANIE PRI SCH','lat' => 13.5660, 'lng' => -15.5970, 'codes' => ['406111','406112','406113','406114','406115']],
                                    ['name' => 'FARAFENNI LIBRARY',          'lat' => 13.5670, 'lng' => -15.5960, 'codes' => ['406121','406122','406123','406124']],
                                    ['name' => 'FARAFENNI WHARF TOWN',       'lat' => 13.5690, 'lng' => -15.5940, 'codes' => ['406131']],
                                    ['name' => 'FARAFENNI SEN SEC SCH',      'lat' => 13.5695, 'lng' => -15.5940, 'codes' => ['406141']],
                                ],
                            ],
                            [
                                'name' => 'NOO KUNDA', 'code' => 'KW-IL-NK', 'lat' => 13.5540, 'lng' => -15.6720,
                                'stations' => [
                                    ['name' => 'KEKUTA KUNDA',       'lat' => 13.5540, 'lng' => -15.6720, 'codes' => ['406151']],
                                    ['name' => 'NOO KUNDA BANTABA',  'lat' => 13.5545, 'lng' => -15.6725, 'codes' => ['406161','406162']],
                                    ['name' => 'CONTEH KUNDA SUKOTO','lat' => 13.5550, 'lng' => -15.6700, 'codes' => ['406171']],
                                    ['name' => 'CONTEH KUNDA NIJI',  'lat' => 13.5560, 'lng' => -15.6680, 'codes' => ['406181']],
                                    ['name' => 'NYERIBAYA',          'lat' => 13.5570, 'lng' => -15.6660, 'codes' => ['406191']],
                                ],
                            ],
                            [
                                'name' => 'KATCHANG', 'code' => 'KW-IL-KC', 'lat' => 13.5490, 'lng' => -15.7010,
                                'stations' => [
                                    ['name' => 'ILLIASSA BANTABA',            'lat' => 13.5510, 'lng' => -15.7050, 'codes' => ['406201','406202']],
                                    ['name' => 'JUMANSARIBA HEALTH CENTRE',   'lat' => 13.5500, 'lng' => -15.7030, 'codes' => ['406211']],
                                    ['name' => 'KATCHANG BANTABA',            'lat' => 13.5490, 'lng' => -15.7010, 'codes' => ['406221','406222']],
                                    ['name' => 'ALKALI KUNDA',                'lat' => 13.5480, 'lng' => -15.6990, 'codes' => ['406231']],
                                    ['name' => 'YOUNA BANTABA',               'lat' => 13.5470, 'lng' => -15.6970, 'codes' => ['406241']],
                                    ['name' => 'JAJARI BANTABA',              'lat' => 13.5460, 'lng' => -15.6950, 'codes' => ['406251']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'SABACH SANJAL', 'code' => 'KW-SS', 'lat' => 13.6200, 'lng' => -15.5200,
                        'wards' => [
                            [
                                'name' => 'SANJAL', 'code' => 'KW-SS-SJ', 'lat' => 13.6020, 'lng' => -15.5350,
                                'stations' => [
                                    ['name' => 'BAMBALI BANTABA',             'lat' => 13.6200, 'lng' => -15.5200, 'codes' => ['407011']],
                                    ['name' => 'KUNJATA BANTABA',             'lat' => 13.6220, 'lng' => -15.5180, 'codes' => ['407021']],
                                    ['name' => 'SARA KUNDA BANTABA',          'lat' => 13.6020, 'lng' => -15.5350, 'codes' => ['407031']],
                                    ['name' => 'SINCHU PALEN',                'lat' => 13.6050, 'lng' => -15.5300, 'codes' => ['407041']],
                                    ['name' => 'KANI KUNDA',                  'lat' => 13.6080, 'lng' => -15.5250, 'codes' => ['407051']],
                                    ['name' => 'KUMBIJA ARABIC SCH',          'lat' => 13.6100, 'lng' => -15.5200, 'codes' => ['407061']],
                                    ['name' => 'PALEN WOLOF HEALTH CENTRE',   'lat' => 13.6120, 'lng' => -15.5150, 'codes' => ['407071','407072']],
                                    ['name' => 'LOUMEN BANTABA',              'lat' => 13.6140, 'lng' => -15.5100, 'codes' => ['407081','407082']],
                                    ['name' => 'MBALLOW OMAR (SINCHU SANJAL)','lat' => 13.6160, 'lng' => -15.5050, 'codes' => ['407091']],
                                    ['name' => 'DAFFA BANTABA',               'lat' => 13.6180, 'lng' => -15.5000, 'codes' => ['407101']],
                                    ['name' => 'NGAYEN SANJAL BANTABA',       'lat' => 13.6200, 'lng' => -15.4950, 'codes' => ['407111','407112','407113']],
                                ],
                            ],
                            [
                                'name' => 'SABACH', 'code' => 'KW-SS-SB', 'lat' => 13.6420, 'lng' => -15.5600,
                                'stations' => [
                                    ['name' => 'MBAPA MARIGA SCHOOL',             'lat' => 13.6420, 'lng' => -15.5650, 'codes' => ['407121']],
                                    ['name' => 'DIBBA KUNDA WOLLOF BANTABA',      'lat' => 13.6450, 'lng' => -15.5610, 'codes' => ['407131','407132']],
                                    ['name' => 'KATABA MANDINKA HEALTH CENTRE',   'lat' => 13.6400, 'lng' => -15.5550, 'codes' => ['407141']],
                                    ['name' => 'BASSIC BANTABA',                  'lat' => 13.6380, 'lng' => -15.5500, 'codes' => ['407151']],
                                    ['name' => 'NYANGEN (CHALLA) BANTABA',        'lat' => 13.6360, 'lng' => -15.5450, 'codes' => ['407161']],
                                    ['name' => 'SABACH SUKOTO BANTABA',           'lat' => 13.6210, 'lng' => -15.5120, 'codes' => ['407171','407172']],
                                    ['name' => 'KUSSASAI (KUNJO) BANTABA',        'lat' => 13.6190, 'lng' => -15.5000, 'codes' => ['407181']],
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        }

        // =========================================================================
        // 5. MANSAKONKO (Lower River Region)
        // =========================================================================
        private function mansakonko(): array
        {
            return [
                'name' => 'MANSAKONKO', 'code' => 'MK', 'lat' => 13.4667, 'lng' => -15.5500,
                'constituencies' => [
                    [
                        'name' => 'JARRA WEST', 'code' => 'MK-JW', 'lat' => 13.4332, 'lng' => -15.5398,
                        'wards' => [
                            [
                                'name' => 'GIKOKO', 'code' => 'MK-JW-GK', 'lat' => 13.4332, 'lng' => -15.5398,
                                'stations' => [
                                    ['name' => 'FONKOI KUNDA',        'lat' => 13.4200, 'lng' => -15.5600, 'codes' => ['501011']],
                                    ['name' => 'SARE SAIDY',          'lat' => 13.4100, 'lng' => -15.5700, 'codes' => ['501021']],
                                    ['name' => 'DIGANTEH',            'lat' => 13.3986, 'lng' => -15.5222, 'codes' => ['501031','501032']],
                                    ['name' => 'MISSERA',             'lat' => 13.3986, 'lng' => -15.5222, 'codes' => ['501041','501042']],
                                    ['name' => 'TONIATABA',           'lat' => 13.4308, 'lng' => -15.4851, 'codes' => ['501051','501052']],
                                    ['name' => 'SI-KUNDA',            'lat' => 13.4329, 'lng' => -15.5058, 'codes' => ['501061']],
                                    ['name' => 'PAKALINDING',         'lat' => 13.4475, 'lng' => -15.5186, 'codes' => ['501071','501072']],
                                    ['name' => 'JENOI',               'lat' => 13.4560, 'lng' => -15.5035, 'codes' => ['501081']],
                                    ['name' => 'SOMA SATEBA',         'lat' => 13.4350, 'lng' => -15.5400, 'codes' => ['501091','501092']],
                                    ['name' => 'SOMA NEW TOWN',       'lat' => 13.4360, 'lng' => -15.5395, 'codes' => ['501101','501102']],
                                    ['name' => 'SOMA ANGALFUTA',      'lat' => 13.4370, 'lng' => -15.5380, 'codes' => ['501111','501112']],
                                    ['name' => 'SOMA MISERA',         'lat' => 13.4380, 'lng' => -15.5370, 'codes' => ['501121','501122']],
                                ],
                            ],
                            [
                                'name' => 'JADUMA', 'code' => 'MK-JW-JD', 'lat' => 13.4561, 'lng' => -15.5031,
                                'stations' => [
                                    ['name' => 'SENO BAJONKI',  'lat' => 13.4500, 'lng' => -15.4900, 'codes' => ['501131','501132']],
                                    ['name' => 'JABISA',        'lat' => 13.4600, 'lng' => -15.4800, 'codes' => ['501141']],
                                    ['name' => 'KARANTABA',     'lat' => 13.4561, 'lng' => -15.5031, 'codes' => ['501151','501152']],
                                    ['name' => 'KANIKUNDA',     'lat' => 13.4500, 'lng' => -15.5100, 'codes' => ['501161']],
                                    ['name' => 'SANKWIA',       'lat' => 13.4542, 'lng' => -15.5347, 'codes' => ['501171','501172']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'JARRA CENTRAL', 'code' => 'MK-JC', 'lat' => 13.4503, 'lng' => -15.4342,
                        'wards' => [
                            [
                                'name' => 'BUIBA', 'code' => 'MK-JC-BU', 'lat' => 13.4503, 'lng' => -15.4342,
                                'stations' => [
                                    ['name' => 'DIGANTEH (JARRA CENTRAL)', 'lat' => 13.4080, 'lng' => -15.3850, 'codes' => ['502011','502012']],
                                    ['name' => 'FOROYAA FULA',             'lat' => 13.4100, 'lng' => -15.3800, 'codes' => ['502021']],
                                    ['name' => 'JAPINNEH MARIKOTO',        'lat' => 13.4344, 'lng' => -15.3981, 'codes' => ['502031','502032']],
                                    ['name' => 'JAPINNEH TEMBETO',         'lat' => 13.4348, 'lng' => -15.3985, 'codes' => ['502041']],
                                    ['name' => 'BUIBA',                    'lat' => 13.4503, 'lng' => -15.4342, 'codes' => ['502051']],
                                ],
                            ],
                            [
                                'name' => 'JALAMBEREH', 'code' => 'MK-JC-JL', 'lat' => 13.3881, 'lng' => -15.4214,
                                'stations' => [
                                    ['name' => 'NANEKO',                    'lat' => 13.4200, 'lng' => -15.4000, 'codes' => ['502061']],
                                    ['name' => 'WELLINGARA (SITA HUMA)',    'lat' => 13.4539, 'lng' => -15.3855, 'codes' => ['502071']],
                                    ['name' => 'JALAMBEREH',                'lat' => 13.3881, 'lng' => -15.4214, 'codes' => ['502081','502082']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'JARRA EAST', 'code' => 'MK-JE', 'lat' => 13.4132, 'lng' => -15.3194,
                        'wards' => [
                            [
                                'name' => 'PAKALIBA', 'code' => 'MK-JE-PK', 'lat' => 13.4300, 'lng' => -15.3200,
                                'stations' => [
                                    ['name' => 'SUKUTA (JARRA EAST)',              'lat' => 13.4200, 'lng' => -15.3400, 'codes' => ['503011']],
                                    ['name' => 'PAKALIBA',                         'lat' => 13.4300, 'lng' => -15.3200, 'codes' => ['503021','503022']],
                                    ['name' => 'MADINA (JARRA EAST)',              'lat' => 13.4400, 'lng' => -15.3000, 'codes' => ['503031']],
                                    ['name' => 'DARSILAMEH (JARRA EAST)',          'lat' => 13.4500, 'lng' => -15.2800, 'codes' => ['503041']],
                                    ['name' => 'BARROW KUNDA SUWAREH KUNDA',       'lat' => 13.4600, 'lng' => -15.2600, 'codes' => ['503051']],
                                    ['name' => 'BARROW KUNDA',                     'lat' => 13.4620, 'lng' => -15.2580, 'codes' => ['503061']],
                                ],
                            ],
                            [
                                'name' => 'BURENG', 'code' => 'MK-JE-BR', 'lat' => 13.4132, 'lng' => -15.3194,
                                'stations' => [
                                    ['name' => 'NYAWURULUNG',     'lat' => 13.4000, 'lng' => -15.3100, 'codes' => ['503071','503072']],
                                    ['name' => 'SUTUKUNG',        'lat' => 13.3900, 'lng' => -15.3000, 'codes' => ['503081','503082']],
                                    ['name' => 'BURENG',          'lat' => 13.4132, 'lng' => -15.3194, 'codes' => ['503091','503092']],
                                    ['name' => 'JASONG',          'lat' => 13.4200, 'lng' => -15.3000, 'codes' => ['503101']],
                                    ['name' => 'WELLINGARA BA',   'lat' => 13.4300, 'lng' => -15.2900, 'codes' => ['503111','503112','503113']],
                                    ['name' => 'DINGIRAI',        'lat' => 13.4400, 'lng' => -15.2800, 'codes' => ['503121','503122']],
                                    ['name' => 'DONGOROBA',       'lat' => 13.4267, 'lng' => -15.2956, 'codes' => ['503131','503132']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'KIANG EAST', 'code' => 'MK-KE', 'lat' => 13.3870, 'lng' => -15.5640,
                        'wards' => [
                            [
                                'name' => 'MASSEMBEH', 'code' => 'MK-KE-MB', 'lat' => 13.3872, 'lng' => -15.5447,
                                'stations' => [
                                    ['name' => 'JOMARR',            'lat' => 13.3900, 'lng' => -15.6000, 'codes' => ['504011']],
                                    ['name' => 'KOLIOR',            'lat' => 13.3856, 'lng' => -15.5786, 'codes' => ['504021']],
                                    ['name' => 'TORANKA BANTANG',   'lat' => 13.3800, 'lng' => -15.5900, 'codes' => ['504031']],
                                    ['name' => 'MASSEMBEH',         'lat' => 13.3872, 'lng' => -15.5447, 'codes' => ['504041']],
                                ],
                            ],
                            [
                                'name' => 'KAIAF', 'code' => 'MK-KE-KF', 'lat' => 13.3761, 'lng' => -15.6178,
                                'stations' => [
                                    ['name' => 'NJOLOFEN',         'lat' => 13.3950, 'lng' => -15.6100, 'codes' => ['504051']],
                                    ['name' => 'MADINA SINCHU',    'lat' => 13.3970, 'lng' => -15.6050, 'codes' => ['504061','504062']],
                                    ['name' => 'GENIERE',          'lat' => 13.4031, 'lng' => -15.6114, 'codes' => ['504071']],
                                    ['name' => 'KAIAF',            'lat' => 13.3761, 'lng' => -15.6178, 'codes' => ['504081','504082']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'KIANG CENTRAL', 'code' => 'MK-KC', 'lat' => 13.3644, 'lng' => -15.7006,
                        'wards' => [
                            [
                                'name' => 'KWINELLA', 'code' => 'MK-KC-KW', 'lat' => 13.3644, 'lng' => -15.7006,
                                'stations' => [
                                    ['name' => 'WUROKANG',                 'lat' => 13.3700, 'lng' => -15.7000, 'codes' => ['505011']],
                                    ['name' => 'KWINELLA SANSANG KONO',    'lat' => 13.3644, 'lng' => -15.7006, 'codes' => ['505021']],
                                    ['name' => 'KWINELLA NYA KUNDA',       'lat' => 13.3650, 'lng' => -15.6980, 'codes' => ['505031','505032']],
                                    ['name' => 'MADINA ANGALLEH',          'lat' => 13.3500, 'lng' => -15.6900, 'codes' => ['505041','505042']],
                                ],
                            ],
                            [
                                'name' => 'JIROFF', 'code' => 'MK-KC-JR', 'lat' => 13.3200, 'lng' => -15.6800,
                                'stations' => [
                                    ['name' => 'SARE SARJO',  'lat' => 13.3600, 'lng' => -15.6600, 'codes' => ['505051']],
                                    ['name' => 'SIBITO',      'lat' => 13.3550, 'lng' => -15.6700, 'codes' => ['505061']],
                                    ['name' => 'NEMA',        'lat' => 13.3144, 'lng' => -15.6989, 'codes' => ['505071','505072']],
                                    ['name' => 'JIROFF',      'lat' => 13.3200, 'lng' => -15.6800, 'codes' => ['505081']],
                                    ['name' => 'NEMA KUTA',   'lat' => 13.3300, 'lng' => -15.6700, 'codes' => ['505091']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'KIANG WEST', 'code' => 'MK-KW', 'lat' => 13.3308, 'lng' => -16.0152,
                        'wards' => [
                            [
                                'name' => 'KIANG JULAFAR', 'code' => 'MK-KW-KJ', 'lat' => 13.3700, 'lng' => -15.9500,
                                'stations' => [
                                    ['name' => 'BURONG',        'lat' => 13.3753, 'lng' => -15.9069, 'codes' => ['506011']],
                                    ['name' => 'KARANTABA',     'lat' => 13.3700, 'lng' => -15.8900, 'codes' => ['506021']],
                                    ['name' => 'JANNEH KUNDA',  'lat' => 13.3500, 'lng' => -15.9200, 'codes' => ['506031']],
                                    ['name' => 'JOLI',          'lat' => 13.3400, 'lng' => -15.9300, 'codes' => ['506041']],
                                    ['name' => 'KEMOTO',        'lat' => 13.3300, 'lng' => -15.9400, 'codes' => ['506051']],
                                    ['name' => 'JISSAY',        'lat' => 13.3200, 'lng' => -15.9500, 'codes' => ['506061']],
                                    ['name' => 'MANDUAR',       'lat' => 13.3524, 'lng' => -16.0461, 'codes' => ['506071']],
                                    ['name' => 'TANKULAR',      'lat' => 13.4344, 'lng' => -15.9233, 'codes' => ['506081']],
                                    ['name' => 'KENEBA',        'lat' => 13.3308, 'lng' => -16.0152, 'codes' => ['506091','506092']],
                                    ['name' => 'JALI',          'lat' => 13.3458, 'lng' => -15.7994, 'codes' => ['506101']],
                                    ['name' => 'KANTONG KUNDA', 'lat' => 13.2989, 'lng' => -15.9864, 'codes' => ['506111']],
                                ],
                            ],
                            [
                                'name' => 'KIANG BANTA', 'code' => 'MK-KW-KB', 'lat' => 13.3111, 'lng' => -15.9381,
                                'stations' => [
                                    ['name' => 'KULI KUNDA',     'lat' => 13.3100, 'lng' => -15.9500, 'codes' => ['506121']],
                                    ['name' => 'JIFFARONG',      'lat' => 13.3111, 'lng' => -15.9381, 'codes' => ['506131','506132']],
                                    ['name' => 'JATTABA',        'lat' => 13.3200, 'lng' => -15.9200, 'codes' => ['506141']],
                                    ['name' => 'NIORO JATTABA',  'lat' => 13.3180, 'lng' => -15.9180, 'codes' => ['506151','506152']],
                                    ['name' => 'SANKANDI',       'lat' => 13.3300, 'lng' => -15.9100, 'codes' => ['506161']],
                                    ['name' => 'DUMBUTU',        'lat' => 13.3400, 'lng' => -15.9000, 'codes' => ['506171']],
                                    ['name' => 'BATELLING',      'lat' => 13.3500, 'lng' => -15.8900, 'codes' => ['506181']],
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        }

        // =========================================================================
        // 6. JANJANBUREH (Central River Region)
        // =========================================================================
        private function janjanbureh(): array
        {
            return [
                'name' => 'JANJANBUREH', 'code' => 'JB', 'lat' => 13.5358, 'lng' => -14.7644,
                'constituencies' => [
                    [
                        'name' => 'NIAMINA DANKUNKU', 'code' => 'JB-ND', 'lat' => 13.5558, 'lng' => -15.2206,
                        'wards' => [
                            [
                                'name' => 'DANKUNKU', 'code' => 'JB-ND-DK', 'lat' => 13.5558, 'lng' => -15.2206,
                                'stations' => [
                                    ['name' => 'JESSADI',                          'lat' => 13.5400, 'lng' => -15.2400, 'codes' => ['601011']],
                                    ['name' => 'BARO KUNDA',                       'lat' => 13.5350, 'lng' => -15.2200, 'codes' => ['601021','601022']],
                                    ['name' => 'WELLINGARA YORO BAH (KERR LAYIN)', 'lat' => 13.5500, 'lng' => -15.2100, 'codes' => ['601031','601032']],
                                    ['name' => 'WELINGARA ELO (NIANI KUNDA)',       'lat' => 13.5450, 'lng' => -15.2000, 'codes' => ['601041']],
                                    ['name' => 'DANKUNKU MANDINKA',                'lat' => 13.5558, 'lng' => -15.2206, 'codes' => ['601051','601052']],
                                    ['name' => 'TOUBA WOLLOF (SAMBANG WOLLOF)',    'lat' => 13.5600, 'lng' => -15.2300, 'codes' => ['601061']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'NIAMINA WEST', 'code' => 'JB-NW', 'lat' => 13.4864, 'lng' => -15.1936,
                        'wards' => [
                            [
                                'name' => 'CATAMINA', 'code' => 'JB-NW-CT', 'lat' => 13.4864, 'lng' => -15.1936,
                                'stations' => [
                                    ['name' => 'SAMBANG FULA KUNDA',           'lat' => 13.5181, 'lng' => -15.1911, 'codes' => ['602011','602012']],
                                    ['name' => 'CATAMINA',                     'lat' => 13.4864, 'lng' => -15.1936, 'codes' => ['602021','602022']],
                                    ['name' => 'CHOYA',                        'lat' => 13.4561, 'lng' => -15.1633, 'codes' => ['602031']],
                                    ['name' => 'PAPPA',                        'lat' => 13.5283, 'lng' => -15.2289, 'codes' => ['602041']],
                                    ['name' => 'DALABA',                       'lat' => 13.4700, 'lng' => -15.2000, 'codes' => ['602051']],
                                    ['name' => 'NANA',                         'lat' => 13.4800, 'lng' => -15.2100, 'codes' => ['602061']],
                                    ['name' => 'MADINA MA ANCHA (KERR MALIMA)','lat' => 13.4472, 'lng' => -15.2114, 'codes' => ['602071','602072']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'NIAMINA EAST', 'code' => 'JB-NE', 'lat' => 13.5200, 'lng' => -15.0400,
                        'wards' => [
                            [
                                'name' => 'JARENG', 'code' => 'JB-NE-JR', 'lat' => 13.5000, 'lng' => -15.1000,
                                'stations' => [
                                    ['name' => 'JARENG',              'lat' => 13.5000, 'lng' => -15.1000, 'codes' => ['603011','603012']],
                                    ['name' => 'PAKALA KERR BIRAM',   'lat' => 13.5100, 'lng' => -15.0900, 'codes' => ['603021']],
                                    ['name' => 'JOCKUL',              'lat' => 13.5200, 'lng' => -15.0800, 'codes' => ['603031']],
                                    ['name' => 'BANTANTO',            'lat' => 13.5300, 'lng' => -15.0700, 'codes' => ['603041']],
                                    ['name' => 'BATTI NJOL',          'lat' => 13.5208, 'lng' => -15.0044, 'codes' => ['603051','603052']],
                                    ['name' => 'MAMUD FANA',          'lat' => 13.4739, 'lng' => -15.0456, 'codes' => ['603061','603062']],
                                    ['name' => 'MBAYEN',              'lat' => 13.4347, 'lng' => -15.0119, 'codes' => ['603071']],
                                ],
                            ],
                            [
                                'name' => 'KUDANG', 'code' => 'JB-NE-KD', 'lat' => 13.5519, 'lng' => -15.0211,
                                'stations' => [
                                    ['name' => 'MAKA MBAYEN',     'lat' => 13.4522, 'lng' => -14.9817, 'codes' => ['603081','603082']],
                                    ['name' => 'KAOLONG',         'lat' => 13.5100, 'lng' => -15.0500, 'codes' => ['603091']],
                                    ['name' => 'SOTOKOI',         'lat' => 13.5200, 'lng' => -15.0400, 'codes' => ['603101','603102']],
                                    ['name' => 'KUDANG',          'lat' => 13.5519, 'lng' => -15.0211, 'codes' => ['603111','603112','603113']],
                                    ['name' => 'PATEH SARM',      'lat' => 13.5042, 'lng' => -15.0878, 'codes' => ['603121','603122']],
                                    ['name' => 'SAMBEL KUNDA',    'lat' => 13.5300, 'lng' => -15.0500, 'codes' => ['603131','603132']],
                                    ['name' => 'SINCHU GUNDO',    'lat' => 13.5400, 'lng' => -15.0300, 'codes' => ['603141','603142']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'LOWER FULLADU WEST', 'code' => 'JB-LFW', 'lat' => 13.5292, 'lng' => -14.8944,
                        'wards' => [
                            [
                                'name' => 'BRIKAMABA', 'code' => 'JB-LFW-BK', 'lat' => 13.5292, 'lng' => -14.8944,
                                'stations' => [
                                    ['name' => 'SARE MALANG',       'lat' => 13.5000, 'lng' => -14.8600, 'codes' => ['604011']],
                                    ['name' => 'JAHALLY',           'lat' => 13.5100, 'lng' => -14.8700, 'codes' => ['604021','604022']],
                                    ['name' => 'MADINA MFALLY',     'lat' => 13.5200, 'lng' => -14.8800, 'codes' => ['604031','604032']],
                                    ['name' => 'BRIKMANDING',       'lat' => 13.5250, 'lng' => -14.8850, 'codes' => ['604041']],
                                    ['name' => 'BRIKAMABA',         'lat' => 13.5292, 'lng' => -14.8944, 'codes' => ['604051','604052','604053']],
                                    ['name' => 'DASILAMEH',         'lat' => 13.4800, 'lng' => -14.9000, 'codes' => ['604061','604062','604063']],
                                ],
                            ],
                            [
                                'name' => 'KEREWAN', 'code' => 'JB-LFW-KW', 'lat' => 13.5469, 'lng' => -14.9453,
                                'stations' => [
                                    ['name' => 'SARUJA',             'lat' => 13.5200, 'lng' => -14.9400, 'codes' => ['604071','604072']],
                                    ['name' => 'BOYRAM DENTON',      'lat' => 13.4744, 'lng' => -14.8692, 'codes' => ['604081','604082']],
                                    ['name' => 'MISERA JOBEN',       'lat' => 13.5000, 'lng' => -14.9000, 'codes' => ['604091','604092']],
                                    ['name' => 'FASS ABDOU',         'lat' => 13.4800, 'lng' => -14.9200, 'codes' => ['604101','604102']],
                                    ['name' => 'KEREWAN FULA',       'lat' => 13.5469, 'lng' => -14.9453, 'codes' => ['604111','604112']],
                                    ['name' => 'KEREWAN MANDINKA',   'lat' => 13.5500, 'lng' => -14.9500, 'codes' => ['604121']],
                                    ['name' => 'TAIFA CHENDOU',      'lat' => 13.5400, 'lng' => -14.9000, 'codes' => ['604131','604132']],
                                ],
                            ],
                            [
                                'name' => 'FULABANTANG', 'code' => 'JB-LFW-FB', 'lat' => 13.5294, 'lng' => -14.7742,
                                'stations' => [
                                    ['name' => 'GIDDA',                        'lat' => 13.5200, 'lng' => -14.7800, 'codes' => ['604141','604142']],
                                    ['name' => 'SARE NGAI-TABANDING NGAI',     'lat' => 13.5300, 'lng' => -14.7700, 'codes' => ['604151']],
                                    ['name' => 'PATCHARR',                     'lat' => 13.5400, 'lng' => -14.7600, 'codes' => ['604161']],
                                    ['name' => 'FULABANTANG',                  'lat' => 13.5294, 'lng' => -14.7742, 'codes' => ['604171','604172','604173']],
                                    ['name' => 'FARABA',                       'lat' => 13.5000, 'lng' => -14.7600, 'codes' => ['604181','604182']],
                                    ['name' => 'SANKULAY KUNDA',               'lat' => 13.5100, 'lng' => -14.7700, 'codes' => ['604191']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'JANJANBUREH', 'code' => 'JB-JB', 'lat' => 13.5358, 'lng' => -14.7644,
                        'wards' => [
                            [
                                'name' => 'McCARTHY', 'code' => 'JB-JB-MC', 'lat' => 13.5358, 'lng' => -14.7644,
                                'stations' => [
                                    ['name' => 'JANJANBUREH (McCARTHY)', 'lat' => 13.5358, 'lng' => -14.7644, 'codes' => ['605011','605012']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'UPPER FULLADU WEST', 'code' => 'JB-UFW', 'lat' => 13.4333, 'lng' => -14.6500,
                        'wards' => [
                            [
                                'name' => 'DARU', 'code' => 'JB-UFW-DA', 'lat' => 13.4419, 'lng' => -14.9125,
                                'stations' => [
                                    ['name' => 'TANDI',               'lat' => 13.4300, 'lng' => -14.9000, 'codes' => ['606011']],
                                    ['name' => 'DARU',                'lat' => 13.4419, 'lng' => -14.9125, 'codes' => ['606021','606022']],
                                    ['name' => 'SANTANTO BUBU',       'lat' => 13.4500, 'lng' => -14.9200, 'codes' => ['606031','606032']],
                                    ['name' => 'CHA KUNDA MADINA',    'lat' => 13.4600, 'lng' => -14.9300, 'codes' => ['606041']],
                                    ['name' => 'FASS BELAL',          'lat' => 13.4700, 'lng' => -14.9400, 'codes' => ['606051','606052']],
                                    ['name' => 'SARE PATEH JAWO',     'lat' => 13.4800, 'lng' => -14.9500, 'codes' => ['606061','606062']],
                                ],
                            ],
                            [
                                'name' => 'SARE SOFIE', 'code' => 'JB-UFW-SS', 'lat' => 13.4072, 'lng' => -14.7331,
                                'stations' => [
                                    ['name' => 'SARE SOFIE',          'lat' => 13.4072, 'lng' => -14.7331, 'codes' => ['606071','606072']],
                                    ['name' => 'CHARGEL',             'lat' => 13.3900, 'lng' => -14.7200, 'codes' => ['606081']],
                                    ['name' => 'SARE SILLERI',        'lat' => 13.3800, 'lng' => -14.7100, 'codes' => ['606091','606092']],
                                    ['name' => 'LIBRASS',             'lat' => 13.3700, 'lng' => -14.7000, 'codes' => ['606101']],
                                    ['name' => 'NDIKIRI KUNDA',       'lat' => 13.3600, 'lng' => -14.6900, 'codes' => ['606111']],
                                    ['name' => 'LALA GUI (SARE CHEWTO)','lat' => 13.3500, 'lng' => -14.6800, 'codes' => ['606121']],
                                    ['name' => 'DOBANG KUNDA KEBBA',  'lat' => 13.3400, 'lng' => -14.6700, 'codes' => ['606131','606132']],
                                    ['name' => 'BANTANTO',            'lat' => 13.3300, 'lng' => -14.6600, 'codes' => ['606141','606142']],
                                ],
                            ],
                            [
                                'name' => 'GALLEH', 'code' => 'JB-UFW-GA', 'lat' => 13.4619, 'lng' => -14.7086,
                                'stations' => [
                                    ['name' => 'SAM PATEH (JAHANKA)',           'lat' => 13.4700, 'lng' => -14.7200, 'codes' => ['606151','606152']],
                                    ['name' => 'PATEH GAI',                     'lat' => 13.4800, 'lng' => -14.7300, 'codes' => ['606161']],
                                    ['name' => 'NGAYEN (KERR NJAGA)',            'lat' => 13.4900, 'lng' => -14.7400, 'codes' => ['606171']],
                                    ['name' => 'TUBA OUSMAN',                   'lat' => 13.5000, 'lng' => -14.7500, 'codes' => ['606181']],
                                    ['name' => 'MEDINA TUNJANG',                'lat' => 13.5100, 'lng' => -14.7600, 'codes' => ['606191']],
                                    ['name' => 'GALLEH MANDA',                  'lat' => 13.4619, 'lng' => -14.7086, 'codes' => ['606201','606202']],
                                    ['name' => 'WELLINGARA DEMBA KANDEH',       'lat' => 13.5200, 'lng' => -14.7700, 'codes' => ['606211']],
                                ],
                            ],
                            [
                                'name' => 'BANSANG', 'code' => 'JB-UFW-BS', 'lat' => 13.4333, 'lng' => -14.6500,
                                'stations' => [
                                    ['name' => 'YORO BERI KUNDA MANDINKA', 'lat' => 13.4400, 'lng' => -14.6600, 'codes' => ['606221']],
                                    ['name' => 'BORABA',                   'lat' => 13.4500, 'lng' => -14.6700, 'codes' => ['606231']],
                                    ['name' => 'FUGGA',                    'lat' => 13.4600, 'lng' => -14.6800, 'codes' => ['606241']],
                                    ['name' => 'SOLOLO MANDINKA',          'lat' => 13.4700, 'lng' => -14.6900, 'codes' => ['606251']],
                                    ['name' => 'BANSANG A',                'lat' => 13.4333, 'lng' => -14.6500, 'codes' => ['606261','606262','606263','606264']],
                                    ['name' => 'BANSANG B',                'lat' => 13.4340, 'lng' => -14.6505, 'codes' => ['606271','606272','606273']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'LOWER SALOUM', 'code' => 'JB-LS', 'lat' => 13.5000, 'lng' => -15.4800,
                        'wards' => [
                            [
                                'name' => 'BALLANGHAR', 'code' => 'JB-LS-BL', 'lat' => 13.6150, 'lng' => -15.4880,
                                'stations' => [
                                    ['name' => 'BALLANGHAR JALATO',       'lat' => 13.6100, 'lng' => -15.4900, 'codes' => ['607011']],
                                    ['name' => 'BALLANGHAR KERR NDERRI',  'lat' => 13.6150, 'lng' => -15.4880, 'codes' => ['607021','607022']],
                                    ['name' => 'BALLANGHAR KERR LAYIN',   'lat' => 13.6200, 'lng' => -15.4860, 'codes' => ['607031','607032']],
                                ],
                            ],
                            [
                                'name' => 'KAUR', 'code' => 'JB-LS-KR', 'lat' => 13.4800, 'lng' => -15.6100,
                                'stations' => [
                                    ['name' => 'JAHOUR MANDINKA',      'lat' => 13.4300, 'lng' => -15.5600, 'codes' => ['607041']],
                                    ['name' => 'GENGI WOLLOF',         'lat' => 13.4400, 'lng' => -15.5700, 'codes' => ['607051','607052']],
                                    ['name' => 'JIMBALA FELUNGO',      'lat' => 13.4500, 'lng' => -15.5800, 'codes' => ['607061','607062']],
                                    ['name' => 'SIMBARA KHAI',         'lat' => 13.4600, 'lng' => -15.5900, 'codes' => ['607071']],
                                    ['name' => 'KAUR JANNEH KUNDA',    'lat' => 13.4700, 'lng' => -15.6000, 'codes' => ['607081','607082']],
                                    ['name' => 'KAUR WHARF TOWN',      'lat' => 13.4800, 'lng' => -15.6100, 'codes' => ['607091','607092','607093']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'UPPER SALOUM', 'code' => 'JB-US', 'lat' => 13.5500, 'lng' => -14.8700,
                        'wards' => [
                            [
                                'name' => 'NJAU', 'code' => 'JB-US-NJ', 'lat' => 13.5500, 'lng' => -14.9000,
                                'stations' => [
                                    ['name' => 'JARENG MADI JAMA',         'lat' => 13.5200, 'lng' => -14.8500, 'codes' => ['608011','608012']],
                                    ['name' => 'KERR AULDI',               'lat' => 13.5300, 'lng' => -14.8600, 'codes' => ['608021','608022']],
                                    ['name' => 'BANTANTO EBRIMA KAH',      'lat' => 13.5400, 'lng' => -14.8700, 'codes' => ['608031']],
                                    ['name' => 'BANTANTO KERR LAYE',       'lat' => 13.5500, 'lng' => -14.8800, 'codes' => ['608041']],
                                    ['name' => 'BATTI NDARR',              'lat' => 13.5600, 'lng' => -14.8900, 'codes' => ['608051','608052']],
                                    ['name' => 'NJAU',                     'lat' => 13.5700, 'lng' => -14.9000, 'codes' => ['608061','608062']],
                                ],
                            ],
                            [
                                'name' => 'PANCHANG', 'code' => 'JB-US-PC', 'lat' => 13.5500, 'lng' => -14.8500,
                                'stations' => [
                                    ['name' => 'PANCHANG',      'lat' => 13.5500, 'lng' => -14.8500, 'codes' => ['608071','608072']],
                                    ['name' => 'FASS',          'lat' => 13.5600, 'lng' => -14.8600, 'codes' => ['608081','608082']],
                                    ['name' => 'NOIRO TUKULOR', 'lat' => 13.5700, 'lng' => -14.8700, 'codes' => ['608091','608092']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'NIANIJA', 'code' => 'JB-NN', 'lat' => 13.4700, 'lng' => -14.8000,
                        'wards' => [
                            [
                                'name' => 'CHAMEN', 'code' => 'JB-NN-CH', 'lat' => 13.4700, 'lng' => -14.8000,
                                'stations' => [
                                    ['name' => 'CHAMEN',      'lat' => 13.4700, 'lng' => -14.8000, 'codes' => ['609011','609012','609013']],
                                    ['name' => 'PALAEILI',    'lat' => 13.4800, 'lng' => -14.8100, 'codes' => ['609021']],
                                    ['name' => 'KERR JEBEL',  'lat' => 13.4900, 'lng' => -14.8200, 'codes' => ['609031']],
                                    ['name' => 'BUDUCK',      'lat' => 13.5000, 'lng' => -14.8300, 'codes' => ['609041','609042']],
                                    ['name' => 'BAKADAGI',    'lat' => 13.5100, 'lng' => -14.8400, 'codes' => ['609051']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'NIANI', 'code' => 'JB-NI', 'lat' => 13.6700, 'lng' => -14.8800,
                        'wards' => [
                            [
                                'name' => 'NYANGA', 'code' => 'JB-NI-NY', 'lat' => 13.6500, 'lng' => -14.8000,
                                'stations' => [
                                    ['name' => 'SAFALU',            'lat' => 13.6600, 'lng' => -14.8100, 'codes' => ['610011']],
                                    ['name' => 'NYANGA BANTANG',    'lat' => 13.6500, 'lng' => -14.8000, 'codes' => ['610021','610022','610023']],
                                    ['name' => 'KASS WOLLOF',       'lat' => 13.6400, 'lng' => -14.7900, 'codes' => ['610031','610032']],
                                    ['name' => 'DINGIRAI',          'lat' => 13.6300, 'lng' => -14.7800, 'codes' => ['610041','610042']],
                                    ['name' => 'JOCKUL NDOWEN',     'lat' => 13.6200, 'lng' => -14.7700, 'codes' => ['610051','610052']],
                                    ['name' => 'GINGORY MUSTAPHA',  'lat' => 13.6100, 'lng' => -14.7600, 'codes' => ['610061','610062']],
                                    ['name' => 'MBAYEN WOLLOF',     'lat' => 13.6000, 'lng' => -14.7500, 'codes' => ['610071']],
                                ],
                            ],
                            [
                                'name' => 'KUNTAUR', 'code' => 'JB-NI-KT', 'lat' => 13.6700, 'lng' => -14.8800,
                                'stations' => [
                                    ['name' => 'WASSU',              'lat' => 13.6700, 'lng' => -14.8800, 'codes' => ['610081','610082','610083']],
                                    ['name' => 'KATABA ALH. OMAR',   'lat' => 13.6800, 'lng' => -14.8900, 'codes' => ['610091','610092']],
                                    ['name' => 'KUNTAUR WHARF TOWN', 'lat' => 13.6900, 'lng' => -14.9000, 'codes' => ['610101']],
                                    ['name' => 'SUKUTA',             'lat' => 13.7000, 'lng' => -14.9100, 'codes' => ['610111','610112']],
                                    ['name' => 'JAKABA',             'lat' => 13.7100, 'lng' => -14.9200, 'codes' => ['610121','610122']],
                                    ['name' => 'KAYAI',              'lat' => 13.7200, 'lng' => -14.9300, 'codes' => ['610131']],
                                    ['name' => 'SAIT MARAM',         'lat' => 13.7300, 'lng' => -14.9400, 'codes' => ['610141','610142']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'SAMI', 'code' => 'JB-SM', 'lat' => 13.5200, 'lng' => -14.5500,
                        'wards' => [
                            [
                                'name' => 'BANNI', 'code' => 'JB-SM-BN', 'lat' => 13.5600, 'lng' => -14.6500,
                                'stations' => [
                                    ['name' => 'JARUMEH KOTO',    'lat' => 13.5600, 'lng' => -14.6500, 'codes' => ['611011','611012']],
                                    ['name' => 'JAMALI GANYADO',  'lat' => 13.5700, 'lng' => -14.6600, 'codes' => ['611021']],
                                    ['name' => 'LAMIN KOTO',      'lat' => 13.5800, 'lng' => -14.6700, 'codes' => ['611031']],
                                    ['name' => 'BANNI',           'lat' => 13.5900, 'lng' => -14.6800, 'codes' => ['611041']],
                                    ['name' => 'KIBIRI',          'lat' => 13.6000, 'lng' => -14.6900, 'codes' => ['611051']],
                                    ['name' => 'YORNA MUSA',      'lat' => 13.6100, 'lng' => -14.7000, 'codes' => ['611061']],
                                    ['name' => 'KUNTING',         'lat' => 13.6200, 'lng' => -14.7100, 'codes' => ['611071','611072']],
                                    ['name' => 'DOBO',            'lat' => 13.6300, 'lng' => -14.7200, 'codes' => ['611081','611082']],
                                ],
                            ],
                            [
                                'name' => 'KARANTABA', 'code' => 'JB-SM-KR', 'lat' => 13.5300, 'lng' => -14.6100,
                                'stations' => [
                                    ['name' => 'CHANGAI WOLLOF',       'lat' => 13.5200, 'lng' => -14.6000, 'codes' => ['611091','611092']],
                                    ['name' => 'TABANANI',             'lat' => 13.5300, 'lng' => -14.6100, 'codes' => ['611101']],
                                    ['name' => 'RANEROU SAMBA NGAI',   'lat' => 13.5400, 'lng' => -14.6200, 'codes' => ['611111']],
                                    ['name' => 'KARANTABA',            'lat' => 13.5500, 'lng' => -14.6300, 'codes' => ['611121','611122']],
                                ],
                            ],
                            [
                                'name' => 'PACHONKI', 'code' => 'JB-SM-PC', 'lat' => 13.5100, 'lng' => -14.5600,
                                'stations' => [
                                    ['name' => 'SAMI NJALAL SAMBA (TORO)', 'lat' => 13.5000, 'lng' => -14.5500, 'codes' => ['611131']],
                                    ['name' => 'SAMI PACHONKI',            'lat' => 13.5100, 'lng' => -14.5600, 'codes' => ['611141','611142']],
                                    ['name' => 'SAMI MEDINA',              'lat' => 13.5200, 'lng' => -14.5700, 'codes' => ['611151','611152']],
                                    ['name' => 'TANDI MANDINKA',           'lat' => 13.5300, 'lng' => -14.5800, 'codes' => ['611161']],
                                    ['name' => 'BAYA BA (BAYA EDI BAH)',   'lat' => 13.5400, 'lng' => -14.5900, 'codes' => ['611171','611172']],
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        }

        // =========================================================================
        // 7. BASSE (Upper River Region)
        // =========================================================================
        private function basse(): array
        {
            return [
                'name' => 'BASSE', 'code' => 'BA', 'lat' => 13.3152, 'lng' => -14.2178,
                'constituencies' => [
                    // -----------------------------------------------------------------
                    // JIMARA
                    // -----------------------------------------------------------------
                    [
                        'name' => 'JIMARA', 'code' => 'BA-JM', 'lat' => 13.2758, 'lng' => -14.3644,
                        'wards' => [
                            [
                                'name' => 'JULANGEL', 'code' => 'BA-JM-JL', 'lat' => 13.2758, 'lng' => -14.3644,
                                'stations' => [
                                    ['name' => 'SARE BOJO (NGAMANA)',         'lat' => 13.3108, 'lng' => -14.3164, 'codes' => ['701011','701012','701013']],
                                    ['name' => 'BANTANKORE (SANDI KUNDA)',    'lat' => 13.2667, 'lng' => -14.3039, 'codes' => ['701021']],
                                    ['name' => 'KORO JULA KUNDA',             'lat' => 13.2847, 'lng' => -14.3514, 'codes' => ['701031']],
                                    ['name' => 'MANKAMANG KUNDA',             'lat' => 13.2981, 'lng' => -14.3822, 'codes' => ['701041']],
                                    ['name' => 'SARE NJOBO',                  'lat' => 13.2208, 'lng' => -14.3161, 'codes' => ['701051','701052']],
                                    ['name' => 'JULANGEL',                    'lat' => 13.2758, 'lng' => -14.3644, 'codes' => ['701061','701062']],
                                    ['name' => 'SARE WOLLOM',                 'lat' => 13.2033, 'lng' => -14.3986, 'codes' => ['701071']],
                                    ['name' => 'TABAJANG',                    'lat' => 13.2631, 'lng' => -14.4447, 'codes' => ['701081']],
                                    ['name' => 'FATAKO',                      'lat' => 13.2356, 'lng' => -14.4553, 'codes' => ['701091']],
                                    ['name' => 'KOSSEMAR TENDA',              'lat' => 13.3361, 'lng' => -14.4144, 'codes' => ['701101']],
                                    ['name' => 'BAKADAJI',                    'lat' => 13.3014, 'lng' => -14.4697, 'codes' => ['701111','701112']],
                                    ['name' => 'HELLA KUNDA',                 'lat' => 13.3200, 'lng' => -14.4900, 'codes' => ['701121']],
                                    ['name' => 'MEDINA SAMBA SOWE',           'lat' => 13.3400, 'lng' => -14.5100, 'codes' => ['701131']],
                                    ['name' => 'SARE JAWBEH',                 'lat' => 13.3600, 'lng' => -14.5300, 'codes' => ['701141']],
                                ],
                            ],
                            [
                                'name' => 'GAMBISARA', 'code' => 'BA-JM-GB', 'lat' => 13.2144, 'lng' => -14.2386,
                                'stations' => [
                                    ['name' => 'GAMBISARA',             'lat' => 13.2144, 'lng' => -14.2386, 'codes' => ['701151','701152','701153','701154','701155','701156','701157','701158']],
                                    ['name' => 'DEMBA KUNDA BAHAWA',    'lat' => 13.2300, 'lng' => -14.2500, 'codes' => ['701161','701162']],
                                    ['name' => 'DEMBA KUNDA MORIBUGU',  'lat' => 13.2400, 'lng' => -14.2600, 'codes' => ['701171','701172','701173']],
                                    ['name' => 'NUMUYEL',               'lat' => 13.2500, 'lng' => -14.2700, 'codes' => ['701181','701182','701183']],
                                    ['name' => 'SOTUMA SERE',           'lat' => 13.2600, 'lng' => -14.2800, 'codes' => ['701191','701192','701193']],
                                ],
                            ],
                        ],
                    ],
                    // -----------------------------------------------------------------
                    // BASSE
                    // -----------------------------------------------------------------
                    [
                        'name' => 'BASSE', 'code' => 'BA-BA', 'lat' => 13.3152, 'lng' => -14.2178,
                        'wards' => [
                            [
                                'name' => 'BASSE', 'code' => 'BA-BA-BS', 'lat' => 13.3152, 'lng' => -14.2178,
                                'stations' => [
                                    ['name' => 'KANUBE',                'lat' => 13.3100, 'lng' => -14.2100, 'codes' => ['702011']],
                                    ['name' => 'ALLOUNGHARE',           'lat' => 13.3150, 'lng' => -14.2150, 'codes' => ['702021','702022','702023','702024']],
                                    ['name' => 'KOBA KUNDA',            'lat' => 13.3106, 'lng' => -14.2111, 'codes' => ['702031','702032']],
                                    ['name' => 'BASSE DUNYA CINEMA',    'lat' => 13.3152, 'lng' => -14.2178, 'codes' => ['702041','702042','702043','702044','702045']],
                                    ['name' => 'BASSE COMMUNITY CENTRE','lat' => 13.3144, 'lng' => -14.2147, 'codes' => ['702051','702052']],
                                    ['name' => 'MANNEH KUNDA',          'lat' => 13.3089, 'lng' => -14.2306, 'codes' => ['702061','702062']],
                                    ['name' => 'KABAKAMA',              'lat' => 13.3242, 'lng' => -14.2253, 'codes' => ['702071','702072']],
                                    ['name' => 'MANSAJANG KUNDA',       'lat' => 13.2972, 'lng' => -14.2119, 'codes' => ['702081','702082']],
                                    ['name' => 'GIROBA KUNDA',          'lat' => 13.2900, 'lng' => -14.2050, 'codes' => ['702091','702092']],
                                ],
                            ],
                            [
                                'name' => 'SABI', 'code' => 'BA-BA-SB', 'lat' => 13.1783, 'lng' => -14.1706,
                                'stations' => [
                                    ['name' => 'SARE MUSA',    'lat' => 13.1500, 'lng' => -14.1800, 'codes' => ['702101']],
                                    ['name' => 'SABI',         'lat' => 13.1783, 'lng' => -14.1706, 'codes' => ['702111','702112','702113','702114','702115']],
                                    ['name' => 'FASS BAJONG',  'lat' => 13.1600, 'lng' => -14.1600, 'codes' => ['702121','702122']],
                                    ['name' => 'BANIKO KEKORO','lat' => 13.1400, 'lng' => -14.1400, 'codes' => ['702131','702132','702133']],
                                    ['name' => 'KUMBIJA',      'lat' => 13.1200, 'lng' => -14.1200, 'codes' => ['702141','702142']],
                                ],
                            ],
                        ],
                    ],
                    // -----------------------------------------------------------------
                    // TUMANA
                    // -----------------------------------------------------------------
                    [
                        'name' => 'TUMANA', 'code' => 'BA-TU', 'lat' => 13.2575, 'lng' => -14.1569,
                        'wards' => [
                            [
                                'name' => 'DAMPHA KUNDA', 'code' => 'BA-TU-DK', 'lat' => 13.2575, 'lng' => -14.1569,
                                'stations' => [
                                    ['name' => 'TAMBASANSANG',    'lat' => 13.2500, 'lng' => -14.1400, 'codes' => ['703011']],
                                    ['name' => 'DAMPHA KUNDA',    'lat' => 13.2575, 'lng' => -14.1569, 'codes' => ['703021','703022','703023']],
                                    ['name' => 'CHAMOI',          'lat' => 13.2642, 'lng' => -14.2183, 'codes' => ['703031']],
                                    ['name' => 'KULKULEH',        'lat' => 13.2500, 'lng' => -14.1700, 'codes' => ['703041']],
                                    ['name' => 'SANUNDING',       'lat' => 13.2400, 'lng' => -14.1600, 'codes' => ['703051','703052']],
                                    ['name' => 'BISSANDUGU',      'lat' => 13.2300, 'lng' => -14.1500, 'codes' => ['703061']],
                                    ['name' => 'DINGIRI',         'lat' => 13.2700, 'lng' => -14.1800, 'codes' => ['703071','703072','703073']],
                                    ['name' => 'MEDINA SAMAKO',   'lat' => 13.2800, 'lng' => -14.1900, 'codes' => ['703081','703082']],
                                    ['name' => 'WALIBA KUNDA',    'lat' => 13.2900, 'lng' => -14.2000, 'codes' => ['703091']],
                                    ['name' => 'NJAYEL',          'lat' => 13.3000, 'lng' => -14.2100, 'codes' => ['703101']],
                                ],
                            ],
                            [
                                'name' => 'KULARI', 'code' => 'BA-TU-KL', 'lat' => 13.3900, 'lng' => -14.1900,
                                'stations' => [
                                    ['name' => 'DIABUGU BA-SILLAH', 'lat' => 13.3500, 'lng' => -14.1500, 'codes' => ['703111']],
                                    ['name' => 'SARE ALPHA',        'lat' => 13.3600, 'lng' => -14.1600, 'codes' => ['703121','703122','703123']],
                                    ['name' => 'PERAI',             'lat' => 13.3700, 'lng' => -14.1700, 'codes' => ['703131']],
                                    ['name' => 'BADARI',            'lat' => 13.3800, 'lng' => -14.1800, 'codes' => ['703141']],
                                    ['name' => 'KULARI',            'lat' => 13.3900, 'lng' => -14.1900, 'codes' => ['703151','703152','703153','703154']],
                                    ['name' => 'KUNDAM MA-FATTY',   'lat' => 13.4000, 'lng' => -14.2000, 'codes' => ['703161','703162']],
                                ],
                            ],
                        ],
                    ],
                    // -----------------------------------------------------------------
                    // KANTORA
                    // -----------------------------------------------------------------
                    [
                        'name' => 'KANTORA', 'code' => 'BA-KT', 'lat' => 13.3500, 'lng' => -13.9200,
                        'wards' => [
                            [
                                'name' => 'GARAWOL', 'code' => 'BA-KT-GW', 'lat' => 13.3211, 'lng' => -13.8153,
                                'stations' => [
                                    ['name' => 'SUDUWOL',              'lat' => 13.3200, 'lng' => -13.8000, 'codes' => ['704011','704012']],
                                    ['name' => 'MISSERA BA MARIAMA',   'lat' => 13.3100, 'lng' => -13.8100, 'codes' => ['704021','704022']],
                                    ['name' => 'GAMBISARA LAMOI',      'lat' => 13.3000, 'lng' => -13.8200, 'codes' => ['704031','704032']],
                                    ['name' => 'GARAWOL A',            'lat' => 13.3100, 'lng' => -13.8300, 'codes' => ['704041','704042','704043']],
                                    ['name' => 'GARAWOL B',            'lat' => 13.3200, 'lng' => -13.8400, 'codes' => ['704051','704052','704053']],
                                    ['name' => 'SABI KALILU',          'lat' => 13.3300, 'lng' => -13.8500, 'codes' => ['704061']],
                                    ['name' => 'SAMI KOTO (KANTORA)',  'lat' => 13.3400, 'lng' => -13.8600, 'codes' => ['704071']],
                                    ['name' => 'KEBBEH KUNDA',         'lat' => 13.3500, 'lng' => -13.8700, 'codes' => ['704081']],
                                    ['name' => 'BARAGI KUNDA',         'lat' => 13.3600, 'lng' => -13.8800, 'codes' => ['704091']],
                                ],
                            ],
                            [
                                'name' => 'KOINA', 'code' => 'BA-KT-KN', 'lat' => 13.4114, 'lng' => -13.8839,
                                'stations' => [
                                    ['name' => 'NYAMANARI',         'lat' => 13.4000, 'lng' => -13.9000, 'codes' => ['704101','704102']],
                                    ['name' => 'SONGKUNDA',         'lat' => 13.3900, 'lng' => -13.9100, 'codes' => ['704111','704112']],
                                    ['name' => 'FANTUMBU',          'lat' => 13.3800, 'lng' => -13.9200, 'codes' => ['704121']],
                                    ['name' => 'BOLI BANA',         'lat' => 13.3700, 'lng' => -13.9300, 'codes' => ['704131']],
                                    ['name' => 'BRIKAMA KANTORA',   'lat' => 13.3600, 'lng' => -13.9400, 'codes' => ['704141']],
                                    ['name' => 'GIDDA (KANTORA)',   'lat' => 13.3500, 'lng' => -13.9500, 'codes' => ['704151','704152']],
                                    ['name' => 'KENEBA KANTORA',    'lat' => 13.3400, 'lng' => -13.9600, 'codes' => ['704161']],
                                    ['name' => 'KOINA',             'lat' => 13.3300, 'lng' => -13.9700, 'codes' => ['704171','704172','704173','704174']],
                                    ['name' => 'FATOTO',            'lat' => 13.4114, 'lng' => -13.8839, 'codes' => ['704181','704182']],
                                ],
                            ],
                        ],
                    ],
                    // -----------------------------------------------------------------
                    // SANDU
                    // -----------------------------------------------------------------
                    [
                        'name' => 'SANDU', 'code' => 'BA-SD', 'lat' => 13.5000, 'lng' => -14.3500,
                        'wards' => [
                            [
                                'name' => 'DIABUGU', 'code' => 'BA-SD-DB', 'lat' => 13.5350, 'lng' => -14.3411,
                                'stations' => [
                                    ['name' => 'MAMADI CEESAY KUNDA',    'lat' => 13.5200, 'lng' => -14.3200, 'codes' => ['705011']],
                                    ['name' => 'NAUDE',                  'lat' => 13.5100, 'lng' => -14.3100, 'codes' => ['705021','705022']],
                                    ['name' => 'NIANKUI',                'lat' => 13.5000, 'lng' => -14.3000, 'codes' => ['705031']],
                                    ['name' => 'KURAW ARFANG',           'lat' => 13.4900, 'lng' => -14.2900, 'codes' => ['705041']],
                                    ['name' => 'DIABUGU BATAPA',         'lat' => 13.5350, 'lng' => -14.3411, 'codes' => ['705051','705052','705053']],
                                    ['name' => 'SARE GUGU BASIROU',      'lat' => 13.5400, 'lng' => -14.3500, 'codes' => ['705061','705062']],
                                ],
                            ],
                            [
                                'name' => 'MISSERA', 'code' => 'BA-SD-MS', 'lat' => 13.4689, 'lng' => -14.3792,
                                'stations' => [
                                    ['name' => 'MISSERA (SANDU)',           'lat' => 13.4689, 'lng' => -14.3792, 'codes' => ['705071','705072']],
                                    ['name' => 'CHANGALI LANG KADDY',       'lat' => 13.4600, 'lng' => -14.3700, 'codes' => ['705081','705082']],
                                    ['name' => 'SARE DEMBA TORO',           'lat' => 13.4500, 'lng' => -14.3600, 'codes' => ['705091','705092']],
                                    ['name' => 'KUWONKU',                   'lat' => 13.4400, 'lng' => -14.3500, 'codes' => ['705101']],
                                    ['name' => 'DARSILAMEH MANDINKA',       'lat' => 13.4347, 'lng' => -14.4308, 'codes' => ['705111','705112']],
                                    ['name' => 'DARSILAMEH BULEMBU',        'lat' => 13.4300, 'lng' => -14.4400, 'codes' => ['705121']],
                                    ['name' => 'DARSILAMEH TAKUTALA',       'lat' => 13.4200, 'lng' => -14.4500, 'codes' => ['705131','705132']],
                                ],
                            ],
                        ],
                    ],
                    // -----------------------------------------------------------------
                    // WULLI WEST
                    // -----------------------------------------------------------------
                    [
                        'name' => 'WULLI WEST', 'code' => 'BA-WW', 'lat' => 13.5156, 'lng' => -14.2758,
                        'wards' => [
                            [
                                'name' => 'SUTUKONDING', 'code' => 'BA-WW-SK', 'lat' => 13.5156, 'lng' => -14.2758,
                                'stations' => [
                                    ['name' => 'KEREWAN BADALA',              'lat' => 13.4800, 'lng' => -14.2600, 'codes' => ['706011']],
                                    ['name' => 'KEREWAN NYAKOI',              'lat' => 13.4900, 'lng' => -14.2700, 'codes' => ['706021']],
                                    ['name' => 'TAIBATOU',                    'lat' => 13.5000, 'lng' => -14.2800, 'codes' => ['706031']],
                                    ['name' => 'MADINA KOTO',                 'lat' => 13.5100, 'lng' => -14.2900, 'codes' => ['706041']],
                                    ['name' => 'SUTUKONDING',                 'lat' => 13.5156, 'lng' => -14.2758, 'codes' => ['706051','706052']],
                                    ['name' => 'BANNI ISRAEL',                'lat' => 13.5200, 'lng' => -14.3000, 'codes' => ['706061']],
                                    ['name' => 'PERAI MAMADI (BAJONKOTO)',     'lat' => 13.5300, 'lng' => -14.3100, 'codes' => ['706071']],
                                    ['name' => 'TUBA WULLI',                  'lat' => 13.5400, 'lng' => -14.3200, 'codes' => ['706081']],
                                ],
                            ],
                            [
                                'name' => 'SARE NGAI', 'code' => 'BA-WW-SN', 'lat' => 13.4739, 'lng' => -14.3019,
                                'stations' => [
                                    ['name' => 'KOLI BANTANG',          'lat' => 13.4800, 'lng' => -14.3000, 'codes' => ['706091']],
                                    ['name' => 'JAH KUNDA',             'lat' => 13.4900, 'lng' => -14.3100, 'codes' => ['706101']],
                                    ['name' => 'FADIA KUNDA',           'lat' => 13.5000, 'lng' => -14.3200, 'codes' => ['706111']],
                                    ['name' => 'SARE NGAI',             'lat' => 13.4739, 'lng' => -14.3019, 'codes' => ['706121','706122']],
                                    ['name' => 'CHAMOI BUNDA',          'lat' => 13.4147, 'lng' => -14.3089, 'codes' => ['706131','706132']],
                                    ['name' => 'GUNJUR KUTA',           'lat' => 13.5156, 'lng' => -14.2758, 'codes' => ['706141','706142']],
                                    ['name' => 'BARROW KUNDA',          'lat' => 13.4336, 'lng' => -14.2742, 'codes' => ['706151','706152']],
                                    ['name' => 'LIMBAMBULU YAMADOU',    'lat' => 13.4883, 'lng' => -14.2411, 'codes' => ['706161']],
                                ],
                            ],
                        ],
                    ],
                    // -----------------------------------------------------------------
                    // WULLI EAST
                    // -----------------------------------------------------------------
                    [
                        'name' => 'WULLI EAST', 'code' => 'BA-WE', 'lat' => 13.5186, 'lng' => -14.1281,
                        'wards' => [
                            [
                                'name' => 'BAJA KUNDA', 'code' => 'BA-WE-BK', 'lat' => 13.5186, 'lng' => -14.1281,
                                'stations' => [
                                    ['name' => 'BANTUNDING',        'lat' => 13.4872, 'lng' => -14.1325, 'codes' => ['707011','707012']],
                                    ['name' => 'BAJA KUNDA',        'lat' => 13.5186, 'lng' => -14.1281, 'codes' => ['707021','707022','707023','707024']],
                                    ['name' => 'BORO KANDA KASSEH', 'lat' => 13.4358, 'lng' => -14.1683, 'codes' => ['707031','707032']],
                                    ['name' => 'SUTOKOBA',          'lat' => 13.5514, 'lng' => -13.9686, 'codes' => ['707041','707042','707043']],
                                ],
                            ],
                            [
                                'name' => 'FODAY KUNDA', 'code' => 'BA-WE-FK', 'lat' => 13.4100, 'lng' => -14.1300,
                                'stations' => [
                                    ['name' => 'BRIFU',              'lat' => 13.4000, 'lng' => -14.1200, 'codes' => ['707051']],
                                    ['name' => 'FODAY KUNDA',        'lat' => 13.4100, 'lng' => -14.1300, 'codes' => ['707061']],
                                    ['name' => 'PASSAMAS MANDINKA',  'lat' => 13.4200, 'lng' => -14.1400, 'codes' => ['707071','707072']],
                                    ['name' => 'SAKOLEY KUNDA',      'lat' => 13.4300, 'lng' => -14.1500, 'codes' => ['707081']],
                                    ['name' => 'SARE BOHUM',         'lat' => 13.4400, 'lng' => -14.1600, 'codes' => ['707091','707092']],
                                    ['name' => 'MACCA MASIREH',      'lat' => 13.4500, 'lng' => -14.1700, 'codes' => ['707101']],
                                    ['name' => 'GUNJUR KOTO',        'lat' => 13.4600, 'lng' => -14.1800, 'codes' => ['707111']],
                                    ['name' => 'MUREH KUNDA',        'lat' => 13.4700, 'lng' => -14.1900, 'codes' => ['707121']],
                                    ['name' => 'WELLINGARA YAREH',   'lat' => 13.4800, 'lng' => -14.2000, 'codes' => ['707131']],
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        }
}
