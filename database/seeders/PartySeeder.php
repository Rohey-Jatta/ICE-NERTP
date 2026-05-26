<?php

namespace Database\Seeders;

use App\Models\Election;
use App\Models\PoliticalParty;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the six political parties that contested the 2021 Gambian Presidential Election.
 * Party data sourced from official IEC Gambia website (iec.gm/ova_por/).
 */
class PartySeeder extends Seeder
{
    private const PARTIES = [
        [
            'name'         => "National People's Party",
            'abbreviation' => 'NPP',
            'color'        => '#374151',   // Dark grey (official IEC colour)
            'leader_name'  => 'Adama Barrow',
            'symbol_path'  => null,
            'motto'        => 'Peace, Progress and Unity',
            'headquarters' => "Churchill's Town, The Gambia",
            'website'      => 'https://nppthegambia.com/',
        ],
        [
            'name'         => 'United Democratic Party',
            'abbreviation' => 'UDP',
            'color'        => '#F59E0B',   // Yellow
            'leader_name'  => 'Ousainou Darboe',
            'symbol_path'  => null,
            'motto'        => null,
            'headquarters' => null,
            'website'      => 'https://udp.gm',
        ],
        [
            'name'         => 'Gambia Democratic Congress',
            'abbreviation' => 'GDC',
            'color'        => '#7C3AED',   // Purple
            'leader_name'  => 'Mama Kandeh',
            'symbol_path'  => null,
            'motto'        => 'One Gambia, One People',
            'headquarters' => null,
            'website'      => null,
        ],
        [
            'name'         => "People's Democratic Organisation for Independence and Socialism",
            'abbreviation' => 'PDOIS',
            'color'        => '#92400E',   // Brown
            'leader_name'  => 'Halifa Sallah',
            'symbol_path'  => null,
            'motto'        => 'Liberty, Dignity and Prosperity',
            'headquarters' => 'Serekunda',
            'website'      => 'https://pdois.org',
        ],
        [
            'name'         => 'All Peoples Party',
            'abbreviation' => 'APP',
            'color'        => '#0EA5E9',   // Sea Blue (IEC: "Sea Blue and White")
            'leader_name'  => 'Essa M. Faal',
            'symbol_path'  => null,
            'motto'        => 'Putting People First',
            'headquarters' => 'Wellingara, West Coast Region',
            'website'      => null,
        ],
        [
            'name'         => 'National Unity Party',
            'abbreviation' => 'NUP',
            'color'        => '#F97316',   // Orange (IEC: "Orange and White diagonally")
            'leader_name'  => 'Abdoulie Ebrima Jammeh',
            'symbol_path'  => null,
            'motto'        => 'Unity is Strength',
            'headquarters' => null,
            'website'      => 'https://www.nupgambia.com',
        ],
    ];

    public function run(): void
    {
        $electionId = Election::where('slug', 'gambia-2021-presidential')->value('id');
        if (!$electionId) {
            throw new \RuntimeException('Election gambia-2021-presidential must exist before running PartySeeder.');
        }

        // Skip if parties already exist
        if (PoliticalParty::where('election_id', $electionId)->exists()) {
            $this->command->info('PartySeeder: parties already exist, skipping.');
            return;
        }

        foreach (self::PARTIES as $data) {
            PoliticalParty::firstOrCreate(
                [
                    'election_id'  => $electionId,
                    'abbreviation' => $data['abbreviation'],
                ],
                [
                    'name'         => $data['name'],
                    'slug'         => Str::slug($data['name']),
                    'color'        => $data['color'],
                    'leader_name'  => $data['leader_name'],
                    'motto'        => $data['motto'],
                    'headquarters' => $data['headquarters'],
                    'website'      => $data['website'],
                    'is_active'    => true,
                ]
            );
        }

        $this->command->info('✓ PartySeeder: ' . count(self::PARTIES) . ' parties created for the 2021 Presidential Election.');
    }
}
