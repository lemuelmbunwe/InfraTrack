<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAssignmentRequest;
use App\Http\Requests\UpdateAssignmentRequest;
use App\Http\Resources\AssignmentResource;
use App\Models\Assignment;
use App\Models\Issue;
use App\Models\IssueAssignment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Assignment::class);

        $user = $request->user();

        $assignments = Assignment::query()
            ->when(
                $user->role?->name === 'contractor',
                fn ($query) => $query->whereHas('issueAllocations', fn ($allocations) => $allocations
                    ->active()
                    ->where('contractor_id', $user->id)),
            )
            ->with($this->assignmentRelations())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'assignments' => AssignmentResource::collection($assignments)->resolve($request),
        ]);
    }

    public function show(Request $request, Assignment $assignment): JsonResponse
    {
        Gate::authorize('view', $assignment);

        $assignment->load($this->assignmentRelations());

        return response()->json([
            'assignment' => AssignmentResource::make($assignment)->resolve($request),
        ]);
    }

    public function update(UpdateAssignmentRequest $request, Assignment $assignment): JsonResponse
    {
        $validated = $request->validated();

        $assignment = DB::transaction(function () use ($assignment, $validated): Assignment {
            $lockedAssignment = Assignment::query()
                ->lockForUpdate()
                ->findOrFail($assignment->id);

            Gate::authorize('update', $lockedAssignment);
            $lockedAssignment->fill($validated);
            $lockedAssignment->save();

            return $lockedAssignment;
        });

        $assignment->load($this->assignmentRelations());

        return response()->json([
            'assignment' => AssignmentResource::make($assignment)->resolve($request),
        ]);
    }

    public function store(StoreAssignmentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $issueIds = $validated['issue_ids'];
        $assignedBy = $request->user()->id;

        $assignment = DB::transaction(function () use ($assignedBy, $issueIds, $validated): Assignment {
            $contractor = User::query()
                ->whereKey($validated['contractor_id'])
                ->where('is_active', true)
                ->whereHas('role', fn ($query) => $query->where('name', 'contractor'))
                ->lockForUpdate()
                ->first();

            if ($contractor === null) {
                throw ValidationException::withMessages([
                    'contractor_id' => 'Select an active Contractor account.',
                ]);
            }

            $issues = Issue::query()
                ->whereIn('id', $issueIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($issues->count() !== count($issueIds)) {
                abort(409, StoreAssignmentRequest::ISSUE_NO_LONGER_AVAILABLE_MESSAGE);
            }

            $alreadyAllocatedIds = IssueAssignment::query()
                ->whereIn('issue_id', $issueIds)
                ->whereNull('unassigned_at')
                ->pluck('issue_id')
                ->map(fn ($issueId): int => (int) $issueId)
                ->all();

            foreach ($issues as $issue) {
                if (in_array((int) $issue->id, $alreadyAllocatedIds, true)) {
                    abort(409, StoreAssignmentRequest::ISSUE_ALREADY_ASSIGNED_MESSAGE);
                }

                if ($issue->status !== 'reported') {
                    abort(409, StoreAssignmentRequest::ISSUE_NO_LONGER_REPORTED_MESSAGE);
                }
            }

            $assignment = Assignment::query()->create([
                'title' => $validated['title'],
                'instructions' => $validated['instructions'] ?? null,
                'assigned_by' => $assignedBy,
                'initial_contractor_id' => $contractor->id,
            ]);

            $assignedAt = now();

            foreach ($issues as $issue) {
                $issue->assignments()->create([
                    'assignment_id' => $assignment->id,
                    'contractor_id' => $contractor->id,
                    'assigned_by' => $assignedBy,
                    'assigned_at' => $assignedAt,
                ]);

                $issue->status = 'assigned';
                $issue->save();
            }

            return $assignment;
        });

        $assignment->load([
            'initialContractor:id,name',
            'contractors:id,name',
            'issues.activeAssignment.contractor:id,name',
        ]);

        return response()->json([
            'assignment' => AssignmentResource::make($assignment)->resolve($request),
        ], 201);
    }

    /** @return list<string> */
    private function assignmentRelations(): array
    {
        return [
            'initialContractor:id,name',
            'contractors:id,name',
            'issues.activeAssignment.contractor:id,name',
        ];
    }
}
