<?php

namespace Database\Seeders;

use App\Models\Result;
use App\Models\ResultCertification;
use App\Models\AdministrativeHierarchy;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class WorkflowSeeder extends Seeder
{
    public function run()
    {
        Role::firstOrCreate(['name' => 'constituency-approver']);
        Role::firstOrCreate(['name' => 'ward-approver']);

        // For a subset of results, simulate approvals and rejections
        $results = Result::inRandomOrder()->limit(500)->get();

        foreach ($results as $result) {
            DB::transaction(function () use ($result) {
                // ward certification
                $ward = AdministrativeHierarchy::find($result->pollingStation->ward_id);
                $wardApproverId = $ward->assigned_approver_id;
                if (! $wardApproverId) {
                    $wardApprover = User::factory()->create([
                        'name' => $ward->name . ' Approver',
                        'email' => 'ward.approver.' . $ward->id . '@iec.local',
                    ]);
                    $wardApprover->assignRole('ward-approver');
                    $ward->assigned_approver_id = $wardApprover->id;
                    $ward->saveQuietly();
                    $wardApproverId = $wardApprover->id;
                }

                $wardCert = ResultCertification::create([
                    'result_id' => $result->id,
                    'certification_level' => 'ward',
                    'hierarchy_node_id' => $ward->id,
                    'approver_id' => $wardApproverId,
                    'status' => (rand(1, 10) <= 8) ? 'approved' : 'rejected',
                    'comments' => (rand(1,10) <= 8) ? 'Looks good' : 'Mismatch - please resubmit',
                    'assigned_at' => now()->subHours(rand(2, 48)),
                    'decided_at' => now()->subHours(rand(1, 2)),
                ]);

                if ($wardCert->status === 'rejected') {
                    $result->certification_status = Result::STATUS_REJECTED;
                    $result->last_rejection_reason = 'Ward level validation failed';
                    $result->last_rejected_by = $wardApproverId;
                    $result->last_rejected_at = now();
                    $result->saveQuietly();
                } else {
                    // escalate to constituency
                    $const = $ward->parent;
                    if ($const) {
                        $constApprover = $const->assigned_approver_id;
                        if (! $constApprover) {
                            $constApproverUser = User::factory()->create([
                                'name' => $const->name . ' Approver',
                                'email' => 'constituency.approver.' . $const->id . '@iec.local',
                            ]);
                            $constApproverUser->assignRole('constituency-approver');
                            $const->assigned_approver_id = $constApproverUser->id;
                            $const->saveQuietly();
                            $constApprover = $constApproverUser->id;
                        }

                        ResultCertification::create([
                            'result_id' => $result->id,
                            'certification_level' => 'constituency',
                            'hierarchy_node_id' => $const->id,
                            'approver_id' => $constApprover,
                            'status' => (rand(1, 10) <= 9) ? 'approved' : 'rejected',
                            'comments' => 'Reviewed at constituency',
                            'assigned_at' => now()->subHours(rand(1, 24)),
                            'decided_at' => now()->subHours(rand(1, 6)),
                        ]);
                    }
                }
            });
        }
    }
}
