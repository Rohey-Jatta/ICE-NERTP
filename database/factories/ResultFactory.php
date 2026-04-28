<?php

namespace Database\Factories;

use App\Models\Result;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResultFactory extends Factory
{
    protected $model = Result::class;

    public function definition()
    {
        return [
            'polling_station_id' => null,
            'election_id' => 1,
            'submission_uuid' => $this->faker->uuid,
            'user_id' => null,
            'total_registered_voters' => $this->faker->numberBetween(200, 1200),
            'total_votes_cast' => 0,
            'valid_votes' => 0,
            'rejected_votes' => 0,
            'disputed_votes' => 0,
            'certification_status' => Result::STATUS_SUBMITTED,
            'submitted_by' => null,
            'submitted_at' => now(),
        ];
    }
}
