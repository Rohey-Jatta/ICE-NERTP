<?php

namespace Tests\Feature;

use App\Models\AdministrativeHierarchy;
use App\Models\Election;
use App\Models\PollingStation;
use App\Models\Result;
use App\Models\ResultCertification;
use App\Models\User;
use App\Services\CertificationWorkflowService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificationWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    private CertificationWorkflowService $workflow;
    private Election $election;
    private User $officer;
    private PollingStation $station;
    private array $nodes = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->workflow = app(CertificationWorkflowService::class);

        $admin = User::factory()->create();
        $admin->assignRole('iec-administrator');

        $this->election = Election::factory()->create([
            'created_by' => $admin->id,
        ]);
        $this->officer = User::factory()->create();
        $this->officer->assignRole('polling-officer');

        $this->nodes['national'] = $this->createNode('national', 'National', null, 'NAT');
        $this->nodes['admin_area'] = $this->createNode('admin_area', 'Admin Area', $this->nodes['national']->id, 'AA');
        $this->nodes['constituency'] = $this->createNode('constituency', 'Constituency', $this->nodes['admin_area']->id, 'CON');
        $this->nodes['ward'] = $this->createNode('ward', 'Ward', $this->nodes['constituency']->id, 'WRD');

        $this->station = PollingStation::factory()->create([
            'election_id' => $this->election->id,
            'ward_id' => $this->nodes['ward']->id,
            'assigned_officer_id' => $this->officer->id,
        ]);
    }

    public function test_it_approves_the_active_certification_path(): void
    {
        $wardApprover = $this->approver('ward');
        $result = $this->makeResult(Result::STATUS_PENDING_WARD);

        $this->workflow->approve($result, $wardApprover, 'ward', 'looks valid');

        $result->refresh();
        $this->assertSame(Result::STATUS_PENDING_CONSTITUENCY, $result->certification_status);
        $this->assertDatabaseHas('result_certifications', [
            'result_id' => $result->id,
            'certification_level' => 'ward',
            'approver_id' => $wardApprover->id,
            'status' => 'approved',
            'comments' => 'looks valid',
        ]);
        $this->assertCount(1, $result->versions);
    }

    public function test_ward_approval_accepts_legacy_party_gate_records(): void
    {
        $wardApprover = $this->approver('ward');
        $result = $this->makeResult(Result::STATUS_PENDING_PARTY_ACCEPTANCE);

        $this->workflow->approve($result, $wardApprover, 'ward');

        $this->assertSame(Result::STATUS_PENDING_CONSTITUENCY, $result->refresh()->certification_status);
    }

    public function test_wrong_level_approval_is_rejected(): void
    {
        $constituencyApprover = $this->approver('constituency');
        $result = $this->makeResult(Result::STATUS_PENDING_WARD);

        $this->expectException(\Exception::class);

        try {
            $this->workflow->approve($result, $constituencyApprover, 'constituency');
        } finally {
            $this->assertSame(Result::STATUS_PENDING_WARD, $result->refresh()->certification_status);
            $this->assertSame(0, ResultCertification::where('result_id', $result->id)->count());
        }
    }

    public function test_rejections_return_to_the_correct_prior_stage(): void
    {
        $cases = [
            'ward' => [Result::STATUS_PENDING_WARD, Result::STATUS_SUBMITTED],
            'constituency' => [Result::STATUS_PENDING_CONSTITUENCY, Result::STATUS_PENDING_WARD],
            'admin_area' => [Result::STATUS_PENDING_ADMIN_AREA, Result::STATUS_PENDING_CONSTITUENCY],
            'national' => [Result::STATUS_PENDING_NATIONAL, Result::STATUS_PENDING_ADMIN_AREA],
        ];

        foreach ($cases as $level => [$startStatus, $expectedStatus]) {
            $approver = $this->approver($level);
            $result = $this->makeResult($startStatus);

            $this->workflow->reject($result, $approver, $level, "{$level} issue");

            $result->refresh();
            $this->assertSame($expectedStatus, $result->certification_status, "Failed asserting {$level} rejection target.");
            $this->assertSame(1, $result->rejection_count);
            $this->assertSame("{$level} issue", $result->last_rejection_reason);
            $this->assertDatabaseHas('result_certifications', [
                'result_id' => $result->id,
                'certification_level' => $level,
                'status' => 'rejected',
                'comments' => "{$level} issue",
            ]);
        }
    }

    public function test_national_approval_sets_certification_timestamp(): void
    {
        $chairman = $this->approver('national');
        $result = $this->makeResult(Result::STATUS_PENDING_NATIONAL);

        $this->workflow->approve($result, $chairman, 'national');

        $result->refresh();
        $this->assertSame(Result::STATUS_NATIONALLY_CERTIFIED, $result->certification_status);
        $this->assertNotNull($result->nationally_certified_at);
    }

    private function createNode(string $level, string $name, ?int $parentId, string $code): AdministrativeHierarchy
    {
        return AdministrativeHierarchy::create([
            'election_id' => $this->election->id,
            'level' => $level,
            'parent_id' => $parentId,
            'name' => $name,
            'code' => $code,
            'slug' => strtolower($code),
            'is_active' => true,
        ]);
    }

    private function approver(string $level): User
    {
        $role = match ($level) {
            'ward' => 'ward-approver',
            'constituency' => 'constituency-approver',
            'admin_area' => 'admin-area-approver',
            'national' => 'iec-chairman',
        };

        $user = User::factory()->create();
        $user->assignRole($role);
        $this->nodes[$level]->update(['assigned_approver_id' => $user->id]);

        return $user;
    }

    private function makeResult(string $status): Result
    {
        return Result::factory()->create([
            'polling_station_id' => $this->station->id,
            'election_id' => $this->election->id,
            'user_id' => $this->officer->id,
            'submitted_by' => $this->officer->id,
            'total_registered_voters' => $this->station->registered_voters,
            'certification_status' => $status,
        ]);
    }
}
