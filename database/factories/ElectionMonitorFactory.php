<?php

namespace Database\Factories;

use App\Models\ElectionMonitor;
use Illuminate\Database\Eloquent\Factories\Factory;

class ElectionMonitorFactory extends Factory
{
    protected $model = ElectionMonitor::class;

    public function definition()
    {
        return [
            'election_id' => 1,
            'user_id' => null,
            'assigned_ward_id' => null,
            'badge_number' => $this->faker->bothify('EM-####'),
            'is_active' => true,
        ];
    }
}
