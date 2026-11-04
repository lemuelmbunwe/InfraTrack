<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_edit_assignment_title_and_instructions(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->createUser('admin');
        $contractor = $this->createUser('contractor');
        $assignment = $this->createAssignment($admin, $contractor);

        $this->withToken($admin->createToken('test-token')->plainTextToken)
            ->patchJson('/api/v1/assignments/'.$assignment->id, [
                'title' => 'Updated repairs',
                'instructions' => 'Use the approved material.',
            ])
            ->assertOk()
            ->assertJsonPath('assignment.title', 'Updated repairs')
            ->assertJsonPath('assignment.instructions', 'Use the approved material.')
            ->assertJsonPath('assignment.initial_contractor.id', $contractor->id);

        $this->assertDatabaseHas('assignments', [
            'id' => $assignment->id,
            'title' => 'Updated repairs',
            'instructions' => 'Use the approved material.',
        ]);
    }

    public function test_admin_can_clear_instructions_without_changing_the_title(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->createUser('admin');
        $contractor = $this->createUser('contractor');
        $assignment = $this->createAssignment($admin, $contractor);

        $this->withToken($admin->createToken('test-token')->plainTextToken)
            ->patchJson('/api/v1/assignments/'.$assignment->id, [
                'instructions' => null,
            ])
            ->assertOk()
            ->assertJsonPath('assignment.title', 'Original title')
            ->assertJsonPath('assignment.instructions', null);
    }

    public function test_contractors_cannot_edit_assignment_details(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->createUser('admin');
        $contractor = $this->createUser('contractor');
        $assignment = $this->createAssignment($admin, $contractor);

        $this->withToken($contractor->createToken('test-token')->plainTextToken)
            ->patchJson('/api/v1/assignments/'.$assignment->id, [
                'title' => 'Not allowed',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('assignments', [
            'id' => $assignment->id,
            'title' => 'Original title',
        ]);
    }

    public function test_update_requires_at_least_one_assignment_detail(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->createUser('admin');
        $contractor = $this->createUser('contractor');
        $assignment = $this->createAssignment($admin, $contractor);

        $this->withToken($admin->createToken('test-token')->plainTextToken)
            ->patchJson('/api/v1/assignments/'.$assignment->id, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assignment']);
    }

    private function createAssignment(User $admin, User $contractor): Assignment
    {
        return Assignment::query()->create([
            'title' => 'Original title',
            'instructions' => 'Original instructions',
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
