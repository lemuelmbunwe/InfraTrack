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
