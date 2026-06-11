<?php

namespace Tests\Feature;

use App\Models\AdministrativeHierarchy;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\PartyAcceptance;
use App\Models\PartyRepresentative;
use App\Models\PoliticalParty;
use App\Models\PollingStation;
use App\Models\Result;
use App\Models\ResultCandidateVote;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HighlightedIssueRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $officer;
    private Election $currentElection;
    private Election $historicalElection;
    private array $nodes;
    private PollingStation $station;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('iec-administrator');

        $this->officer = User::factory()->create();
        $this->officer->assignRole('polling-officer');

        $this->currentElection = Election::factory()->create([
            'created_by' => $this->admin->id,
            'name' => 'Current Election',
            'status' => 'active',
            'start_date' => '2026-01-01',
            'allow_provisional_public_display' => true,
        ]);

        $this->historicalElection = Election::factory()->create([
            'created_by' => $this->admin->id,
            'name' => 'Historical Election',
            'status' => 'certified',
            'start_date' => '2021-01-01',
            'allow_provisional_public_display' => true,
        ]);

        $this->nodes = $this->createHierarchy($this->currentElection);
        $this->station = PollingStation::factory()->create([
            'election_id' => $this->currentElection->id,
            'ward_id' => $this->nodes['ward']->id,
            'assigned_officer_id' => $this->officer->id,
            'registered_voters' => 1000,
        ]);
    }

    public function test_default_password_users_are_forced_to_change_password_and_admin_can_reset_them(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Default123'),
            'must_change_password' => true,
        ]);
        $user->assignRole('polling-officer');

        $this->actingAs($user)
            ->get('/officer/dashboard')
            ->assertRedirect(route('password.change'));

        $this->actingAs($user)
            ->post(route('password.change.store'), [
                'current_password' => 'Default123',
                'password' => 'Changed123',
                'password_confirmation' => 'Changed123',
            ])
            ->assertRedirect('/officer/dashboard');

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertNotNull($user->password_changed_at);

        $this->actingAs($this->admin)
            ->post(route('admin.users.reset-password', $user), ['password' => 'Reset1234'])
            ->assertRedirect();

        $this->assertTrue($user->refresh()->must_change_password);
        $this->assertTrue(Hash::check('Reset1234', $user->password));
    }

    public function test_constituency_report_page_and_exports_are_scoped_to_the_assigned_constituency_and_current_election(): void
    {
        $approver = $this->assignApprover('constituency-approver', $this->nodes['constituency']);

        $this->resultFor(
            $this->currentElection,
            $this->station,
            Result::STATUS_CONSTITUENCY_CERTIFIED,
            400,
            ['submitted_at' => now()->subHour()]
        );
        $this->resultFor(
            $this->currentElection,
            $this->station,
            Result::STATUS_PENDING_NATIONAL,
            450,
            ['submitted_at' => now()]
        );
        $this->resultFor($this->historicalElection, $this->station, Result::STATUS_CONSTITUENCY_CERTIFIED, 900);

        $this->actingAs($approver)
            ->get(route('constituency.reports'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Constituency/Reports')
                ->where('reportData.total_cast', 450)
                ->where('reportData.certified_count', 1)
            );

        $this->actingAs($approver)
            ->get(route('constituency.reports.export', ['report' => 'full', 'format' => 'pdf']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($approver)
            ->get(route('constituency.reports.export', ['report' => 'full', 'format' => 'excel']))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_role_dashboards_only_count_the_current_election(): void
    {
        $wardApprover = $this->assignApprover('ward-approver', $this->nodes['ward']);
        $constituencyApprover = $this->assignApprover('constituency-approver', $this->nodes['constituency']);
        $adminAreaApprover = $this->assignApprover('admin-area-approver', $this->nodes['admin_area']);
        $chairman = User::factory()->create();
        $chairman->assignRole('iec-chairman');

        $this->resultFor($this->currentElection, $this->station, Result::STATUS_PENDING_ADMIN_AREA, 300);
        $this->resultFor($this->historicalElection, $this->station, Result::STATUS_NATIONALLY_CERTIFIED, 900);

        $this->actingAs($wardApprover)
            ->get(route('ward.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('statistics.pending', 0)
                ->where('statistics.approved', 1)
            );

        $this->actingAs($constituencyApprover)
            ->get(route('constituency.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('statistics.pending', 0)
                ->where('statistics.certified', 1)
            );

        $this->actingAs($adminAreaApprover)
            ->get(route('admin-area.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('pendingResults', 1)
                ->where('statistics.approved', 0)
            );

        $this->actingAs($chairman)
            ->get(route('chairman.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('statistics.pipelineCounts.pending_admin_area', 1)
                ->where('statistics.pipelineCounts.nationally_certified', 0)
            );

        $this->actingAs($wardApprover)
            ->get(route('ward.analytics'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.totalVotes', 300)
            );

        $this->actingAs($adminAreaApprover)
            ->get(route('admin-area.analytics'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.totalVotes', 300)
            );

        $this->actingAs($chairman)
            ->get(route('chairman.analytics'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('nationalStats.votesCast', 0)
            );
    }

    public function test_public_map_region_data_only_uses_published_results_and_includes_published_images_and_party_reactions(): void
    {
        $party = PoliticalParty::create([
            'election_id' => $this->currentElection->id,
            'name' => 'National Party',
            'abbreviation' => 'NPP',
            'slug' => 'national-party',
            'color' => '#155AA6',
            'is_active' => true,
        ]);
        $candidate = Candidate::create([
            'election_id' => $this->currentElection->id,
            'political_party_id' => $party->id,
            'name' => 'Published Candidate',
            'ballot_number' => '1',
            'is_independent' => false,
            'is_active' => true,
        ]);

        $publishedStation = $this->station;
        $unpublishedStation = PollingStation::factory()->create([
            'election_id' => $this->currentElection->id,
            'ward_id' => $this->nodes['ward']->id,
            'registered_voters' => 800,
        ]);

        $published = $this->resultFor(
            $this->currentElection,
            $publishedStation,
            Result::STATUS_NATIONALLY_CERTIFIED,
            400,
            ['result_sheet_photo_path' => 'results/published.jpg', 'nationally_certified_at' => now()]
        );
        ResultCandidateVote::create([
            'result_id' => $published->id,
            'candidate_id' => $candidate->id,
            'election_id' => $this->currentElection->id,
            'votes' => 350,
        ]);

        $representativeUser = User::factory()->create();
        $representativeUser->assignRole('party-representative');
        $representative = PartyRepresentative::create([
            'user_id' => $representativeUser->id,
            'political_party_id' => $party->id,
            'election_id' => $this->currentElection->id,
            'designation' => 'Agent',
            'is_active' => true,
        ]);
        PartyAcceptance::create([
            'result_id' => $published->id,
            'political_party_id' => $party->id,
            'party_representative_id' => $representative->id,
            'election_id' => $this->currentElection->id,
            'status' => 'accepted_with_reservation',
            'comments' => 'Signed with note',
            'decided_at' => now(),
            'is_final' => true,
        ]);

        $this->resultFor($this->currentElection, $unpublishedStation, Result::STATUS_PENDING_WARD, 700);

        $this->get(route('results.map', ['election' => $this->currentElection->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/ResultsMap')
                ->has('regions', 1)
                ->where('regions.0.total_votes', 350)
                ->where('regions.0.reported_stations', 1)
                ->where('national.total_votes', 350)
                ->where('stations', function ($stations) use ($publishedStation, $unpublishedStation) {
                    $byId = collect($stations)->keyBy('id');
                    $published = $byId->get($publishedStation->id);
                    $unpublished = $byId->get($unpublishedStation->id);

                    return $published['is_published'] === true
                        && $published['total_votes_cast'] === 400
                        && $published['photo_url'] !== null
                        && count($published['party_acceptances']) === 1
                        && $published['party_acceptances'][0]['comments'] === 'Signed with note'
                        && $unpublished['is_published'] === false
                        && $unpublished['status'] === Result::STATUS_NOT_REPORTED
                        && $unpublished['result_id'] === null
                        && $unpublished['total_votes_cast'] === null
                        && $unpublished['candidate_votes'] === []
                        && $unpublished['party_acceptances'] === [];
                })
            );
    }

    public function test_public_station_page_gates_unpublished_results_and_internal_statuses(): void
    {
        $published = $this->resultFor(
            $this->currentElection,
            $this->station,
            Result::STATUS_NATIONALLY_CERTIFIED,
            200,
            ['nationally_certified_at' => now()]
        );
        $unpublishedStation = PollingStation::factory()->create([
            'election_id' => $this->currentElection->id,
            'ward_id' => $this->nodes['ward']->id,
        ]);
        $this->resultFor(
            $this->currentElection,
            $unpublishedStation,
            Result::STATUS_PENDING_CONSTITUENCY,
            600
        );

        $this->get(route('results.stations', ['election' => $this->currentElection->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/ResultsStations')
                ->where('stations', function ($stations) use ($published, $unpublishedStation) {
                    $publishedStation = collect($stations)->firstWhere('id', $published->polling_station_id);
                    $unpublishedPayload = collect($stations)->firstWhere('id', $unpublishedStation->id);
                    $forbiddenStatuses = [
                        Result::STATUS_SUBMITTED,
                        Result::STATUS_PENDING_PARTY_ACCEPTANCE,
                        Result::STATUS_PENDING_WARD,
                        Result::STATUS_WARD_CERTIFIED,
                        Result::STATUS_PENDING_CONSTITUENCY,
                        Result::STATUS_CONSTITUENCY_CERTIFIED,
                        Result::STATUS_PENDING_ADMIN_AREA,
                        Result::STATUS_ADMIN_AREA_CERTIFIED,
                        Result::STATUS_PENDING_NATIONAL,
                    ];

                    return $publishedStation['total_votes_cast'] === 200
                        && $publishedStation['is_published'] === true
                        && $unpublishedPayload['status'] === Result::STATUS_NOT_REPORTED
                        && !in_array($unpublishedPayload['status'], $forbiddenStatuses, true)
                        && $unpublishedPayload['total_votes_cast'] === null
                        && $unpublishedPayload['valid_votes'] === null
                        && $unpublishedPayload['candidate_votes'] === []
                        && $unpublishedPayload['party_acceptances'] === []
                        && $unpublishedPayload['photo_url'] === null
                        && $unpublishedPayload['is_published'] === false;
                })
            );
    }

    public function test_officer_result_submission_requires_and_stores_result_sheet_photo(): void
    {
        Queue::fake();
        Storage::fake('public');

        $candidate = Candidate::create([
            'election_id' => $this->currentElection->id,
            'name' => 'Photo Required Candidate',
            'ballot_number' => '1',
            'is_independent' => true,
            'is_active' => true,
        ]);

        $payload = [
            'election_id' => $this->currentElection->id,
            'registered_voters' => 1000,
            'total_votes_cast' => 100,
            'valid_votes' => 90,
            'rejected_votes' => 10,
            'candidate_votes' => [
                $candidate->id => 90,
            ],
        ];

        $this->actingAs($this->officer)
            ->from('/officer/results/submit')
            ->post(route('officer.results.store'), $payload)
            ->assertRedirect('/officer/results/submit')
            ->assertSessionHasErrors('photo');

        $this->actingAs($this->officer)
            ->post(route('officer.results.store'), array_merge($payload, [
                'photo' => UploadedFile::fake()->image('result-sheet.jpg'),
            ]))
            ->assertRedirect(route('officer.submissions'));

        $result = Result::where('polling_station_id', $this->station->id)
            ->where('election_id', $this->currentElection->id)
            ->firstOrFail();

        $this->assertNotNull($result->result_sheet_photo_path);
        Storage::disk('public')->assertExists($result->result_sheet_photo_path);
    }

    private function createHierarchy(Election $election): array
    {
        $national = $this->node($election, 'national', 'National', null, 'NAT-' . $election->id);
        $adminArea = $this->node($election, 'admin_area', 'Region One', $national->id, 'AA-' . $election->id);
        $constituency = $this->node($election, 'constituency', 'Constituency One', $adminArea->id, 'CON-' . $election->id);
        $ward = $this->node($election, 'ward', 'Ward One', $constituency->id, 'WRD-' . $election->id);

        return [
            'national' => $national,
            'admin_area' => $adminArea,
            'constituency' => $constituency,
            'ward' => $ward,
        ];
    }

    private function node(Election $election, string $level, string $name, ?int $parentId, string $code): AdministrativeHierarchy
    {
        return AdministrativeHierarchy::create([
            'election_id' => $election->id,
            'level' => $level,
            'parent_id' => $parentId,
            'name' => $name,
            'code' => $code,
            'slug' => strtolower($code),
            'is_active' => true,
        ]);
    }

    private function assignApprover(string $role, AdministrativeHierarchy $node): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $node->update(['assigned_approver_id' => $user->id]);

        return $user;
    }

    private function resultFor(
        Election $election,
        PollingStation $station,
        string $status,
        int $votesCast,
        array $overrides = []
    ): Result {
        return Result::factory()->create(array_merge([
            'polling_station_id' => $station->id,
            'election_id' => $election->id,
            'user_id' => $this->officer->id,
            'submitted_by' => $this->officer->id,
            'total_registered_voters' => $station->registered_voters,
            'total_votes_cast' => $votesCast,
            'valid_votes' => max(0, $votesCast - 5),
            'rejected_votes' => min(5, $votesCast),
            'certification_status' => $status,
        ], $overrides));
    }
}
