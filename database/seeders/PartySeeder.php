<?php

namespace Database\Seeders;

use App\Models\Election;
use App\Models\PoliticalParty;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PartySeeder extends Seeder
{
    public function run()
    {
        $electionId = Election::where('slug', 'gambia-2021-presidential')->value('id');
        if (!$electionId) {
            throw new \RuntimeException('Election gambia-2021-presidential must exist before running PartySeeder.');
        }

        $parties = [
            ['name' => "National People's Party",                                              'abbreviation' => 'NPP'],
            ['name' => 'United Democratic Party',                                              'abbreviation' => 'UDP'],
            ['name' => 'Gambia Democratic Congress',                                           'abbreviation' => 'GDC'],
            ['name' => "People's Democratic Organisation for Independence and Socialism",      'abbreviation' => 'PDOIS'],
            ['name' => 'National Union Party',                                                 'abbreviation' => 'NUP'],
            ['name' => 'Independent',                                                          'abbreviation' => 'IND'],
        ];

        foreach ($parties as $p) {
            PoliticalParty::firstOrCreate(
                [
                    'election_id'  => $electionId,
                    'abbreviation' => $p['abbreviation'],
                ],
                [
                    'name'      => $p['name'],
                    'slug'      => Str::slug($p['name']),
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('PartySeeder: parties created/verified.');
    }
}