<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\IssueAssignment;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IssueControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_inspector_creates_an_issue_with_private_photo_and_reported_history(): void
    {
        Storage::fake('local');
        $inspector = $this->createUser('inspector');
        $token = $inspector->createToken('test-token')->plainTextToken;
        $payload = $this->validIssuePayload();

        $response = $this->withToken($token)->post('/api/v1/issues', $payload);

        $response->assertCreated()
            ->assertJsonPath('issue.reporter.id', $inspector->id)
            ->assertJsonPath('issue.latitude', 3.848)
            ->assertJsonPath('issue.longitude', 11.502)
            ->assertJsonPath('issue.address', null)
            ->assertJsonPath('issue.address_text', 'Test location, Yaounde')
            ->assertJsonPath('issue.severity', 'medium')
            ->assertJsonPath('issue.status', 'reported')
            ->assertJsonMissingPath('issue.photo_path');

        $issue = Issue::query()->firstOrFail();
        Storage::disk('local')->assertExists($issue->photo_path);
        $this->assertStringStartsWith('issues/', $issue->photo_path);
        $this->assertDatabaseHas('issue_status_history', [
            'issue_id' => $issue->id,
            'changed_by' => $inspector->id,
            'old_status' => null,
            'new_status' => 'reported',
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/issues/'.$issue->id.'/photo')
            ->assertOk();
    }

    public function test_returns_401_when_an_inspector_creates_an_issue_without_a_token(): void
    {
        $this->postJson('/api/v1/issues', [])->assertUnauthorized();
    }

    public function test_rejects_an_admin_or_contractor_creating_an_issue(): void
    {
        foreach (['admin', 'contractor'] as $role) {
            $user = $this->createUser($role);

            $this->withToken($user->createToken('test-token')->plainTextToken)
                ->post('/api/v1/issues', $this->validIssuePayload())
                ->assertForbidden();
        }

        $this->assertDatabaseCount('issues', 0);
    }

    public function test_rejects_missing_photo_invalid_coordinates_and_severity(): void
    {
        $inspector = $this->createUser('inspector');

        $this->withToken($inspector->createToken('test-token')->plainTextToken)
            ->postJson('/api/v1/issues', [
                'latitude' => 95,
                'longitude' => 11.5,
                'severity' => 'critical',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo', 'latitude', 'severity']);

        $this->assertDatabaseCount('issues', 0);
    }

    public function test_inspector_only_lists_and_views_their_own_issues(): void
    {
        $inspector = $this->createUser('inspector');
        $otherInspector = $this->createUser('inspector');
        $ownIssue = $this->createIssue($inspector);
        $otherIssue = $this->createIssue($otherInspector);
        $token = $inspector->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/issues')
            ->assertOk()
            ->assertJsonCount(1, 'issues')
            ->assertJsonPath('issues.0.id', $ownIssue->id);

        $this->withToken($token)
            ->getJson('/api/v1/issues/'.$otherIssue->id)
            ->assertNotFound();
    }

    public function test_admin_can_view_all_issues(): void
    {
        $inspector = $this->createUser('inspector');
        $issue = $this->createIssue($inspector);
        $admin = $this->createUser('admin');

        $this->withToken($admin->createToken('test-token')->plainTextToken)
            ->getJson('/api/v1/issues/'.$issue->id)
            ->assertOk()
            ->assertJsonPath('issue.id', $issue->id);
    }

    public function test_contractor_can_only_list_assigned_issues(): void
    {
        $contractorA = $this->createUser('contractor');
        $contractorB = $this->createUser('contractor');
        $inspector = $this->createUser('inspector');

        $assignedIssue1 = $this->createIssue($inspector, [
            'assigned_to' => $contractorA->id,
            'assigned_at' => now(),
            'status' => 'assigned',
        ]);

        $resolvedIssue = $this->createIssue($inspector, [
            'assigned_to' => $contractorA->id,
            'assigned_at' => now()->subDay(),
            'status' => 'resolved',
        ]);

        $unassignedIssue = $this->createIssue($inspector, [
            'status' => 'reported',
        ]);

        $otherContractorIssue = $this->createIssue($inspector, [
            'assigned_to' => $contractorB->id,
            'assigned_at' => now(),
            'status' => 'assigned',
        ]);

        $this->assertTrue($contractorA->can('viewAny', Issue::class));

        $response = $this->withToken($contractorA->createToken('test-token')->plainTextToken)
            ->getJson('/api/v1/issues');

        $response->assertOk()
            ->assertJsonCount(2, 'issues')
            ->assertJsonPath('issues.0.id', $resolvedIssue->id)
            ->assertJsonPath('issues.1.id', $assignedIssue1->id);

        $returnedIds = collect($response->json('issues'))->pluck('id')->all();
        $this->assertContains($assignedIssue1->id, $returnedIds);
        $this->assertContains($resolvedIssue->id, $returnedIds);
        $this->assertNotContains($unassignedIssue->id, $returnedIds);
        $this->assertNotContains($otherContractorIssue->id, $returnedIds);
    }

    public function test_contractor_can_view_assigned_issue_and_photo(): void
    {
        Storage::fake('local');
        $contractorA = $this->createUser('contractor');
        $contractorB = $this->createUser('contractor');
        $inspector = $this->createUser('inspector');

        $assignedIssue = $this->createIssue($inspector, [
            'assigned_to' => $contractorA->id,
            'assigned_at' => now(),
            'status' => 'assigned',
        ]);
        Storage::disk('local')->put($assignedIssue->photo_path, 'fake photo content');

        $resolvedIssue = $this->createIssue($inspector, [
            'assigned_to' => $contractorA->id,
            'assigned_at' => now()->subDay(),
            'status' => 'resolved',
        ]);

        $unassignedIssue = $this->createIssue($inspector, ['status' => 'reported']);
        $otherIssue = $this->createIssue($inspector, [
            'assigned_to' => $contractorB->id,
            'assigned_at' => now(),
            'status' => 'assigned',
        ]);

        $token = $contractorA->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/issues/'.$assignedIssue->id)
            ->assertOk()
            ->assertJsonPath('issue.id', $assignedIssue->id)
            ->assertJsonPath('issue.assigned_contractor.id', $contractorA->id)
            ->assertJsonPath('issue.assigned_contractor.name', $contractorA->name)
            ->assertJsonPath('issue.assigned_at', $assignedIssue->activeAssignment->assigned_at->toIso8601String());

        $this->withToken($token)
            ->getJson('/api/v1/issues/'.$resolvedIssue->id)
            ->assertOk()
            ->assertJsonPath('issue.id', $resolvedIssue->id)
            ->assertJsonPath('issue.status', 'resolved');

        $this->withToken($token)
            ->getJson('/api/v1/issues/'.$assignedIssue->id.'/photo')
            ->assertOk();

        $this->withToken($token)
            ->getJson('/api/v1/issues/'.$unassignedIssue->id)
            ->assertNotFound();

        $this->withToken($token)
            ->getJson('/api/v1/issues/'.$otherIssue->id)
            ->assertNotFound();
    }

    public function test_inspector_can_edit_their_unassigned_issue_but_cannot_change_status(): void
    {
        $inspector = $this->createUser('inspector');
        $issue = $this->createIssue($inspector);
        $token = $inspector->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/v1/issues/'.$issue->id, [
                'latitude' => 3.85,
                'longitude' => 11.51,
                'address_text' => 'Updated location',
                'description' => 'Updated report details',
                'severity' => 'high',
            ])
            ->assertOk()
            ->assertJsonPath('issue.description', 'Updated report details')
            ->assertJsonPath('issue.severity', 'high');

        $this->withToken($token)
            ->patchJson('/api/v1/issues/'.$issue->id, ['status' => 'resolved'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertSame('reported', $issue->fresh()->status);
        $this->assertDatabaseCount('issue_status_history', 1);
    }

    public function test_inspector_cannot_edit_an_assigned_issue(): void
    {
        $inspector = $this->createUser('inspector');
        $issue = $this->createIssue($inspector, ['status' => 'assigned']);

        $this->withToken($inspector->createToken('test-token')->plainTextToken)
            ->patchJson('/api/v1/issues/'.$issue->id, ['description' => 'Changed'])
            ->assertForbidden();
    }

    public function test_inspector_can_replace_the_photo_on_an_unassigned_issue(): void
    {
        Storage::fake('local');
        $inspector = $this->createUser('inspector');
        $issue = $this->createIssue($inspector);
        Storage::disk('local')->put($issue->photo_path, 'old image');

        $this->withToken($inspector->createToken('test-token')->plainTextToken)
            ->post('/api/v1/issues/'.$issue->id, [
                '_method' => 'PATCH',
                'photo' => UploadedFile::fake()->image('replacement.jpg', 100, 100),
            ])
            ->assertOk();

        $updatedIssue = $issue->fresh();
        Storage::disk('local')->assertExists($updatedIssue->photo_path);
        Storage::disk('local')->assertMissing('issues/test-photo.jpg');
    }

    public function test_admin_can_change_status_and_records_status_history(): void
    {
        $inspector = $this->createUser('inspector');
        $issue = $this->createIssue($inspector);
        $admin = $this->createUser('admin');

        $this->withToken($admin->createToken('test-token')->plainTextToken)
            ->patchJson('/api/v1/issues/'.$issue->id, [
                'status' => 'in_progress',
                'severity' => 'high',
                'description' => 'Reviewed by Admin',
            ])
            ->assertOk()
            ->assertJsonPath('issue.status', 'in_progress')
            ->assertJsonPath('issue.severity', 'high');

        $this->assertDatabaseHas('issue_status_history', [
            'issue_id' => $issue->id,
            'changed_by' => $admin->id,
            'old_status' => 'reported',
            'new_status' => 'in_progress',
        ]);
    }

    public function test_admin_cannot_change_inspector_owned_fields(): void
    {
        $inspector = $this->createUser('inspector');
        $issue = $this->createIssue($inspector);
        $admin = $this->createUser('admin');

        $this->withToken($admin->createToken('test-token')->plainTextToken)
            ->patchJson('/api/v1/issues/'.$issue->id, ['latitude' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('latitude');

        $this->assertSame('3.8480000', $issue->fresh()->latitude);
    }

    public function test_inspector_cannot_read_another_reporters_photo(): void
    {
        Storage::fake('local');
        $reporter = $this->createUser('inspector');
        $otherInspector = $this->createUser('inspector');
        $issue = $this->createIssue($reporter);
        Storage::disk('local')->put($issue->photo_path, 'fake image content');

        $this->withToken($otherInspector->createToken('test-token')->plainTextToken)
            ->getJson('/api/v1/issues/'.$issue->id.'/photo')
            ->assertNotFound();
    }

    public function test_admin_can_assign_contractor_to_reported_unassigned_issue(): void
    {
        $inspector = $this->createUser('inspector');
        $issue = $this->createIssue($inspector, ['status' => 'reported']);
        $admin = $this->createUser('admin');
        $contractor = $this->createUser('contractor');

        $historyCountBefore = DB::table('issue_status_history')->count();

        $response = $this->withToken($admin->createToken('test-token')->plainTextToken)
            ->patchJson('/api/v1/issues/'.$issue->id.'/assignment', [
                'contractor_id' => $contractor->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('issue.id', $issue->id)
            ->assertJsonPath('issue.status', 'assigned')
            ->assertJsonPath('issue.assigned_contractor.id', $contractor->id)
            ->assertJsonPath('issue.assigned_contractor.name', $contractor->name);

        $this->assertNotNull($response->json('issue.assigned_at'));

        $fresh = $issue->fresh();
        $this->assertSame('assigned', $fresh->status);
        $this->assertDatabaseHas('issue_assignments', [
            'issue_id' => $issue->id,
            'contractor_id' => $contractor->id,
            'assigned_by' => $admin->id,
            'unassigned_at' => null,
        ]);
        $this->assertTrue(
            $fresh->activeAssignment()->where('contractor_id', $contractor->id)->exists(),
        );

        // Verify NO issue_status_history record was added
        $this->assertSame($historyCountBefore, DB::table('issue_status_history')->count());
    }

    public function test_admin_cannot_assign_already_assigned_issue(): void
    {
        $inspector = $this->createUser('inspector');
        $contractor1 = $this->createUser('contractor');
        $contractor2 = $this->createUser('contractor');
        $issue = $this->createIssue($inspector, [
            'assigned_to' => $contractor1->id,
            'assigned_at' => now(),
            'status' => 'assigned',
        ]);
        $admin = $this->createUser('admin');

        $this->withToken($admin->createToken('test-token')->plainTextToken)
            ->patchJson('/api/v1/issues/'.$issue->id.'/assignment', [
                'contractor_id' => $contractor2->id,
            ])
            ->assertForbidden();

        $this->assertTrue(
            $issue->fresh()->activeAssignment()->where('contractor_id', $contractor1->id)->exists(),
        );
        $this->assertDatabaseCount('issue_assignments', 1);
    }

    public function test_admin_cannot_assign_issue_not_in_reported_status(): void
    {
        $inspector = $this->createUser('inspector');
        $issue = $this->createIssue($inspector, ['status' => 'in_progress']);
        $admin = $this->createUser('admin');
        $contractor = $this->createUser('contractor');

        $this->withToken($admin->createToken('test-token')->plainTextToken)
            ->patchJson('/api/v1/issues/'.$issue->id.'/assignment', [
                'contractor_id' => $contractor->id,
            ])
            ->assertForbidden();
    }

    public function test_non_admin_cannot_assign_issue(): void
    {
        $inspector = $this->createUser('inspector');
        $contractor = $this->createUser('contractor');
        $issue = $this->createIssue($inspector);

        $this->patchJson('/api/v1/issues/'.$issue->id.'/assignment', [
            'contractor_id' => $contractor->id,
        ])->assertUnauthorized();

        $this->withToken($inspector->createToken('test-token')->plainTextToken)
            ->patchJson('/api/v1/issues/'.$issue->id.'/assignment', [
                'contractor_id' => $contractor->id,
            ])
            ->assertForbidden();

        $this->withToken($contractor->createToken('test-token')->plainTextToken)
            ->patchJson('/api/v1/issues/'.$issue->id.'/assignment', [
                'contractor_id' => $contractor->id,
            ])
            ->assertForbidden();
    }

    public function test_assignment_validates_contractor_account(): void
    {
        $inspector = $this->createUser('inspector');
        $issue = $this->createIssue($inspector);
        $admin = $this->createUser('admin');
        $token = $admin->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/v1/issues/'.$issue->id.'/assignment', [
                'contractor_id' => 999999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contractor_id');

        $otherInspector = $this->createUser('inspector');
        $this->withToken($token)
            ->patchJson('/api/v1/issues/'.$issue->id.'/assignment', [
                'contractor_id' => $otherInspector->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contractor_id');

        $inactiveContractor = User::factory()->create([
            'role_id' => Role::query()->where('name', 'contractor')->value('id'),
            'is_active' => false,
        ]);

        $this->withToken($token)
            ->patchJson('/api/v1/issues/'.$issue->id.'/assignment', [
                'contractor_id' => $inactiveContractor->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contractor_id');
    }

    public function test_assigned_contractor_can_update_status(): void
    {
        $inspector = $this->createUser('inspector');
        $contractor = $this->createUser('contractor');
        $issue = $this->createIssue($inspector, [
            'assigned_to' => $contractor->id,
            'assigned_at' => now(),
            'status' => 'assigned',
        ]);
        $token = $contractor->createToken('test-token')->plainTextToken;

        $historyCountBefore = DB::table('issue_status_history')->count();

        foreach (['in_progress', 'resolved', 'assigned', 'reported'] as $targetStatus) {
            $response = $this->withToken($token)
                ->patchJson('/api/v1/issues/'.$issue->id.'/status', [
                    'status' => $targetStatus,
                ]);

            $response->assertOk()
                ->assertJsonPath('issue.status', $targetStatus)
                ->assertJsonPath('issue.assigned_contractor.id', $contractor->id);

            $this->assertSame($targetStatus, $issue->fresh()->status);
        }

        // Verify NO issue_status_history records were created
        $this->assertSame($historyCountBefore, DB::table('issue_status_history')->count());
    }

    public function test_contractor_cannot_update_status_of_unassigned_or_other_contractor_issue(): void
    {
        $inspector = $this->createUser('inspector');
        $contractor1 = $this->createUser('contractor');
        $contractor2 = $this->createUser('contractor');

        $unassignedIssue = $this->createIssue($inspector, ['status' => 'reported']);
        $assignedToOther = $this->createIssue($inspector, [
            'assigned_to' => $contractor2->id,
            'assigned_at' => now(),
            'status' => 'assigned',
        ]);

        $token = $contractor1->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/v1/issues/'.$unassignedIssue->id.'/status', ['status' => 'in_progress'])
            ->assertForbidden();

        $this->withToken($token)
            ->patchJson('/api/v1/issues/'.$assignedToOther->id.'/status', ['status' => 'in_progress'])
            ->assertForbidden();

        // Non-contractor roles cannot use this endpoint
        $admin = $this->createUser('admin');
        $this->withToken($admin->createToken('test-token')->plainTextToken)
            ->patchJson('/api/v1/issues/'.$assignedToOther->id.'/status', ['status' => 'in_progress'])
            ->assertForbidden();
    }

    public function test_contractor_status_update_validates_status(): void
    {
        $inspector = $this->createUser('inspector');
        $contractor = $this->createUser('contractor');
        $issue = $this->createIssue($inspector, [
            'assigned_to' => $contractor->id,
            'assigned_at' => now(),
            'status' => 'assigned',
        ]);

        $this->withToken($contractor->createToken('test-token')->plainTextToken)
            ->patchJson('/api/v1/issues/'.$issue->id.'/status', [
                'status' => 'not_a_valid_status',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_contractor_cannot_edit_other_issue_fields(): void
    {
        $inspector = $this->createUser('inspector');
        $contractor = $this->createUser('contractor');
        $issue = $this->createIssue($inspector, [
            'assigned_to' => $contractor->id,
            'assigned_at' => now(),
            'status' => 'assigned',
        ]);

        $this->withToken($contractor->createToken('test-token')->plainTextToken)
            ->patchJson('/api/v1/issues/'.$issue->id, [
                'description' => 'Contractor trying to edit description',
                'severity' => 'low',
            ])
            ->assertForbidden();
    }

    public function test_inspector_cannot_edit_issue_past_reported_status(): void
    {
        $inspector = $this->createUser('inspector');
        $contractor = $this->createUser('contractor');
        $token = $inspector->createToken('test-token')->plainTextToken;

        foreach (['assigned', 'in_progress', 'resolved'] as $status) {
            $issue = $this->createIssue($inspector, [
                'assigned_to' => $contractor->id,
                'assigned_at' => now(),
                'status' => $status,
            ]);

            $this->withToken($token)
                ->patchJson('/api/v1/issues/'.$issue->id, ['description' => 'Attempted edit'])
                ->assertForbidden();
        }
    }

    /** @param array<string, mixed> $overrides */
    private function validIssuePayload(array $overrides = []): array
    {
        return array_merge([
            'photo' => UploadedFile::fake()->image('pothole.jpg', 100, 100),
            'latitude' => 3.848,
            'longitude' => 11.502,
            'address_text' => 'Test location, Yaounde',
            'severity' => 'medium',
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function createIssue(User $reporter, array $overrides = []): Issue
    {
        $contractorId = $overrides['assigned_to'] ?? null;
        $assignedAt = $overrides['assigned_at'] ?? now();
        $assignedBy = $overrides['assigned_by'] ?? $reporter->id;
        unset($overrides['assigned_to'], $overrides['assigned_at'], $overrides['assigned_by']);

        $issue = Issue::factory()->reportedBy($reporter)->create(array_merge([
            'photo_path' => 'issues/test-photo.jpg',
            'latitude' => 3.848,
            'longitude' => 11.502,
            'severity' => 'medium',
            'status' => 'reported',
            'reported_at' => now(),
        ], $overrides));

        $issue->statusHistory()->create([
            'changed_by' => $reporter->id,
            'old_status' => null,
            'new_status' => $issue->status,
            'changed_at' => $issue->reported_at,
        ]);

        if ($contractorId !== null) {
            IssueAssignment::factory()->create([
                'issue_id' => $issue->id,
                'contractor_id' => $contractorId,
                'assigned_by' => $assignedBy,
                'assigned_at' => $assignedAt,
                'unassigned_at' => null,
            ]);
            $issue->load('activeAssignment.contractor');
        }

        return $issue;
    }

    private function createUser(string $roleName): User
    {
        $this->seed(RoleSeeder::class);
        $role = Role::query()->where('name', $roleName)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'super' => false,
            'is_active' => true,
        ]);
    }
}
