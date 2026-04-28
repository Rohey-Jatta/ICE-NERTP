<?php

namespace Database\Seeders;

use App\Models\PoliticalParty;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PartySeeder extends Seeder
{
    public function run()
    {
        $parties = [
            ['name' => "National People's Party", 'abbreviation' => 'NPP'],
            ['name' => 'United Democratic Party', 'abbreviation' => 'UDP'],
            ['name' => 'Gambia Democratic Congress', 'abbreviation' => 'GDC'],
            ['name' => 'People\'s Democratic Organisation for Independence and Socialism', 'abbreviation' => 'PDOIS'],
            ['name' => 'National Union Party', 'abbreviation' => 'NUP'],
            ['name' => 'Independent', 'abbreviation' => 'IND'],
        ];

        foreach ($parties as $p) {
            PoliticalParty::create(array_merge([
                'election_id' => 1,
                'is_active' => true,
                'slug' => Str::slug($p['name']),
            ], $p));
        }
    }
}
