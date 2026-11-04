<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Issue;
use App\Models\IssueAssignment;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_assignment_for_multiple_reported_issues(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->createUser('admin');
        $reporter = $this->createUser('inspector');
        $contractor = $this->createUser('contractor');
        $issues = [
            Issue::factory()->reportedBy($reporter)->create(),
            Issue::factory()->reportedBy($reporter)->create(),
        ];

        $response = $this->withToken($admin->createToken('test-token')->plainTextToken)
            ->postJson('/api/v1/assignments', [
                'title' => 'Main Street repairs',
                'instructions' => 'Repair the marked potholes.',
                'contractor_id' => $contractor->id,
                'issue_ids' => collect($issues)->pluck('id')->all(),
            ]);

        $response->assertCreated()
            ->assertJsonPath('assignment.title', 'Main Street repairs')
            ->assertJsonPath('assignment.instructions', 'Repair the marked potholes.')
            ->assertJsonPath('assignment.initial_contractor.id', $contractor->id)
            ->assertJsonCount(1, 'assignment.contractors')
            ->assertJsonCount(2, 'assignment.issues');

        $assignment = Assignment::query()->firstOrFail();
        $this->assertSame($contractor->id, $assignment->initial_contractor_id);
        $this->assertSame($admin->id, $assignment->assigned_by);

        foreach ($issues as $issue) {
            $this->assertDatabaseHas('issue_assignments', [
                'assignment_id' => $assignment->id,
                'issue_id' => $issue->id,
                'contractor_id' => $contractor->id,
                'assigned_by' => $admin->id,
                'unassigned_at' => null,
            ]);
            $this->assertDatabaseHas('issues', [
                'id' => $issue->id,
                'status' => 'assigned',
            ]);
        }
    }

    public function test_creation_rejects_ineligible_issue_without_partial_writes(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->createUser('admin');
        $reporter = $this->createUser('inspector');
        $contractor = $this->createUser('contractor');
        $eligibleIssue = Issue::factory()->reportedBy($reporter)->create();
        $ineligibleIssue = Issue::factory()->reportedBy($reporter)->create(['status' => 'assigned']);

        $this->withToken($admin->createToken('test-token')->plainTextToken)
            ->postJson('/api/v1/assignments', [
                'title' => 'Main Street repairs',
                'contractor_id' => $contractor->id,
                'issue_ids' => [$eligibleIssue->id, $ineligibleIssue->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['issue_ids.1']);

        $this->assertDatabaseCount('assignments', 0);
        $this->assertDatabaseCount('issue_assignments', 0);
        $this->assertDatabaseHas('issues', [
            'id' => $eligibleIssue->id,
            'status' => 'reported',
        ]);
    }

    public function test_only_active_admins_can_create_assignments(): void
    {
        $this->seed(RoleSeeder::class);
        $contractor = $this->createUser('contractor');
        $reporter = $this->createUser('inspector');
        $issue = Issue::factory()->reportedBy($reporter)->create();

        $this->withToken($contractor->createToken('test-token')->plainTextToken)
            ->postJson('/api/v1/assignments', [
                'title' => 'Main Street repairs',
                'contractor_id' => $contractor->id,
                'issue_ids' => [$issue->id],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('assignments', 0);
        $this->assertDatabaseCount('issue_assignments', 0);
    }

    public function test_only_active_contractor_accounts_can_be_selected(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->createUser('admin');
        $reporter = $this->createUser('inspector');
        $inactiveContractor = $this->createUser('contractor', ['is_active' => false]);
        $issue = Issue::factory()->reportedBy($reporter)->create();

        $this->withToken($admin->createToken('test-token')->plainTextToken)
            ->postJson('/api/v1/assignments', [
                'title' => 'Main Street repairs',
                'contractor_id' => $inactiveContractor->id,
                'issue_ids' => [$issue->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contractor_id']);

        $this->assertDatabaseCount('assignments', 0);
        $this->assertDatabaseCount('issue_assignments', 0);
    }

    /** @param array<string, mixed> $attributes */
    private function createUser(string $roleName, array $attributes = []): User
    {
        $role = Role::query()->where('name', $roleName)->firstOrFail();

        return User::factory()->create(array_merge([
            'role_id' => $role->id,
            'super' => false,
            'is_active' => true,
        ], $attributes));
    }
}
