<?php

namespace Database\Factories;

use App\Models\AdministrativeHierarchy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AdministrativeHierarchyFactory extends Factory
{
    protected $model = AdministrativeHierarchy::class;

    public function definition()
    {
        return [
            'election_id' => 1,
            'level' => 'admin_area',
            'parent_id' => null,
            'name' => $this->faker->unique()->city(),
            'code' => strtoupper(Str::random(3)),
            'center_latitude' => $this->faker->latitude(-13, 13),
            'center_longitude' => $this->faker->longitude(-17, -13),
            'registered_voters' => $this->faker->numberBetween(10000, 200000),
            'is_active' => true,
        ];
    }

    public function constituency()
    {
        return $this->state(function (array $attributes) {
            return ['level' => 'constituency'];
        });
    }

    public function ward()
    {
        return $this->state(function (array $attributes) {
            return ['level' => 'ward'];
        });
    }
}
