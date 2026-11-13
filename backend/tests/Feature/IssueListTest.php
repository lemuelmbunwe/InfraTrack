<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssueListTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_issue_list_is_paginated_and_reports_first_across_pages(): void
    {
        $admin = $this->createUser('admin');
        $reporter = $this->createUser('inspector');
        $reportedIssues = collect(range(0, 15))->map(fn (int $index) => Issue::factory()
            ->reportedBy($reporter)
            ->create(['reported_at' => now()->subDays(30 - $index)]));
        $resolvedIssues = collect(range(0, 1))->map(fn (int $index) => Issue::factory()
            ->reportedBy($reporter)
            ->create([
                'status' => 'resolved',
                'reported_at' => now()->addDays($index + 1),
            ]));
        $token = $admin->createToken('issue-list-test')->plainTextToken;

        $firstPage = $this->withToken($token)->getJson('/api/v1/issues');

        $firstPage->assertOk()
            ->assertJsonCount(15, 'issues')
            ->assertJsonPath('pagination.current_page', 1)
            ->assertJsonPath('pagination.per_page', 15)
            ->assertJsonPath('pagination.total', 18)
            ->assertJsonPath('pagination.last_page', 2)
            ->assertJsonPath('summary.total', 18)
            ->assertJsonPath('summary.reported', 16)
            ->assertJsonPath('summary.resolved', 2);

        $this->assertSame(
            array_fill(0, 15, 'reported'),
            collect($firstPage->json('issues'))->pluck('status')->all(),
        );

        $secondPage = $this->withToken($token)->getJson('/api/v1/issues?page=2');

        $secondPage->assertOk()
            ->assertJsonCount(3, 'issues')
            ->assertJsonPath('pagination.current_page', 2);

        $this->assertSame(
            [
                $reportedIssues[0]->id,
                $resolvedIssues[1]->id,
                $resolvedIssues[0]->id,
            ],
            collect($secondPage->json('issues'))->pluck('id')->all(),
        );
    }

    public function test_status_filter_changes_rows_but_summary_counts_all_visible_issues(): void
    {
        $admin = $this->createUser('admin');
        $reporter = $this->createUser('inspector');
        Issue::factory()->reportedBy($reporter)->create(['severity' => 'high']);
        Issue::factory()->reportedBy($reporter)->create(['status' => 'assigned']);
        Issue::factory()->reportedBy($reporter)->create(['status' => 'in_progress']);
        $resolved = Issue::factory()->reportedBy($reporter)->create(['status' => 'resolved']);
        $token = $admin->createToken('issue-list-test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/issues?status=resolved&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'issues')
            ->assertJsonPath('issues.0.id', $resolved->id)
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('summary.total', 4)
            ->assertJsonPath('summary.reported', 1)
            ->assertJsonPath('summary.assigned', 1)
            ->assertJsonPath('summary.in_progress', 1)
            ->assertJsonPath('summary.resolved', 1)
            ->assertJsonPath('summary.high_severity', 1);
    }

    public function test_issue_list_summary_and_filter_are_scoped_to_the_inspector(): void
    {
        $inspector = $this->createUser('inspector');
        $otherInspector = $this->createUser('inspector');
        $ownIssue = Issue::factory()->reportedBy($inspector)->create();
        Issue::factory()->reportedBy($otherInspector)->create(['status' => 'reported']);
        Issue::factory()->reportedBy($otherInspector)->create(['status' => 'resolved']);
        $token = $inspector->createToken('issue-list-test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/issues?status=reported')
            ->assertOk()
            ->assertJsonCount(1, 'issues')
            ->assertJsonPath('issues.0.id', $ownIssue->id)
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('summary.reported', 1)
            ->assertJsonPath('summary.resolved', 0);
    }

    public function test_issue_list_rejects_unknown_status_and_invalid_pagination_values(): void
    {
        $admin = $this->createUser('admin');
        $token = $admin->createToken('issue-list-test')->plainTextToken;

        foreach ([
            ['/api/v1/issues?status=closed', 'status'],
            ['/api/v1/issues?page=0', 'page'],
            ['/api/v1/issues?per_page=51', 'per_page'],
        ] as [$url, $field]) {
            $this->withToken($token)
                ->getJson($url)
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field);
        }
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
