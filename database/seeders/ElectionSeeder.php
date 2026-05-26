<?php

namespace Database\Seeders;

use App\Models\Election;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class ElectionSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'iec-administrator', 'guard_name' => 'web']);

        $admin = User::firstOrCreate(
            ['email' => 'seeder-admin@iec.gm'],
            [
                'name'     => 'Seeder Admin',
                'password' => Hash::make('password123'),
                'status'   => 'active',
            ]
        );
        $admin->syncRoles(['iec-administrator']);

        // Visible admin for the UI
        $visibleAdmin = User::updateOrCreate(
            ['email' => 'admina@iec.gm'],
            [
                'name'        => 'System Administrator',
                'password'    => Hash::make('password123'),
                'phone'       => '+2205872319',
                'employee_id' => 'IEC-ADMIN-001',
                'status'      => 'active',
            ]
        );
        $visibleAdmin->syncRoles(['iec-administrator']);

        Election::firstOrCreate(
            ['slug' => 'gambia-2021-presidential'],
            [
                'name'                             => '2021 Gambian Presidential Election',
                'type'                             => 'presidential',
                'description'                      => 'Official 2021 Gambian Presidential Election held on 4 December 2021.',
                // 'legal_instrument'                 => 'Elections Act 2015',
                'start_date'                       => '2021-12-04',
                'end_date'                         => '2021-12-04',
                // 'results_deadline'                 => '2021-12-06',
                'status'                           => 'certified', // Fully certified historical data
                'requires_party_acceptance'        => true,
                'allow_provisional_public_display' => true,
                'gps_validation_radius_meters'     => 200,
                'created_by'                       => $admin->id,
            ]
        );

        $this->command->info('✓ Election seeded: 2021 Gambian Presidential Election (status: certified)');
    }
}
