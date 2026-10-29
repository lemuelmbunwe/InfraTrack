<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignIssueRequest;
use App\Http\Requests\StoreIssueRequest;
use App\Http\Requests\UpdateIssueRequest;
use App\Http\Requests\UpdateIssueStatusRequest;
use App\Http\Resources\IssueResource;
use App\Models\Issue;
use App\Models\IssueAssignment;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class IssueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Issue::class);

        $role = $request->user()->role?->name;
        $userId = $request->user()->id;

        $issues = Issue::query()
            ->with($this->issueRelations())
            ->when(
                $role === 'inspector',
                fn ($query) => $query->where('reported_by', $userId),
            )
            ->when(
                $role === 'contractor',
                fn ($query) => $query->whereHas(
                    'activeAssignment',
                    fn ($q) => $q->where('contractor_id', $userId),
                ),
            )
            ->orderByDesc('reported_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'issues' => IssueResource::collection($issues)->resolve($request),
        ]);
    }

    public function store(StoreIssueRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $photoPath = $validated['photo']->store('issues', 'local');

        if (! $photoPath) {
            return response()->json(['message' => 'Unable to store the issue photo.'], 500);
        }

        try {
            $issue = DB::transaction(function () use ($request, $validated, $photoPath): Issue {
                $issue = Issue::query()->create([
                    'reported_by' => $request->user()->id,
                    'photo_path' => $photoPath,
                    'latitude' => $validated['latitude'],
                    'longitude' => $validated['longitude'],
                    'address' => null,
                    'address_text' => $validated['address_text'] ?? null,
                    'description' => $validated['description'] ?? null,
                    'severity' => $validated['severity'],
                    'status' => 'reported',
                    'reported_at' => now(),
                ]);

                $issue->statusHistory()->create([
                    'changed_by' => $request->user()->id,
                    'old_status' => null,
                    'new_status' => 'reported',
                    'changed_at' => now(),
                ]);

                return $issue;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($photoPath);

            throw $exception;
        }

        return response()->json([
            'issue' => IssueResource::make($issue->load($this->issueRelations()))->resolve($request),
        ], 201);
    }

    public function assign(AssignIssueRequest $request, Issue $issue): JsonResponse
    {
        $contractorId = $request->validated('contractor_id');
        $assignedBy = $request->user()->id;
        $expectedStatus = $issue->status;
        $expectedAssignmentId = $issue->activeAssignment()->value('id');

        $assignedIssue = DB::transaction(function () use ($assignedBy, $contractorId, $expectedAssignmentId, $expectedStatus, $issue): Issue {
            $lockedIssue = Issue::query()->lockForUpdate()->findOrFail($issue->id);
            Gate::authorize('assign', $lockedIssue);

            $activeAssignment = IssueAssignment::query()
                ->where('issue_id', $lockedIssue->id)
                ->whereNull('unassigned_at')
                ->orderByDesc('assigned_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (
                $lockedIssue->status !== $expectedStatus
                || $activeAssignment?->id !== ($expectedAssignmentId === null ? null : (int) $expectedAssignmentId)
            ) {
                abort(409, 'The issue assignment changed. Reload the issue and try again.');
            }

            $contractor = User::query()
                ->whereKey($contractorId)
                ->where('is_active', true)
                ->whereHas('role', fn ($query) => $query->where('name', 'contractor'))
                ->firstOrFail();

            if ($activeAssignment) {
                $activeAssignment->update(['unassigned_at' => now()]);
            }

            $lockedIssue->assignments()->create([
                'contractor_id' => $contractor->id,
                'assigned_by' => $assignedBy,
                'assigned_at' => now(),
            ]);

            $lockedIssue->status = 'assigned';
            $lockedIssue->save();

            return $lockedIssue->load($this->issueRelations());
        });

        return response()->json([
            'issue' => IssueResource::make($assignedIssue)->resolve($request),
        ]);
    }

    public function updateContractorStatus(UpdateIssueStatusRequest $request, Issue $issue): JsonResponse
    {
        $status = $request->validated('status');

        $issue = DB::transaction(function () use ($issue, $status): Issue {
            $lockedIssue = Issue::query()->lockForUpdate()->findOrFail($issue->id);
            Gate::authorize('updateStatus', $lockedIssue);

            if ($status === 'reported' && $lockedIssue->status !== 'reported' && $lockedIssue->assignments()->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'An assigned issue cannot be returned to reported status.',
                ]);
            }

            $lockedIssue->status = $status;
            $lockedIssue->save();

            return $lockedIssue;
        });

        return response()->json([
            'issue' => IssueResource::make($issue->load($this->issueRelations()))->resolve($request),
        ]);
    }

    public function show(Request $request, Issue $issue): JsonResponse
    {
        Gate::authorize('view', $issue);

        return response()->json([
            'issue' => IssueResource::make($issue->load($this->issueRelations(withHistory: true)))->resolve($request),
        ]);
    }

    public function update(UpdateIssueRequest $request, Issue $issue): JsonResponse
    {
        $validated = $request->validated();
        $oldStatus = $issue->status;
        $oldPhotoPath = $issue->photo_path;
        $newPhotoPath = null;

        if (isset($validated['photo'])) {
            $newPhotoPath = $validated['photo']->store('issues', 'local');

            if (! $newPhotoPath) {
                return response()->json(['message' => 'Unable to store the issue photo.'], 500);
            }

            $validated['photo_path'] = $newPhotoPath;
            unset($validated['photo']);
        }

        try {
            DB::transaction(function () use ($request, $issue, $validated): void {
                $lockedIssue = Issue::query()->lockForUpdate()->findOrFail($issue->id);
                Gate::authorize('update', $lockedIssue);
                $oldStatus = $lockedIssue->status;
                $lockedIssue->fill($validated);

                if (
                    $lockedIssue->isDirty('status')
                    && $lockedIssue->status === 'reported'
                    && $lockedIssue->assignments()->exists()
                ) {
                    throw ValidationException::withMessages([
                        'status' => 'An assigned issue cannot be returned to reported status.',
                    ]);
                }

                if ($lockedIssue->isDirty('status')) {
                    $lockedIssue->statusHistory()->create([
                        'changed_by' => $request->user()->id,
                        'old_status' => $oldStatus,
                        'new_status' => $lockedIssue->status,
                        'changed_at' => now(),
                    ]);
                }

                $lockedIssue->save();
            });
        } catch (Throwable $exception) {
            if ($newPhotoPath) {
                Storage::disk('local')->delete($newPhotoPath);
            }

            throw $exception;
        }

        $issue->refresh();

        if ($newPhotoPath && $oldPhotoPath !== $newPhotoPath) {
            Storage::disk('local')->delete($oldPhotoPath);
        }

        return response()->json([
            'issue' => IssueResource::make($issue->load($this->issueRelations(withHistory: true)))->resolve($request),
        ]);
    }

    public function photo(Issue $issue): StreamedResponse
    {
        Gate::authorize('view', $issue);

        $disk = Storage::disk('local');

        if (! $disk instanceof FilesystemAdapter || ! $disk->exists($issue->photo_path)) {
            abort(404);
        }

        return $disk->response($issue->photo_path, basename($issue->photo_path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return list<string> */
    private function issueRelations(bool $withHistory = false): array
    {
        $relations = ['reporter', 'activeAssignment.contractor'];

        if ($withHistory) {
            $relations[] = 'statusHistory';
        }

        return $relations;
    }
}
