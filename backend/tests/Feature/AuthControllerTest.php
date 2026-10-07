<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_issues_an_expiring_token_for_active_credentials(): void
    {
        $user = $this->createUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'test-password',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token_type',
                'access_token',
                'expires_at',
                'user' => ['id', 'name', 'email', 'role', 'super', 'is_active'],
            ])
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonPath('user.super', true)
            ->assertJsonPath('user.is_active', true)
            ->assertJsonMissingPath('user.password');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'infratrack-web',
        ]);
        $this->assertNotEmpty($response->json('expires_at'));
    }

    public function test_returns_401_without_issuing_a_token_for_invalid_credentials(): void
    {
        $user = $this->createUser();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'incorrect-password',
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'The provided credentials are incorrect.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_returns_403_without_issuing_a_token_for_an_inactive_account(): void
    {
        $user = $this->createUser(['is_active' => false]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'test-password',
        ])->assertForbidden()
            ->assertJsonPath('message', 'This account is inactive.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_returns_422_for_missing_login_fields(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_returns_401_when_current_user_has_no_token(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_returns_only_safe_user_fields_with_a_valid_bearer_token(): void
    {
        $user = $this->createUser();
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.name', $user->name)
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonPath('user.super', true)
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.role_id');
    }

    public function test_returns_403_when_an_inactive_user_reuses_a_valid_token(): void
    {
        $user = $this->createUser(['is_active' => false]);
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertForbidden()
            ->assertJsonPath('message', 'This account is inactive.');
    }

    /** @param array<string, mixed> $attributes */
    private function createUser(array $attributes = []): User
    {
        $role = Role::query()->create([
            'name' => 'admin',
            'description' => 'Manages InfraTrack.',
        ]);

        return User::factory()->create(array_merge([
            'role_id' => $role->id,
            'super' => true,
            'is_active' => true,
            'password' => 'test-password',
        ], $attributes));
    }
}
