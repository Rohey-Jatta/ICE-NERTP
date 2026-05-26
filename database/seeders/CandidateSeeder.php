<?php

namespace Database\Seeders;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\PoliticalParty;
use Illuminate\Database\Seeder;

/**
 * Seeds the six official candidates from the 2021 Gambian Presidential Election.
 * Ballot numbers match the official IEC ballot order.
 * Results column order (official PDF): Barrow, Darboe, Faal, Jammeh, Kandeh, Sallah.
 */
class CandidateSeeder extends Seeder
{
    // [ballot_number, candidate_name, party_abbreviation, is_independent]
    private const CANDIDATES = [
        [1, 'Adama Barrow',          'NPP',   false],
        [2, 'Ousainou Darboe',        'UDP',   false],
        [3, 'Mama Kandeh',            'GDC',   false],
        [4, 'Halifa Sallah',          'PDOIS', false],
        [5, 'Essa M. Faal',           'APP',   false], // Runs under All Peoples Party
        [6, 'Abdoulie Ebrima Jammeh', 'NUP',   false],
    ];

    public function run(): void
    {
        $electionId = Election::where('slug', 'gambia-2021-presidential')->value('id');
        if (!$electionId) {
            throw new \RuntimeException('Election gambia-2021-presidential must exist before running CandidateSeeder.');
        }

        if (Candidate::where('election_id', $electionId)->exists()) {
            $this->command->info('CandidateSeeder: candidates already exist, skipping.');
            return;
        }

        foreach (self::CANDIDATES as [$ballot, $name, $partyAbbr, $isIndependent]) {
            $party = PoliticalParty::where('election_id', $electionId)
                ->where('abbreviation', $partyAbbr)
                ->first();

            Candidate::firstOrCreate(
                [
                    'election_id'  => $electionId,
                    'ballot_number' => (string) $ballot,
                ],
                [
                    'political_party_id' => $party?->id,
                    'name'               => $name,
                    'is_independent'     => $isIndependent,
                    'is_active'          => true,
                    'is_withdrawn'       => false,
                ]
            );
        }

        $this->command->info('✓ CandidateSeeder: ' . count(self::CANDIDATES) . ' candidates created.');
    }
}
