<?php

namespace Database\Seeders;

use App\Models\AdministrativeHierarchy;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\PoliticalParty;
use App\Models\PollingStation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        echo "Creating admin user...\n";

        $admin = User::create([
            "name" => "System Administrator",
            "email" => "admin@iec.gm",
            "phone" => "+2205872319",
            "password" => Hash::make("password123"),
            "status" => "active",
            "employee_id" => "IEC-ADMIN-001",
            "two_factor_enabled" => false,
        ]);
        $admin->assignRole("iec-administrator");

        echo "Creating election...\n";

        $election = Election::create([
            "name" => "2026 Presidential Election",
            "type" => "presidential",
            "start_date" => now()->addDays(7),
            "end_date" => now()->addDays(7),
            "status" => "active",
            "allow_provisional_public_display" => true,
            "requires_party_acceptance" => true,
            "gps_validation_radius_meters" => 100,
            "created_by" => $admin->id,
        ]);

        echo "Creating hierarchy...\n";

        $adminArea = AdministrativeHierarchy::create([
            "election_id" => $election->id,
            "level" => "admin_area",
            "name" => "Greater Banjul Area",
            "code" => "GBA",
        ]);

        $const1 = AdministrativeHierarchy::create([
            "election_id" => $election->id,
            "level" => "constituency",
            "parent_id" => $adminArea->id,
            "name" => "Banjul Central",
            "code" => "BC",
        ]);

        $ward1 = AdministrativeHierarchy::create([
            "election_id" => $election->id,
            "level" => "ward",
            "parent_id" => $const1->id,
            "name" => "Ward 1 - Crab Island",
            "code" => "BC-W1",
        ]);

        echo "Creating parties and candidates...\n";

        $party = PoliticalParty::create([
            "election_id" => $election->id,
            "name" => "United Democratic Party",
            "slug" => Str::slug("United Democratic Party"),
            "abbreviation" => "UDP",
            "color" => "#FFFF00",
        ]);

        Candidate::create([
            "election_id" => $election->id,
            "political_party_id" => $party->id,
            "constituency_id" => $const1->id,
            "name" => "UDP Candidate",
            "ballot_number" => 1,
            "is_independent" => false,
            "is_active" => true,
        ]);

        echo "Creating polling station...\n";

        PollingStation::create([
            "election_id" => $election->id,
            "ward_id" => $ward1->id,
            "code" => "BC-W1-PS1",
            "name" => "Ward 1 - Polling Station 1",
            "registered_voters" => 500,
            "latitude" => 13.4549,
            "longitude" => -16.5790,
            "is_active" => true,
        ]);

        echo "Creating test user...\n";

        $officer = User::create([
            "name" => "Test Polling Officer",
            "email" => "officer@iec.gm",
            "phone" => "+2205872319",
            "password" => Hash::make("password123"),
            "status" => "active",
            "employee_id" => "TEST-POL",
            "two_factor_enabled" => false,
        ]);
        $officer->assignRole("polling-officer");

        echo "\n✅ TEST DATA CREATED!\n\n";
        echo "��� Email: officer@iec.gm\n";
        echo "��� Password: password123\n";
        echo "��� Phone: +2205872319\n";
    }
}
