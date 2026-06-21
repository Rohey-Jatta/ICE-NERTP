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
            // election_id intentionally omitted. Polling stations are no
            // longer created against a specific election — see the
            // election-assignment refactor (CurrentElectionResolver).
            // The column remains nullable on the table for the historical
            // "last seen election" marker, but factories should not set it.
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

    /**
     * Test-only state for asserting historical "last seen election"
     * behavior (e.g. markSeenUnder()). Not used by default — most tests
     * should create stations election-agnostically and resolve the
     * current election via the seeded ElectionFactory + status.
     */
    public function lastSeenUnder(int $electionId): static
    {
        return $this->state(fn() => ['election_id' => $electionId]);
    }
}