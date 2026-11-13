<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IssueDatabaseConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_rejects_an_unsupported_issue_status(): void
    {
        $this->requirePostgresConstraints();
        $issue = $this->createIssue();

        $this->expectException(QueryException::class);

        DB::table('issues')->where('id', $issue->id)->update(['status' => 'archived']);
    }

    public function test_database_rejects_an_unsupported_issue_severity(): void
    {
        $this->requirePostgresConstraints();
        $issue = $this->createIssue();

        $this->expectException(QueryException::class);

        DB::table('issues')->where('id', $issue->id)->update(['severity' => 'critical']);
    }

    private function requirePostgresConstraints(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Issue CHECK constraints are installed only on PostgreSQL.');
        }
    }

    private function createIssue(): Issue
    {
        $this->seed(RoleSeeder::class);
        $inspectorRole = Role::query()->where('name', 'inspector')->firstOrFail();
        $reporter = User::factory()->create([
            'role_id' => $inspectorRole->id,
            'super' => false,
            'is_active' => true,
        ]);

        return Issue::factory()->reportedBy($reporter)->create();
    }
}
