<?php

namespace Database\Factories;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition()
    {
        return [
            'user_id' => null,
            'election_id' => 1,
            'action' => 'comment',
            'event' => 'created',
            'module' => 'Comments',
            'auditable_type' => null,
            'auditable_id' => null,
            'old_values' => null,
            'new_values' => ['comment' => $this->faker->sentence],
            'user_role' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Seeder',
        ];
    }
}
