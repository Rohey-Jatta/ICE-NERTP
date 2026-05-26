<?php

namespace Database\Seeders;

use App\Models\Result;
use App\Models\ResultCertification;
use App\Models\AdministrativeHierarchy;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkflowSeeder extends Seeder
{
    public function run()
    {
        // Skip if certifications already exist
        if (ResultCertification::exists()) {
            $this->command->info('WorkflowSeeder: certifications already exist, skipping.');
            return;
        }

        $results = Result::inRandomOrder()->limit(500)->get();

        foreach ($results as $result) {
            // Skip if this result already has a certification record
            if (ResultCertification::where('result_id', $result->id)->exists()) {
                continue;
            }

            DB::transaction(function () use ($result) {
                $ward           = AdministrativeHierarchy::find($result->pollingStation->ward_id);
                $wardApproverId = $ward->assigned_approver_id;

                if (!$wardApproverId) {
                    $wardApprover = User::firstOrCreate(
                        ['email' => 'ward.approver.' . $ward->id . '@iec.local'],
                        [
                            'name'     => $ward->name . ' Approver',
                            'password' => bcrypt('password123'),
                            'status'   => 'active',
                        ]
                    );
                    if (!$wardApprover->hasRole('ward-approver')) {
                        $wardApprover->assignRole('ward-approver');
                    }
                    $ward->assigned_approver_id = $wardApprover->id;
                    $ward->saveQuietly();
                    $wardApproverId = $wardApprover->id;
                }

                $wardStatus = (rand(1, 10) <= 8) ? 'approved' : 'rejected';

                ResultCertification::create([
                    'result_id'           => $result->id,
                    'certification_level' => 'ward',
                    'hierarchy_node_id'   => $ward->id,
                    'approver_id'         => $wardApproverId,
                    'status'              => $wardStatus,
                    'comments'            => $wardStatus === 'approved' ? 'Looks good' : 'Mismatch - please resubmit',
                    'assigned_at'         => now()->subHours(rand(2, 48)),
                    'decided_at'          => now()->subHours(rand(1, 2)),
                    'created_at'          => now(),
                ]);

                if ($wardStatus === 'rejected') {
                    $result->certification_status  = Result::STATUS_REJECTED;
                    $result->last_rejection_reason = 'Ward level validation failed';
                    $result->last_rejected_by      = $wardApproverId;
                    $result->last_rejected_at      = now();
                    $result->saveQuietly();
                } else {
                    $const = $ward->parent;
                    if ($const) {
                        $constApprover = $const->assigned_approver_id;
                        if (!$constApprover) {
                            $constApproverUser = User::firstOrCreate(
                                ['email' => 'constituency.approver.' . $const->id . '@iec.local'],
                                [
                                    'name'     => $const->name . ' Approver',
                                    'password' => bcrypt('password123'),
                                    'status'   => 'active',
                                ]
                            );
                            if (!$constApproverUser->hasRole('constituency-approver')) {
                                $constApproverUser->assignRole('constituency-approver');
                            }
                            $const->assigned_approver_id = $constApproverUser->id;
                            $const->saveQuietly();
                            $constApprover = $constApproverUser->id;
                        }

                        ResultCertification::create([
                            'result_id'           => $result->id,
                            'certification_level' => 'constituency',
                            'hierarchy_node_id'   => $const->id,
                            'approver_id'         => $constApprover,
                            'status'              => (rand(1, 10) <= 9) ? 'approved' : 'rejected',
                            'comments'            => 'Reviewed at constituency',
                            'assigned_at'         => now()->subHours(rand(1, 24)),
                            'decided_at'          => now()->subHours(rand(1, 6)),
                            'created_at'          => now(),
                        ]);
                    }
                }
            });
        }

        $this->command->info('WorkflowSeeder: certifications created.');
    }
}