<?php

namespace Database\Factories;

use App\Models\Election;
use Illuminate\Database\Eloquent\Factories\Factory;

class ElectionFactory extends Factory
{
    protected $model = Election::class;

    public function definition()
    {
        return [
            'name' => '2021 Gambian Presidential Election',
            'type' => 'presidential',
            'description' => 'Simulated dataset for the 2021 Gambian presidential election',
            'start_date' => '2021-12-04',
            'end_date' => '2021-12-04',
            'results_deadline' => '2021-12-06',
            'status' => 'active',
            'requires_party_acceptance' => true,
            'allow_provisional_public_display' => true,
            'gps_validation_radius_meters' => 200,
        ];
    }
}
