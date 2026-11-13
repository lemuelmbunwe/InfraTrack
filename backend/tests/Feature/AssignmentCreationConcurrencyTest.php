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
        $firstApplicationName = 'assignment-winner-'.$winningContractor->id;
        $secondApplicationName = 'assignment-racer-'.$competingContractor->id;

        $connection->beginTransaction();

        try {
            // Hold writes to assignments so the first request pauses after it locks the issue.
            $connection->statement('LOCK TABLE assignments IN SHARE MODE');
            $testConnectionPid = (int) $connection->selectOne('SELECT pg_backend_pid() AS pid')->pid;

            $firstRequest = $this->startAssignmentRequest(
                $token,
                $winningContractor,
                $issue,
                $firstApplicationName,
            );
            $firstRequestPid = $this->waitUntilWorkerIsBlockedBy(
                $connection,
                $firstApplicationName,
                $testConnectionPid,
                $firstRequest,
                'The first request did not reach the assignment insert while the test held its table lock.',
            );

            $secondRequest = $this->startAssignmentRequest(
                $token,
                $competingContractor,
                $issue,
                $secondApplicationName,
            );
            $this->waitUntilWorkerIsBlockedBy(
                $connection,
                $secondApplicationName,
                $firstRequestPid,
                $secondRequest,
                'The competing request did not wait on the issue lock held by the first request.',
            );

            $connection->commit();

            $firstResponse = $this->completeRequest($firstRequest);
            $secondResponse = $this->completeRequest($secondRequest);
            $statusCodes = [$firstResponse['status'], $secondResponse['status']];
            sort($statusCodes);

            $this->assertSame([201, 409], $statusCodes);
            $conflictResponse = $firstResponse['status'] === 409 ? $firstResponse : $secondResponse;
            $this->assertSame(StoreAssignmentRequest::ISSUE_ALREADY_ASSIGNED_MESSAGE, $conflictResponse['body']['message'] ?? null);
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

    private function startAssignmentRequest(
        string $token,
        User $contractor,
        Issue $issue,
        string $applicationName,
    ): Process {
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
            'application_name' => $applicationName,
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

    private function waitUntilWorkerIsBlockedBy(
        Connection $connection,
        string $applicationName,
        int $blockerPid,
        Process $request,
        string $failureMessage,
    ): int {
        $deadline = microtime(true) + 10;

        do {
            $connection->selectOne('SELECT pg_stat_clear_snapshot()');
            $waitingRequest = $connection->selectOne(
                <<<'SQL'
                    select pid
                    from pg_stat_activity
                    where datname = current_database()
                      and application_name = ?
                      and wait_event_type = 'Lock'
                      and ? = any(pg_blocking_pids(pid))
                    limit 1
                    SQL,
                [$applicationName, $blockerPid],
            );

            if ($waitingRequest !== null) {
                return (int) $waitingRequest->pid;
            }

            if (! $request->isRunning()) {
                $this->fail($failureMessage.' Worker output: '.$request->getOutput().'; worker error: '.$request->getErrorOutput());
            }

            usleep(50_000);
        } while (microtime(true) < $deadline);

        $activity = $connection->select(
            <<<'SQL'
                select pid, application_name, state, wait_event_type, wait_event, query
                from pg_stat_activity
                where datname = current_database()
                  and pid <> pg_backend_pid()
                SQL,
        );
        $this->fail($failureMessage.' Timed out waiting for the worker lock state; worker output: '
            .$request->getOutput().'; worker error: '.$request->getErrorOutput().'; worker pid: '
            .$request->getPid().'; worker status: '.json_encode($request->getStatus()).'; activity: '.json_encode($activity));
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
