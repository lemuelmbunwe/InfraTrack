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

class AssignmentRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_relations_expose_only_current_allocations_and_contractors(): void
    {
        $this->seed(RoleSeeder::class);

        $admin = $this->createUser('admin');
        $reporter = $this->createUser('inspector');
        $initialContractor = $this->createUser('contractor');
        $otherContractor = $this->createUser('contractor');
        $formerContractor = $this->createUser('contractor');

        $assignment = Assignment::query()->create([
            'title' => 'Main Street repairs',
            'instructions' => 'Repair the marked potholes.',
            'assigned_by' => $admin->id,
            'initial_contractor_id' => $initialContractor->id,
        ]);

        $firstIssue = Issue::factory()->reportedBy($reporter)->create();
        $secondIssue = Issue::factory()->reportedBy($reporter)->create();
        $legacyIssue = Issue::factory()->reportedBy($reporter)->create();

        IssueAssignment::factory()->create([
            'assignment_id' => $assignment->id,
            'issue_id' => $firstIssue->id,
            'contractor_id' => $formerContractor->id,
            'assigned_by' => $admin->id,
            'assigned_at' => now()->subDay(),
            'unassigned_at' => now(),
        ]);
        IssueAssignment::factory()->create([
            'assignment_id' => $assignment->id,
            'issue_id' => $firstIssue->id,
            'contractor_id' => $initialContractor->id,
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
        ]);
        IssueAssignment::factory()->create([
            'assignment_id' => $assignment->id,
            'issue_id' => $secondIssue->id,
            'contractor_id' => $otherContractor->id,
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
        ]);
        IssueAssignment::factory()->create([
            'assignment_id' => null,
            'issue_id' => $legacyIssue->id,
            'contractor_id' => $formerContractor->id,
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
        ]);

        $this->assertSame($admin->id, $assignment->creator->id);
        $this->assertSame($initialContractor->id, $assignment->initialContractor->id);
        $this->assertEqualsCanonicalizing(
            [$firstIssue->id, $secondIssue->id],
            $assignment->issues()->pluck('issues.id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$initialContractor->id, $otherContractor->id],
            $assignment->contractors()->pluck('users.id')->all(),
        );
        $this->assertEquals([$assignment->id], $firstIssue->workAssignments()->pluck('assignments.id')->all());
        $this->assertCount(2, $firstIssue->assignments);
        $this->assertNull($legacyIssue->workAssignments()->first());
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
