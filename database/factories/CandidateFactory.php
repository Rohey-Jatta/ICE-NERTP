<?php

namespace Database\Factories;

use App\Models\Candidate;
use Illuminate\Database\Eloquent\Factories\Factory;

class CandidateFactory extends Factory
{
    protected $model = Candidate::class;

    public function definition()
    {
        return [
            'election_id' => 1,
            'political_party_id' => null,
            'name' => $this->faker->name,
            'ballot_number' => $this->faker->numberBetween(1, 99),
            'is_independent' => false,
            'is_active' => true,
        ];
    }
}
