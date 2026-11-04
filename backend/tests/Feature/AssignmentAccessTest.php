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

class AssignmentAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_and_view_assignments_with_no_current_issues(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->createUser('admin');
        $contractor = $this->createUser('contractor');
        $assignment = $this->createAssignment($admin, $contractor);
        $token = $admin->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/assignments')
            ->assertOk()
            ->assertJsonCount(1, 'assignments')
            ->assertJsonPath('assignments.0.id', $assignment->id)
            ->assertJsonCount(0, 'assignments.0.issues')
            ->assertJsonCount(0, 'assignments.0.contractors');

        $this->withToken($token)
            ->getJson('/api/v1/assignments/'.$assignment->id)
            ->assertOk()
            ->assertJsonPath('assignment.id', $assignment->id);
    }

    public function test_contractor_can_view_only_involved_assignments_and_safe_roster_fields(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->createUser('admin');
        $reporter = $this->createUser('inspector');
        $contractor = $this->createUser('contractor');
        $otherContractor = $this->createUser('contractor');
        $assignment = $this->createAssignment($admin, $otherContractor);
        $unrelatedAssignment = $this->createAssignment($admin, $otherContractor);
        $ownIssue = Issue::factory()->reportedBy($reporter)->create([
            'status' => 'in_progress',
            'description' => 'Private issue description',
        ]);
        $otherIssue = Issue::factory()->reportedBy($reporter)->create([
            'status' => 'assigned',
            'description' => 'Another private issue description',
        ]);

        IssueAssignment::factory()->create([
            'assignment_id' => $assignment->id,
            'issue_id' => $ownIssue->id,
            'contractor_id' => $contractor->id,
            'assigned_by' => $admin->id,
        ]);
        IssueAssignment::factory()->create([
            'assignment_id' => $assignment->id,
            'issue_id' => $otherIssue->id,
            'contractor_id' => $otherContractor->id,
            'assigned_by' => $admin->id,
        ]);

        $formerIssue = Issue::factory()->reportedBy($reporter)->create(['status' => 'assigned']);
        IssueAssignment::factory()->create([
            'assignment_id' => $unrelatedAssignment->id,
            'issue_id' => $formerIssue->id,
            'contractor_id' => $contractor->id,
            'assigned_by' => $admin->id,
            'unassigned_at' => now(),
        ]);
        IssueAssignment::factory()->create([
            'assignment_id' => $unrelatedAssignment->id,
            'issue_id' => $formerIssue->id,
            'contractor_id' => $otherContractor->id,
            'assigned_by' => $admin->id,
        ]);

        $token = $contractor->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/assignments')
            ->assertOk()
            ->assertJsonCount(1, 'assignments')
            ->assertJsonPath('assignments.0.id', $assignment->id)
            ->assertJsonCount(2, 'assignments.0.issues')
            ->assertJsonPath('assignments.0.contractors.0.id', $contractor->id)
            ->assertJsonPath('assignments.0.contractors.1.id', $otherContractor->id)
            ->assertJsonMissing(['description' => 'Private issue description'])
            ->assertJsonMissing(['photo_url' => route('api.v1.issues.photo', ['issue' => $otherIssue->id])]);

        $this->withToken($token)
            ->getJson('/api/v1/assignments/'.$assignment->id)
            ->assertOk()
            ->assertJsonPath('assignment.issues.0.status', 'in_progress');

        $this->withToken($token)
            ->getJson('/api/v1/assignments/'.$unrelatedAssignment->id)
            ->assertNotFound();

        $this->withToken($token)
            ->getJson('/api/v1/issues/'.$ownIssue->id)
            ->assertOk();

        $this->withToken($token)
            ->getJson('/api/v1/issues/'.$otherIssue->id)
            ->assertNotFound();

        $this->withToken($token)
            ->getJson('/api/v1/issues/'.$otherIssue->id.'/photo')
            ->assertNotFound();
    }

    public function test_inspectors_cannot_list_assignments(): void
    {
        $this->seed(RoleSeeder::class);
        $inspector = $this->createUser('inspector');

        $this->withToken($inspector->createToken('test-token')->plainTextToken)
            ->getJson('/api/v1/assignments')
            ->assertForbidden();
    }

    private function createAssignment(User $admin, User $contractor): Assignment
    {
        return Assignment::query()->create([
            'title' => 'Main Street repairs',
            'instructions' => null,
            'assigned_by' => $admin->id,
            'initial_contractor_id' => $contractor->id,
        ]);
    }

    private function createUser(string $roleName): User
    {
        $role = Role::query()->where('name', $roleName)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'super' => false,
            'is_active' => true,
        ]);
    }
}
