<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_account_creation_is_unauthenticated(): void
    {
        $this->postJson('/api/v1/users', $this->validUserPayload())
            ->assertUnauthorized();
    }

    public function test_returns_403_when_a_non_super_admin_creates_an_account(): void
    {
        $actor = $this->createActor(super: false);

        $this->withToken($actor->createToken('test-token')->plainTextToken)
            ->postJson('/api/v1/users', $this->validUserPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('users', 1);
    }

    public function test_creates_active_users_for_each_supported_role(): void
    {
        $actor = $this->createActor();
        $token = $actor->createToken('test-token')->plainTextToken;

        foreach (['admin', 'inspector', 'contractor'] as $role) {
            $payload = $this->validUserPayload([
                'email' => $role.'@example.test',
                'role' => $role,
            ]);

            $response = $this->withToken($token)->postJson('/api/v1/users', $payload);

            $response->assertCreated()
                ->assertJsonPath('user.email', $payload['email'])
                ->assertJsonPath('user.role', $role)
                ->assertJsonPath('user.super', false)
                ->assertJsonPath('user.is_active', true)
                ->assertJsonMissingPath('user.password');

            $user = User::query()->where('email', $payload['email'])->firstOrFail();
            $this->assertSame($role, $user->role->name);
            $this->assertTrue($user->is_active);
            $this->assertTrue(Hash::check($payload['password'], $user->password));
        }
    }

    public function test_allows_super_privilege_only_for_a_new_admin(): void
    {
        $actor = $this->createActor();
        $token = $actor->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/users', $this->validUserPayload([
                'email' => 'second-admin@example.test',
                'role' => 'admin',
                'super' => true,
            ]))
            ->assertCreated()
            ->assertJsonPath('user.super', true);

        $this->withToken($token)
            ->postJson('/api/v1/users', $this->validUserPayload([
                'email' => 'super-inspector@example.test',
                'role' => 'inspector',
                'super' => true,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('super');

        $this->assertDatabaseCount('users', 2);
    }

    public function test_rejects_duplicate_email_and_unconfirmed_password(): void
    {
        $actor = $this->createActor();
        User::factory()->create([
            'role_id' => $this->role('inspector')->id,
            'email' => 'existing@example.test',
        ]);
        $token = $actor->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/users', $this->validUserPayload([
                'email' => 'existing@example.test',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->withToken($token)
            ->postJson('/api/v1/users', $this->validUserPayload([
                'email' => 'new-user@example.test',
                'password_confirmation' => 'different-password',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_rejects_roles_outside_the_supported_set(): void
    {
        $actor = $this->createActor();

        $this->withToken($actor->createToken('test-token')->plainTextToken)
            ->postJson('/api/v1/users', $this->validUserPayload(['role' => 'super-admin']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_denies_inactive_super_admins(): void
    {
        $actor = $this->createActor(active: false);

        $this->withToken($actor->createToken('test-token')->plainTextToken)
            ->postJson('/api/v1/users', $this->validUserPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('users', 1);
    }

    public function test_admin_can_list_active_contractors(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->createActor(super: false);
        $contractorRole = $this->role('contractor');

        $activeContractor1 = User::factory()->create([
            'name' => 'Zara Contractor',
            'email' => 'zara@example.test',
            'role_id' => $contractorRole->id,
            'is_active' => true,
        ]);

        $activeContractor2 = User::factory()->create([
            'name' => 'Alex Contractor',
            'email' => 'alex@example.test',
            'role_id' => $contractorRole->id,
            'is_active' => true,
        ]);

        User::factory()->create([
            'name' => 'Inactive Contractor',
            'email' => 'inactive@example.test',
            'role_id' => $contractorRole->id,
            'is_active' => false,
        ]);

        User::factory()->create([
            'name' => 'Inspector User',
            'email' => 'inspector@example.test',
            'role_id' => $this->role('inspector')->id,
            'is_active' => true,
        ]);

        $response = $this->withToken($admin->createToken('test-token')->plainTextToken)
            ->getJson('/api/v1/users/contractors');

        $response->assertOk()
            ->assertJsonCount(2, 'contractors')
            ->assertJsonPath('contractors.0.name', 'Alex Contractor')
            ->assertJsonPath('contractors.1.name', 'Zara Contractor')
            ->assertJsonPath('contractors.0.role', 'contractor')
            ->assertJsonMissingPath('contractors.0.password');
    }

    public function test_non_admin_cannot_list_contractors(): void
    {
        $this->seed(RoleSeeder::class);

        $this->getJson('/api/v1/users/contractors')
            ->assertUnauthorized();

        $inspector = User::factory()->create([
            'role_id' => $this->role('inspector')->id,
            'is_active' => true,
        ]);

        $this->withToken($inspector->createToken('test-token')->plainTextToken)
            ->getJson('/api/v1/users/contractors')
            ->assertForbidden();

        $contractor = User::factory()->create([
            'role_id' => $this->role('contractor')->id,
            'is_active' => true,
        ]);

        $this->withToken($contractor->createToken('test-token')->plainTextToken)
            ->getJson('/api/v1/users/contractors')
            ->assertForbidden();

        $inactiveAdmin = $this->createActor(active: false);

        $this->withToken($inactiveAdmin->createToken('test-token')->plainTextToken)
            ->getJson('/api/v1/users/contractors')
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validUserPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New InfraTrack User',
            'email' => 'new-user@example.test',
            'role' => 'inspector',
            'password' => 'safe-test-password',
            'password_confirmation' => 'safe-test-password',
        ], $overrides);
    }

    private function createActor(bool $super = true, bool $active = true): User
    {
        $this->seed(RoleSeeder::class);

        return User::factory()->create([
            'role_id' => $this->role('admin')->id,
            'super' => $super,
            'is_active' => $active,
        ]);
    }

    private function role(string $name): Role
    {
        return Role::query()->firstOrCreate(
            ['name' => $name],
            ['description' => 'Test role.'],
        );
    }
}
