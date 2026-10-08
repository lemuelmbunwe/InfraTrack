<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_contractor_cannot_list_issues(): void
    {
        $contractor = $this->createUser('contractor');
        $this->assertFalse($contractor->can('viewAny', Issue::class));

        $this->withToken($contractor->createToken('test-token')->plainTextToken)
            ->getJson('/api/v1/issues')
            ->assertForbidden();
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
