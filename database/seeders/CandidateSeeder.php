<?php

namespace Database\Seeders;

use App\Models\Candidate;
use App\Models\PoliticalParty;
use Illuminate\Database\Seeder;

class CandidateSeeder extends Seeder
{
    public function run()
    {
        $mapping = [
            ['name' => 'Adama Barrow', 'party' => "National People's Party"],
            ['name' => 'Ousainou Darboe', 'party' => 'United Democratic Party'],
            ['name' => 'Mama Kandeh', 'party' => 'Gambia Democratic Congress'],
            ['name' => 'Halifa Sallah', 'party' => 'People\'s Democratic Organisation for Independence and Socialism'],
            ['name' => 'Essa M. Faal', 'party' => 'Independent'],
            ['name' => 'Abdoulie Ebrima Jammeh', 'party' => 'National Union Party'],
        ];

        foreach ($mapping as $i => $m) {
            $party = PoliticalParty::where('name', $m['party'])->first();
            Candidate::create([
                'election_id' => 1,
                'political_party_id' => $party?->id,
                'name' => $m['name'],
                'ballot_number' => $i + 1,
                'is_independent' => $m['party'] === 'Independent',
                'is_active' => true,
            ]);
        }
    }
}
