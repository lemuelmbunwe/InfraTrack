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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class IssueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Issue::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['reported', 'assigned', 'in_progress', 'resolved'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $role = $request->user()->role?->name;
        $userId = $request->user()->id;

        $visibleIssues = Issue::query()
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
            );

        $summary = (clone $visibleIssues)
            ->selectRaw(
                'COUNT(*) AS total, '
                .'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS reported, '
                .'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS assigned, '
                .'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS in_progress, '
                .'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS resolved, '
                .'COALESCE(SUM(CASE WHEN severity = ? THEN 1 ELSE 0 END), 0) AS high_severity',
                ['reported', 'assigned', 'in_progress', 'resolved', 'high'],
            )
            ->first();

        $issuesQuery = (clone $visibleIssues)
            ->with($this->issueRelations())
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']));

        if ($role === 'admin') {
            $issuesQuery
                ->orderByRaw("CASE status WHEN 'reported' THEN 0 WHEN 'assigned' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'resolved' THEN 3 ELSE 4 END")
                ->orderByDesc('reported_at')
                ->orderByDesc('id');
        } else {
            $issuesQuery->orderByDesc('reported_at')->orderByDesc('id');
        }

        $issues = $issuesQuery->paginate($filters['per_page'] ?? 15);

        return response()->json([
            'issues' => IssueResource::collection($issues->items())->resolve($request),
            'pagination' => [
                'current_page' => $issues->currentPage(),
                'per_page' => $issues->perPage(),
                'total' => $issues->total(),
                'last_page' => $issues->lastPage(),
            ],
            'summary' => [
                'total' => (int) $summary->total,
                'reported' => (int) $summary->reported,
                'assigned' => (int) $summary->assigned,
                'in_progress' => (int) $summary->in_progress,
                'resolved' => (int) $summary->resolved,
                'high_severity' => (int) $summary->high_severity,
            ],
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
        $expectedAllocationId = $issue->activeAssignment()->value('id');
        $expectedAssignmentId = $issue->activeAssignment()->value('assignment_id');

        $assignedIssue = DB::transaction(function () use ($assignedBy, $contractorId, $expectedAllocationId, $expectedAssignmentId, $expectedStatus, $issue): Issue {
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
                || $activeAssignment?->id !== ($expectedAllocationId === null ? null : (int) $expectedAllocationId)
                || $activeAssignment?->assignment_id !== ($expectedAssignmentId === null ? null : (int) $expectedAssignmentId)
            ) {
                abort(409, 'The issue assignment changed. Reload the issue and try again.');
            }

            if ($activeAssignment === null || $activeAssignment->assignment_id === null) {
                abort(409, 'The issue is not part of a maintenance assignment.');
            }

            $contractor = User::query()
                ->whereKey($contractorId)
                ->where('is_active', true)
                ->whereHas('role', fn ($query) => $query->where('name', 'contractor'))
                ->firstOrFail();

            $activeAssignment->update(['unassigned_at' => now()]);

            $lockedIssue->assignments()->create([
                'assignment_id' => $activeAssignment->assignment_id,
                'contractor_id' => $contractor->id,
                'assigned_by' => $assignedBy,
                'assigned_at' => now(),
            ]);

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
