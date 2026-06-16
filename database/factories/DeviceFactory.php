<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeviceFactory extends Factory
{
    protected $model = Device::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'device_fingerprint' => hash('sha256', $this->faker->unique()->uuid),
            'device_name' => $this->faker->userAgent,
            'device_type' => 'desktop',
            'os' => 'Linux',
            'browser' => 'Chrome',
            'verified_at' => now(),
            'verified_by_ip' => '127.0.0.1',
            'last_used_at' => now(),
            'last_used_ip' => '127.0.0.1',
            'is_trusted' => true,
            'is_revoked' => false,
        ];
    }
}
