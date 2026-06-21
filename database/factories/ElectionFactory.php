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

    /**
     * Election currently in the submitting status — i.e. polling has
     * closed and officers are actively filing results. Useful for testing
     * the CurrentElectionResolver against the 'submitting' status.
     */
    public function submitting(): static
    {
        return $this->state(fn() => ['status' => 'submitting']);
    }

    /**
     * Election in certifying status (approval chain running).
     */
    public function certifying(): static
    {
        return $this->state(fn() => ['status' => 'certifying']);
    }

    /**
     * Election that is fully closed/published — should NOT be picked up
     * by CurrentElectionResolver.
     */
    public function certified(): static
    {
        return $this->state(fn() => ['status' => 'certified']);
    }

    /**
     * Election still in draft — should NOT be picked up by
     * CurrentElectionResolver.
     */
    public function draft(): static
    {
        return $this->state(fn() => ['status' => 'draft']);
    }
}