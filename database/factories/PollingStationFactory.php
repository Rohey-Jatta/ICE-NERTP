<?php

namespace Database\Factories;

use App\Models\PollingStation;
use Illuminate\Database\Eloquent\Factories\Factory;

class PollingStationFactory extends Factory
{
    protected $model = PollingStation::class;

    public function definition()
    {
        return [
            'election_id' => 1,
            'ward_id' => null,
            'code' => strtoupper($this->faker->bothify('PS-####')),
            'name' => 'Polling Station ' . $this->faker->unique()->numberBetween(1, 10000),
            'address' => $this->faker->streetAddress,
            'latitude' => $this->faker->latitude(13.3, 13.9),
            'longitude' => $this->faker->longitude(-16.9, -13.6),
            'registered_voters' => $this->faker->numberBetween(200, 1200),
            'is_active' => true,
        ];
    }
}
