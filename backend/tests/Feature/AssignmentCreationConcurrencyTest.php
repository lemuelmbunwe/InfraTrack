<?php

namespace Tests\Feature;

use App\Http\Requests\StoreAssignmentRequest;
use App\Models\Issue;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AssignmentCreationConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_competing_request_waits_for_issue_lock_and_reports_already_assigned(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('This concurrency test requires PostgreSQL row locking.');
        }

        $this->seed(RoleSeeder::class);

        $admin = $this->createUser('admin');
        $reporter = $this->createUser('inspector');
        $winningContractor = $this->createUser('contractor');
        $competingContractor = $this->createUser('contractor');
        $issue = Issue::factory()->reportedBy($reporter)->create();
        $token = $admin->createToken('concurrency-test')->plainTextToken;
        $connection = DB::connection();
        $firstRequest = null;
        $secondRequest = null;

        $connection->beginTransaction();

        try {
            User::query()
                ->whereKey($winningContractor->id)
                ->lockForUpdate()
                ->firstOrFail();
            Issue::query()
                ->whereKey($issue->id)
                ->lockForUpdate()
                ->firstOrFail();

            $firstRequest = $this->startAssignmentRequest($token, $winningContractor, $issue);
            $this->waitUntilRequestIsBlockedOnContractorLock($connection, $firstRequest);

            $secondRequest = $this->startAssignmentRequest($token, $competingContractor, $issue);
            $this->waitUntilRequestIsBlockedOnIssueLock($connection, $secondRequest);

            $connection->commit();

            $firstResponse = $this->completeRequest($firstRequest);
            $secondResponse = $this->completeRequest($secondRequest);
            $statusCodes = [$firstResponse['status'], $secondResponse['status']];
            sort($statusCodes);

            $this->assertSame([201, 422], $statusCodes);
            $conflictResponse = $firstResponse['status'] === 422 ? $firstResponse : $secondResponse;
            $this->assertSame(
                StoreAssignmentRequest::ISSUE_ALREADY_ASSIGNED_MESSAGE,
                $conflictResponse['body']['errors']['issue_ids'][0] ?? null,
            );
            $this->assertDatabaseCount('assignments', 1);
            $this->assertDatabaseCount('issue_assignments', 1);
            $currentAllocations = DB::table('issue_assignments')
                ->where('issue_id', $issue->id)
                ->whereNull('unassigned_at')
                ->count();
            $this->assertSame(1, (int) $currentAllocations);
            $this->assertDatabaseHas('issues', [
                'id' => $issue->id,
                'status' => 'assigned',
            ]);
        } finally {
            if ($firstRequest instanceof Process && $firstRequest->isRunning()) {
                $firstRequest->stop(1);
            }

            if ($secondRequest instanceof Process && $secondRequest->isRunning()) {
                $secondRequest->stop(1);
            }

            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
        }
    }

    private function startAssignmentRequest(string $token, User $contractor, Issue $issue): Process
    {
        $request = new Process(
            [PHP_BINARY, base_path('tests/Support/assignment_creation_request_worker.php')],
            base_path(),
            [
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'pgsql',
                'DB_HOST' => (string) config('database.connections.pgsql.host'),
                'DB_PORT' => (string) config('database.connections.pgsql.port'),
                'DB_DATABASE' => (string) config('database.connections.pgsql.database'),
                'DB_USERNAME' => (string) config('database.connections.pgsql.username'),
                'DB_PASSWORD' => (string) config('database.connections.pgsql.password'),
                'DB_URL' => '',
            ],
        );
        $request->setInput(json_encode([
            'token' => $token,
            'payload' => [
                'title' => 'Assignment for contractor '.$contractor->id,
                'contractor_id' => $contractor->id,
                'issue_ids' => [$issue->id],
            ],
        ], JSON_THROW_ON_ERROR));
        $request->setTimeout(null);
        $request->start();

        return $request;
    }

    private function waitUntilRequestIsBlockedOnIssueLock(Connection $connection, Process $request): void
    {
        $deadline = microtime(true) + 10;

        do {
            $waitingRequest = $connection->selectOne(<<<'SQL'
                select pid, query
                from pg_stat_activity
                where datname = current_database()
                  and pid <> pg_backend_pid()
                  and pg_backend_pid() = any(pg_blocking_pids(pid))
                  and query ilike '%issues%'
                  and query ilike '%for update%'
                limit 1
                SQL);

            if ($waitingRequest !== null) {
                return;
            }

            if (! $request->isRunning()) {
                $this->fail('The competing request finished before blocking on the issue lock: '.$request->getOutput());
            }

            usleep(50_000);
        } while (microtime(true) < $deadline);

        $waitingQueries = $connection->select(<<<'SQL'
            select pid, state, wait_event_type, wait_event, query
            from pg_stat_activity
            where datname = current_database()
              and pid <> pg_backend_pid()
            SQL);
        $this->fail('PostgreSQL did not report the competing request waiting on the issue row lock. Activity: '
            .json_encode($waitingQueries).'; worker output: '.$request->getOutput().'; worker error: '.$request->getErrorOutput());
    }

    private function waitUntilRequestIsBlockedOnContractorLock(Connection $connection, Process $request): void
    {
        $deadline = microtime(true) + 10;

        do {
            $waitingRequest = $connection->selectOne(<<<'SQL'
                select pid, query
                from pg_stat_activity
                where datname = current_database()
                  and pid <> pg_backend_pid()
                  and pg_backend_pid() = any(pg_blocking_pids(pid))
                  and query ilike '%users%'
                  and query ilike '%for update%'
                limit 1
                SQL);

            if ($waitingRequest !== null) {
                return;
            }

            if (! $request->isRunning()) {
                $this->fail('The first request finished before blocking on the contractor lock: '.$request->getOutput());
            }

            usleep(50_000);
        } while (microtime(true) < $deadline);

        $waitingQueries = $connection->select(<<<'SQL'
            select pid, state, wait_event_type, wait_event, query
            from pg_stat_activity
            where datname = current_database()
              and pid <> pg_backend_pid()
            SQL);
        $this->fail('PostgreSQL did not report the first request waiting on the contractor row lock. Activity: '
            .json_encode($waitingQueries).'; worker output: '.$request->getOutput().'; worker error: '.$request->getErrorOutput());
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function completeRequest(Process $request): array
    {
        $request->wait();
        $this->assertSame(0, $request->getExitCode(), $request->getErrorOutput());

        $response = json_decode($request->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        return [
            'status' => $response['status'],
            'body' => $response['body'],
        ];
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
