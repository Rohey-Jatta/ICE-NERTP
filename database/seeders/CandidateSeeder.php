<?php

namespace Database\Seeders;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\PoliticalParty;
use Illuminate\Database\Seeder;

class CandidateSeeder extends Seeder
{
    public function run()
    {
        $electionId = Election::where('slug', 'gambia-2021-presidential')->value('id');
        if (!$electionId) {
            throw new \RuntimeException('Election gambia-2021-presidential must exist before running CandidateSeeder.');
        }

        // Skip if candidates already exist for this election
        if (Candidate::where('election_id', $electionId)->exists()) {
            $this->command->info('CandidateSeeder: candidates already exist, skipping.');
            return;
        }

        $mapping = [
            ['name' => 'Adama Barrow',              'party' => "National People's Party"],
            ['name' => 'Ousainou Darboe',            'party' => 'United Democratic Party'],
            ['name' => 'Mama Kandeh',                'party' => 'Gambia Democratic Congress'],
            ['name' => 'Halifa Sallah',              'party' => "People's Democratic Organisation for Independence and Socialism"],
            ['name' => 'Essa M. Faal',               'party' => 'Independent'],
            ['name' => 'Abdoulie Ebrima Jammeh',     'party' => 'National Union Party'],
        ];

        foreach ($mapping as $i => $m) {
            $party = PoliticalParty::where('election_id', $electionId)
                ->where('name', $m['party'])
                ->first();

            Candidate::firstOrCreate(
                [
                    'election_id'       => $electionId,
                    'ballot_number'     => (string) ($i + 1),
                ],
                [
                    'political_party_id' => $party?->id,
                    'name'               => $m['name'],
                    'is_independent'     => $m['party'] === 'Independent',
                    'is_active'          => true,
                ]
            );
        }

        $this->command->info('CandidateSeeder: candidates created/verified.');
    }
}