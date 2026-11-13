<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class IssuePhotoStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_creation_returns_error_and_writes_no_rows_if_photo_storage_fails(): void
    {
        $inspector = $this->createUser();
        $filesystem = \Mockery::mock(FilesystemAdapter::class);
        $filesystem->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->once()->with('local')->andReturn($filesystem);

        $this->withToken($inspector->createToken('photo-storage-test')->plainTextToken)
            ->post('/api/v1/issues', $this->validIssuePayload())
            ->assertStatus(500)
            ->assertJsonPath('message', 'Unable to store the issue photo.');

        $this->assertDatabaseCount('issues', 0);
        $this->assertDatabaseCount('issue_status_history', 0);
    }

    public function test_failed_issue_update_rolls_back_and_removes_replacement_photo(): void
    {
        Storage::fake('local');
        $inspector = $this->createUser();
        $issue = Issue::factory()->reportedBy($inspector)->create([
            'photo_path' => 'issues/original-photo.jpg',
            'description' => 'Original description',
        ]);
        Storage::disk('local')->put($issue->photo_path, 'original photo');

        Event::listen('eloquent.updated: '.Issue::class, function (Issue $updatedIssue): void {
            if ($updatedIssue->description === 'trigger persistence failure') {
                throw new RuntimeException('Simulated failure after the issue row was updated.');
            }
        });

        $this->withToken($inspector->createToken('photo-storage-test')->plainTextToken)
            ->post('/api/v1/issues/'.$issue->id, [
                '_method' => 'PATCH',
                'photo' => UploadedFile::fake()->image('replacement.jpg', 100, 100),
                'description' => 'trigger persistence failure',
            ])
            ->assertServerError();

        $this->assertSame('Original description', $issue->fresh()->description);
        $this->assertSame('issues/original-photo.jpg', $issue->fresh()->photo_path);
        Storage::disk('local')->assertExists('issues/original-photo.jpg');
        $this->assertSame(['issues/original-photo.jpg'], Storage::disk('local')->allFiles('issues'));
    }

    /** @return array<string, mixed> */
    private function validIssuePayload(): array
    {
        return [
            'photo' => UploadedFile::fake()->image('pothole.jpg', 100, 100),
            'latitude' => 3.848,
            'longitude' => 11.502,
            'severity' => 'medium',
        ];
    }

    private function createUser(): User
    {
        $this->seed(RoleSeeder::class);
        $inspectorRole = Role::query()->where('name', 'inspector')->firstOrFail();

        return User::factory()->create([
            'role_id' => $inspectorRole->id,
            'super' => false,
            'is_active' => true,
        ]);
    }
}
