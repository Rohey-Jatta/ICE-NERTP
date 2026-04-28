<?php

namespace Database\Factories;

use App\Models\PoliticalParty;
use Illuminate\Database\Eloquent\Factories\Factory;

class PoliticalPartyFactory extends Factory
{
    protected $model = PoliticalParty::class;

    public function definition()
    {
        return [
            'election_id' => 1,
            'name' => $this->faker->unique()->word . ' Party',
            'abbreviation' => strtoupper($this->faker->lexify('???')),
            'color' => '#'. $this->faker->hexcolor,
            'leader_name' => $this->faker->name,
            'is_active' => true,
        ];
    }
}
