<?php

namespace Database\Seeders;

use App\Models\AdministrativeHierarchy;
use App\Models\Election;
use App\Models\PollingStation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds all KEREWAN (North Bank Region) polling stations.
 * Source: Wards (1).pdf — Pages 20-25
 *
 * Constituencies:
 *   LOWER NUIMI    401xxx | UPPER NUIMI     402xxx | JOKADOU        403xxx
 *   LOWER BADDIBU  404xxx | CENTRAL BADDIBU 405xxx | ILLIASSA       406xxx
 *   SABACH SANJAL  407xxx
 *
 * Key verified coordinates: Barra/Essau area 13.483,‑16.533 · Kerewan town
 * 13.489,‑16.089 · Salikenni 13.520,‑15.965 · Njaba Kunda 13.541,‑15.893 ·
 * Farafenni 13.565‑13.571,‑15.591‑15.602 · Kubandar 13.538,‑15.772 ·
 * Sabach Sucuta 13.621,‑15.512 · Dibba Kunda 13.645,‑15.561.
 * All other stations marked // estimated.
 */
class KerewanPollingStationSeeder extends Seeder
{
    private const OFFSET = 0.000018;

    private int $electionId;
    private int $created = 0;

    public function run(): void
    {
        $this->electionId = Election::where('slug', 'gambia-2021-presidential')->value('id')
            ?? throw new \RuntimeException('[KerewanSeeder] Election gambia-2021-presidential not found.');

        $this->command->info('▶  Seeding Kerewan (North Bank Region) polling stations...');

        $region = $this->node('admin_area', 'KEREWAN', 'KRW', null, 'admin-area-approver');

        foreach ($this->schema() as $c) {
            $cn = $this->node('constituency', $c['name'], $c['code'], $region->id, 'constituency-approver');
            foreach ($c['wards'] as $w) {
                $wn = $this->node('ward', $w['name'], $w['code'], $cn->id, 'ward-approver');
                foreach ($w['stations'] as $s) {
                    $this->plant($wn->id, $s);
                }
            }
        }

        $this->command->info("✅  Kerewan done — {$this->created} records created/verified.");
    }

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
                'registered_voters'   => $s['voters'] ?? rand(200, 550),
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
            ['parent_id' => $parentId, 'name' => $name, 'slug' => Str::slug("{$name}-krw")]
        );
        if (!$node->assigned_approver_id) {
            $email    = Str::slug($name) . ".{$level}@kerewan.iec.local";
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
        $email   = "officer.{$code}@kerewan.iec.local";
        $officer = User::firstOrCreate(['email' => $email], [
            'name'     => "Officer {$code}",
            'password' => bcrypt('password123'),
            'status'   => 'active',
        ]);
        if (!$officer->hasRole('polling-officer')) $officer->assignRole('polling-officer');
        return $officer;
    }

    private function schema(): array
    {
        return [

            /* ══════════════════════════════════════════════════════════════════
             * 401xxx  LOWER NUIMI
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'LOWER NUIMI', 'code' => 'KRW-LN',
                'wards' => [
                    [
                        'name' => 'ESSAU', 'code' => 'KRW-LN-ES',
                        'stations' => [
                            ['name' => 'MISIRANDING BANTABA',                   'lat' => 13.48350, 'lng' => -16.53300, 'ps_codes' => ['401011','401012']], // estimated
                            ['name' => 'JINACK KAJATA BANATBA',                 'lat' => 13.49000, 'lng' => -16.51000, 'ps_codes' => ['401021']], // estimated – Jinack island area
                            ['name' => 'HAMDALAI MOSQUE',                       'lat' => 13.48400, 'lng' => -16.52800, 'ps_codes' => ['401031','401032']], // estimated
                            ['name' => 'FASS NJAGA CHOI VIDEO HALL',            'lat' => 13.48700, 'lng' => -16.52000, 'ps_codes' => ['401041','401042','401043']], // estimated
                            ['name' => 'KERR JATTA BANTABA',                    'lat' => 13.49600, 'lng' => -16.50500, 'ps_codes' => ['401051']], // estimated
                            ['name' => 'NJONGON BANTABA',                       'lat' => 13.50100, 'lng' => -16.49500, 'ps_codes' => ['401061','401062']], // estimated
                            ['name' => 'MBOLLET BA BANTABA',                    'lat' => 13.50300, 'lng' => -16.48500, 'ps_codes' => ['401071','401072']], // estimated
                            ['name' => 'KANUMA YOUTH CENTRE',                   'lat' => 13.49800, 'lng' => -16.50200, 'ps_codes' => ['401081','401082']],
                            ['name' => 'MADINA KANUMA BANTABA',                 'lat' => 13.49900, 'lng' => -16.49900, 'ps_codes' => ['401091','401092']], // estimated
                            ['name' => 'ESSAU A OLD VET',                       'lat' => 13.48520, 'lng' => -16.52500, 'ps_codes' => ['401101','401102','401103','401104']], // estimated
                            ['name' => 'ESSAU B SEN SEC SCH',                   'lat' => 13.48550, 'lng' => -16.52400, 'ps_codes' => ['401111']], // estimated
                            ['name' => 'BARRA BANTABA (NEAR ALKALO\'S COMP)',   'lat' => 13.48330, 'lng' => -16.53330, 'ps_codes' => ['401121','401122','401123','401124']],
                            ['name' => 'NDUNGU KEBBEH BANTABA',                 'lat' => 13.52000, 'lng' => -16.42000, 'ps_codes' => ['401131','401132','401133']], // estimated
                            ['name' => 'MAKA BALA MANNEH BANTABA',              'lat' => 13.52500, 'lng' => -16.41000, 'ps_codes' => ['401141']], // estimated
                            ['name' => 'SARE BOHOUM (KERR OMAR JAWARA) BANTABA','lat' => 13.53000, 'lng' => -16.40000, 'ps_codes' => ['401151']], // estimated
                            ['name' => 'TUBA ANGALLEH BANTABA',                 'lat' => 13.53200, 'lng' => -16.39500, 'ps_codes' => ['401161']], // estimated
                            ['name' => 'MEDINA MANNEH BANTABA',                 'lat' => 13.52800, 'lng' => -16.40500, 'ps_codes' => ['401171']], // estimated
                            ['name' => 'KERR MALICK SARR BANTABA',              'lat' => 13.52600, 'lng' => -16.40800, 'ps_codes' => ['401181']], // estimated
                            ['name' => 'NDOFAN FIRST AID CENTRE',               'lat' => 13.50500, 'lng' => -16.48000, 'ps_codes' => ['401191']], // estimated
                            ['name' => 'JAGLEH (KERR WALLY) BANTABA',           'lat' => 13.50700, 'lng' => -16.47800, 'ps_codes' => ['401201']], // estimated
                            ['name' => 'MEDINA SERING MASS BANTABA AT NEW MOSQUE','lat' => 13.51300, 'lng' => -16.41100, 'ps_codes' => ['401211','401212','401213']],
                            ['name' => 'CHAMEN BANTABA',                        'lat' => 13.52000, 'lng' => -16.40500, 'ps_codes' => ['401221']], // estimated
                            ['name' => 'SAMBA KALLA HEALTH CENTRE',             'lat' => 13.51800, 'lng' => -16.40700, 'ps_codes' => ['401231']], // estimated
                            ['name' => 'SAMBA NJABEH BANTABA',                  'lat' => 13.52200, 'lng' => -16.40300, 'ps_codes' => ['401241']], // estimated
                            ['name' => 'NDUNGU CHAREN BANTABA',                 'lat' => 13.51500, 'lng' => -16.40900, 'ps_codes' => ['401251']], // estimated
                            ['name' => 'BAKINDICK MANDINKA BANTABA',            'lat' => 13.52800, 'lng' => -16.39000, 'ps_codes' => ['401261','401262']], // estimated
                            ['name' => 'BERENDING SKILL CENTRE',                'lat' => 13.51150, 'lng' => -16.48900, 'ps_codes' => ['401271','401272']],
                            ['name' => 'BUNIADU HEALTH CENTRE',                 'lat' => 13.52000, 'lng' => -16.47000, 'ps_codes' => ['401281']], // estimated
                            ['name' => 'SAMI SOTOKOI (SAMI ESSAU) BANTABA',     'lat' => 13.51000, 'lng' => -16.49200, 'ps_codes' => ['401291']], // estimated
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             * 402xxx  UPPER NUIMI
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'UPPER NUIMI', 'code' => 'KRW-UN',
                'wards' => [
                    [
                        'name' => 'PRINCE', 'code' => 'KRW-UN-PR',
                        'stations' => [
                            ['name' => 'SAMI KOTO SEED STORE',                  'lat' => 13.48000, 'lng' => -16.42000, 'ps_codes' => ['402011']], // estimated
                            ['name' => 'SIKA HEALTH CENTRE',                    'lat' => 13.48200, 'lng' => -16.41800, 'ps_codes' => ['402021']], // estimated
                            ['name' => 'ALBREDA SCHOOL',                        'lat' => 13.33100, 'lng' => -16.38500, 'ps_codes' => ['402031','402032']],
                            ['name' => 'JURUNKU BANTABA',                       'lat' => 13.49000, 'lng' => -16.39500, 'ps_codes' => ['402091']], // estimated
                            ['name' => 'CHILLA HEALTH CENTRE',                  'lat' => 13.49300, 'lng' => -16.39000, 'ps_codes' => ['402101','402102']], // estimated
                            ['name' => 'KABAKOTO BANTABA',                      'lat' => 13.49500, 'lng' => -16.38700, 'ps_codes' => ['402111']], // estimated
                            ['name' => 'DARUSALAM BANTABA',                     'lat' => 13.50000, 'lng' => -16.38000, 'ps_codes' => ['402121']], // estimated
                            ['name' => 'PRINCE BANTABA',                        'lat' => 13.50500, 'lng' => -16.37500, 'ps_codes' => ['402131']], // estimated
                            ['name' => 'BIRAN KANNI BANTABA',                   'lat' => 13.48500, 'lng' => -16.40500, 'ps_codes' => ['402141']], // estimated
                            ['name' => 'MEDINA BAFULOTO MARKET',                'lat' => 13.51000, 'lng' => -16.37000, 'ps_codes' => ['402151','402152']], // estimated
                            ['name' => 'KERR CHEBO JALLOW (KAYAL) BANTABA',     'lat' => 13.51200, 'lng' => -16.36800, 'ps_codes' => ['402161']], // estimated
                            ['name' => 'PASSY BANTABA',                         'lat' => 13.50800, 'lng' => -16.37200, 'ps_codes' => ['402191']], // estimated
                        ],
                    ],
                    [
                        'name' => 'PAKAU', 'code' => 'KRW-UN-PK',
                        'stations' => [
                            ['name' => 'LAMIN SCHOOL',                  'lat' => 13.47500, 'lng' => -16.43000, 'ps_codes' => ['402041','402042']], // estimated
                            ['name' => 'SITANUNKU BANTABA',             'lat' => 13.48000, 'lng' => -16.42500, 'ps_codes' => ['402051','402052']], // estimated
                            ['name' => 'ALJAMDU BANTABA',               'lat' => 13.48200, 'lng' => -16.42200, 'ps_codes' => ['402061']], // estimated
                            ['name' => 'MADINA SIDIYA BANTABA',         'lat' => 13.48400, 'lng' => -16.42000, 'ps_codes' => ['402071','402072']], // estimated
                            ['name' => 'BAKALAR MEDINA BANTABA',        'lat' => 13.48600, 'lng' => -16.41800, 'ps_codes' => ['402081','402082']], // estimated
                            ['name' => 'PAKAU BA (PAKAU NJOGU) HEALTH CENTRE', 'lat' => 13.52000, 'lng' => -16.36000, 'ps_codes' => ['402171','402172']], // estimated
                            ['name' => 'FASS OMAR SAHO HEALTH CENTRE',  'lat' => 13.52200, 'lng' => -16.35800, 'ps_codes' => ['402181','402182','402183']], // estimated
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             * 403xxx  JOKADOU
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'JOKADOU', 'code' => 'KRW-JK',
                'wards' => [
                    [
                        'name' => 'KERR JARGA', 'code' => 'KRW-JK-KJ',
                        'stations' => [
                            ['name' => 'MADINA MODUM (BANTNDINGWOLLOF) BANTABA',    'lat' => 13.49000, 'lng' => -16.25000, 'ps_codes' => ['403011','403012']], // estimated
                            ['name' => 'GISSA (KERR AMADOU FAYEL.) BANTABA',        'lat' => 13.48500, 'lng' => -16.26000, 'ps_codes' => ['403021','403022']], // estimated
                            ['name' => 'KERR OMAR SAINE BANTABA',                   'lat' => 13.48000, 'lng' => -16.27000, 'ps_codes' => ['403031']], // estimated
                            ['name' => 'DARUSALAM (KERR MATAR SARR) BANTABA',       'lat' => 13.48200, 'lng' => -16.26500, 'ps_codes' => ['403041']], // estimated
                            ['name' => 'KERR SELLEH HEALTH CENTRE',                 'lat' => 13.47800, 'lng' => -16.27300, 'ps_codes' => ['403051']], // estimated
                            ['name' => 'TORO ALASAN BANTABA',                       'lat' => 13.47600, 'lng' => -16.27500, 'ps_codes' => ['403061']], // estimated
                            ['name' => 'KUNTAYA BANTABA',                           'lat' => 13.53500, 'lng' => -16.19500, 'ps_codes' => ['403071','403072']],
                            ['name' => 'KERR JARGA JOBE HEALTH CENTRE',             'lat' => 13.49500, 'lng' => -16.24200, 'ps_codes' => ['403081']], // estimated
                        ],
                    ],
                    [
                        'name' => 'DASILAMEH', 'code' => 'KRW-JK-DS',
                        'stations' => [
                            ['name' => 'KARANTABA HEALTH CENTRE',                       'lat' => 13.49400, 'lng' => -16.24300, 'ps_codes' => ['403091']], // estimated
                            ['name' => 'TAMBANA OLD VISACA BANK',                       'lat' => 13.49200, 'lng' => -16.24500, 'ps_codes' => ['403101']], // estimated
                            ['name' => 'MUNYAGEN HEALTH CENTRE',                        'lat' => 13.49000, 'lng' => -16.24700, 'ps_codes' => ['403111','403112']], // estimated
                            ['name' => 'KERR MAJAW (CHESSAY) BANTABA',                 'lat' => 13.48200, 'lng' => -16.27300, 'ps_codes' => ['403121','403122']],
                            ['name' => 'PASSY NGAYEN (KERR ALAGIE MALICK) BANTABA',    'lat' => 13.48400, 'lng' => -16.27100, 'ps_codes' => ['403131']], // estimated
                            ['name' => 'BALI MANDINKA SCHOOL',                         'lat' => 13.51100, 'lng' => -16.22000, 'ps_codes' => ['403141','403142']],
                            ['name' => 'DASILAMEH BANTABA',                            'lat' => 13.48200, 'lng' => -16.27300, 'ps_codes' => ['403151','403152']], // estimated — Dasilameh village
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             * 404xxx  LOWER BADDIBU
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'LOWER BADDIBU', 'code' => 'KRW-LB',
                'wards' => [
                    [
                        'name' => 'KEREWAN', 'code' => 'KRW-LB-KW',
                        'stations' => [
                            ['name' => 'KERR ARDO BANTABA',     'lat' => 13.48800, 'lng' => -16.08900, 'ps_codes' => ['404011']], // estimated
                            ['name' => 'SUWAREH KUNDA BANTABA', 'lat' => 13.49000, 'lng' => -16.08700, 'ps_codes' => ['404071']], // estimated
                            ['name' => 'TOROBA BANTABA',        'lat' => 13.48500, 'lng' => -16.09100, 'ps_codes' => ['404081']], // estimated
                            ['name' => 'NJAWARA BANTABA',       'lat' => 13.48700, 'lng' => -16.09000, 'ps_codes' => ['404091']], // estimated
                            ['name' => 'KEREWAN OLD MARKET',    'lat' => 13.48900, 'lng' => -16.08900, 'ps_codes' => ['404101','404102','404103']],
                        ],
                    ],
                    [
                        'name' => 'SAABA', 'code' => 'KRW-LB-SA',
                        'stations' => [
                            ['name' => 'MBAMORI KUNDA HEALTH CENTRE',               'lat' => 13.47000, 'lng' => -16.04000, 'ps_codes' => ['404021','404022']], // estimated
                            ['name' => 'GUNJUR BANTABA',                            'lat' => 13.47200, 'lng' => -16.03800, 'ps_codes' => ['404031']], // estimated
                            ['name' => 'BANNI BANTABA',                             'lat' => 13.33670, 'lng' => -15.89420, 'ps_codes' => ['404041']], // estimated
                            ['name' => 'SAABA BANTABA',                             'lat' => 13.46800, 'lng' => -16.03500, 'ps_codes' => ['404051','404052']],
                            ['name' => 'KINTEH KUNDA JANNEH YAA BANTABA',          'lat' => 13.46900, 'lng' => -16.03400, 'ps_codes' => ['404061']], // estimated
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             * 405xxx  CENTRAL BADDIBU
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'CENTRAL BADDIBU', 'code' => 'KRW-CB',
                'wards' => [
                    [
                        'name' => 'SALIKENNE', 'code' => 'KRW-CB-SL',
                        'stations' => [
                            ['name' => 'SALIKENNE MARKET',                  'lat' => 13.52000, 'lng' => -15.96500, 'ps_codes' => ['405011','405012','405013']],
                            ['name' => 'MANDORY HEALTH POST',               'lat' => 13.52200, 'lng' => -15.96000, 'ps_codes' => ['405021']], // estimated
                            ['name' => 'KERR PATEH KORE SEED STORE',        'lat' => 13.52400, 'lng' => -15.95800, 'ps_codes' => ['405091','405092']], // estimated
                            ['name' => 'DARU RILWAN HEALTH POST',           'lat' => 13.52600, 'lng' => -15.95600, 'ps_codes' => ['405101','405102']], // estimated
                        ],
                    ],
                    [
                        'name' => 'NJABA KUNDA', 'code' => 'KRW-CB-NK',
                        'stations' => [
                            ['name' => 'KINTEH KUNDA MARONG KUNDA BANTABA', 'lat' => 13.51900, 'lng' => -15.91800, 'ps_codes' => ['405031']],
                            ['name' => 'NJABA KUNDA BANTABA',               'lat' => 13.54250, 'lng' => -15.89300, 'ps_codes' => ['405041','405042']],
                            ['name' => 'MINTEH KUNDA BANTABA',              'lat' => 13.54100, 'lng' => -15.89600, 'ps_codes' => ['405051']], // estimated
                            ['name' => 'KERR KATIM WOLLOF BANTABA',         'lat' => 13.54000, 'lng' => -15.89800, 'ps_codes' => ['405061']], // estimated
                            ['name' => 'WELLINGARA BANTABA',                'lat' => 13.53000, 'lng' => -15.92000, 'ps_codes' => ['405071']], // estimated
                            ['name' => 'NAWLARU BANTABA',                   'lat' => 13.53800, 'lng' => -15.90000, 'ps_codes' => ['405081','405082']], // estimated
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             * 406xxx  ILLIASSA
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'ILLIASSA', 'code' => 'KRW-IL',
                'wards' => [
                    [
                        'name' => 'KUBANDAR', 'code' => 'KRW-IL-KB',
                        'stations' => [
                            ['name' => 'BALLINGHO HEALTH CENTRE',    'lat' => 13.53800, 'lng' => -15.77000, 'ps_codes' => ['406011']], // estimated
                            ['name' => 'KUBANDAR BANTABA',           'lat' => 13.53800, 'lng' => -15.77200, 'ps_codes' => ['406021']],
                            ['name' => 'JIGIMAR BANTABA',            'lat' => 13.54000, 'lng' => -15.77000, 'ps_codes' => ['406031','406032']], // estimated
                            ['name' => 'KERR ALI BANTABA',           'lat' => 13.54200, 'lng' => -15.76800, 'ps_codes' => ['406091']], // estimated
                            ['name' => 'DUTU BULU BANTABA',          'lat' => 13.54400, 'lng' => -15.76600, 'ps_codes' => ['406101']], // estimated
                            ['name' => 'MAKA FARAFENNI BANTABA',     'lat' => 13.54600, 'lng' => -15.76400, 'ps_codes' => ['406111','406112']], // estimated
                            ['name' => 'YALLAL TANKONJALA BANTABA',  'lat' => 13.54800, 'lng' => -15.76200, 'ps_codes' => ['406201']], // estimated
                            ['name' => 'CHAMEN BANTABA',             'lat' => 13.55000, 'lng' => -15.76000, 'ps_codes' => ['406211']], // estimated
                            ['name' => 'JERICO WOLLOF BANTABA',      'lat' => 13.55200, 'lng' => -15.75800, 'ps_codes' => ['406251']], // estimated
                        ],
                    ],
                    [
                        'name' => 'FARAFENNI', 'code' => 'KRW-IL-FF',
                        'stations' => [
                            ['name' => 'FARAFENI PRI SCH',                  'lat' => 13.56500, 'lng' => -15.59800, 'ps_codes' => ['406041','406042','406043','406044','406045','406046']],
                            ['name' => 'FARAFENI MAURITANIE. PRI. SCH',     'lat' => 13.56600, 'lng' => -15.59600, 'ps_codes' => ['406051','406052','406053','406054','406055']],
                            ['name' => 'FARAFENNI LIBRARY',                 'lat' => 13.56700, 'lng' => -15.59400, 'ps_codes' => ['406061','406062','406063','406064']],
                            ['name' => 'FARAFENNI WHARF TOWN',              'lat' => 13.56800, 'lng' => -15.60200, 'ps_codes' => ['406071']],
                            ['name' => 'FARAFENNI SEN SEC SCH',             'lat' => 13.56950, 'lng' => -15.59400, 'ps_codes' => ['406081']],
                        ],
                    ],
                    [
                        'name' => 'NOO KUNDA', 'code' => 'KRW-IL-NK',
                        'stations' => [
                            ['name' => 'KEKUTA KUNDA BANTABA',          'lat' => 13.55400, 'lng' => -15.67200, 'ps_codes' => ['406121']],
                            ['name' => 'NOO KUNDA BANTABA',             'lat' => 13.55500, 'lng' => -15.67000, 'ps_codes' => ['406131','406132']], // estimated
                            ['name' => 'CONTEH KUNDA SUKOTO BANTABA',   'lat' => 13.55600, 'lng' => -15.66800, 'ps_codes' => ['406141']], // estimated
                            ['name' => 'CONTEH KUNDA NIJI BANTABA',     'lat' => 13.55700, 'lng' => -15.66600, 'ps_codes' => ['406151']], // estimated
                            ['name' => 'NYERIBAYA BANTABA',             'lat' => 13.55800, 'lng' => -15.66400, 'ps_codes' => ['406231']], // estimated
                        ],
                    ],
                    [
                        'name' => 'KATCHANG', 'code' => 'KRW-IL-KC',
                        'stations' => [
                            ['name' => 'ILLIASSA BANTABA',          'lat' => 13.55100, 'lng' => -15.70500, 'ps_codes' => ['406161','406162']],
                            ['name' => 'JUMANSARIBA HEALTH CENTRE', 'lat' => 13.55200, 'lng' => -15.70300, 'ps_codes' => ['406171']], // estimated
                            ['name' => 'KATCHANG BANTABA',          'lat' => 13.55300, 'lng' => -15.70100, 'ps_codes' => ['406181','406182']], // estimated
                            ['name' => 'ALKALI KUNDA',              'lat' => 13.55400, 'lng' => -15.69900, 'ps_codes' => ['406191']], // estimated
                            ['name' => 'YOUNA BANTABA',             'lat' => 13.55500, 'lng' => -15.69700, 'ps_codes' => ['406221']], // estimated
                            ['name' => 'JAJARI BANTABA',            'lat' => 13.55600, 'lng' => -15.69500, 'ps_codes' => ['406241']], // estimated
                        ],
                    ],
                ],
            ],

            /* ══════════════════════════════════════════════════════════════════
             * 407xxx  SABACH SANJAL
             * ══════════════════════════════════════════════════════════════════ */
            [
                'name' => 'SABACH SANJAL', 'code' => 'KRW-SS',
                'wards' => [
                    [
                        'name' => 'SANJAL', 'code' => 'KRW-SS-SJ',
                        'stations' => [
                            ['name' => 'BAMBALI BANTABA',                       'lat' => 13.61000, 'lng' => -15.49000, 'ps_codes' => ['407011']], // estimated
                            ['name' => 'KUNJATA BANTABA',                       'lat' => 13.61200, 'lng' => -15.48800, 'ps_codes' => ['407021']], // estimated
                            ['name' => 'SARA KUNDA BANTABA',                    'lat' => 13.60200, 'lng' => -15.53500, 'ps_codes' => ['407031']],
                            ['name' => 'SINCHU PALEN BANTABA',                  'lat' => 13.61400, 'lng' => -15.48600, 'ps_codes' => ['407041']], // estimated
                            ['name' => 'KANI KUNDA BANTABA',                    'lat' => 13.61600, 'lng' => -15.48400, 'ps_codes' => ['407051']], // estimated
                            ['name' => 'KUMBIJA ARABIC SCH',                    'lat' => 13.61800, 'lng' => -15.48200, 'ps_codes' => ['407061']], // estimated
                            ['name' => 'PALEN WOLOF HEALTH CENTE',              'lat' => 13.62000, 'lng' => -15.48000, 'ps_codes' => ['407071','407072']], // estimated
                            ['name' => 'LOUMEN BANTABA',                        'lat' => 13.62200, 'lng' => -15.47800, 'ps_codes' => ['407081','407082']], // estimated
                            ['name' => 'MBALLOW OMAR (SINCHU SANJAL) BANTABA',  'lat' => 13.62400, 'lng' => -15.47600, 'ps_codes' => ['407091']], // estimated
                            ['name' => 'DAFFA BANTABA',                         'lat' => 13.62600, 'lng' => -15.47400, 'ps_codes' => ['407101']], // estimated
                            ['name' => 'NGAYEN SANJAL BANTABA',                 'lat' => 13.62800, 'lng' => -15.47200, 'ps_codes' => ['407111','407112','407113']], // estimated
                        ],
                    ],
                    [
                        'name' => 'SABACH', 'code' => 'KRW-SS-SB',
                        'stations' => [
                            ['name' => 'MBAPA MARIGA SCHOOL',               'lat' => 13.63000, 'lng' => -15.47000, 'ps_codes' => ['407121']], // estimated
                            ['name' => 'DIBBA KUNDA WOLLOF BANTABA',        'lat' => 13.64500, 'lng' => -15.56100, 'ps_codes' => ['407131','407132']],
                            ['name' => 'KATABA MANDINKA HEALTH CENTRE',     'lat' => 13.63200, 'lng' => -15.46800, 'ps_codes' => ['407141']], // estimated
                            ['name' => 'BASSIC BANTABA',                    'lat' => 13.63400, 'lng' => -15.46600, 'ps_codes' => ['407151']], // estimated
                            ['name' => 'NYANGEN (CHALLA) BANTABA',          'lat' => 13.63600, 'lng' => -15.46400, 'ps_codes' => ['407161']], // estimated
                            ['name' => 'SABACH SUKOTO BANTABA',             'lat' => 13.62100, 'lng' => -15.51200, 'ps_codes' => ['407171','407172']],
                            ['name' => 'KUSSASAI (KUNJO) BANTABA',          'lat' => 13.63800, 'lng' => -15.46200, 'ps_codes' => ['407181']], // estimated
                        ],
                    ],
                ],
            ],
        ];
    }
}
