<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIssueRequest;
use App\Http\Requests\UpdateIssueRequest;
use App\Http\Resources\IssueResource;
use App\Models\Issue;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class IssueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Issue::class);

        $issues = Issue::query()
            ->with('reporter')
            ->when(
                $request->user()->role->name === 'inspector',
                fn ($query) => $query->where('reported_by', $request->user()->id),
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
            'issue' => IssueResource::make($issue->load('reporter'))->resolve($request),
        ], 201);
    }

    public function show(Request $request, Issue $issue): JsonResponse
    {
        Gate::authorize('view', $issue);

        return response()->json([
            'issue' => IssueResource::make($issue->load(['reporter', 'statusHistory']))->resolve($request),
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
            DB::transaction(function () use ($request, $issue, $validated, $oldStatus): void {
                $issue->fill($validated);

                if ($issue->isDirty('status')) {
                    $issue->statusHistory()->create([
                        'changed_by' => $request->user()->id,
                        'old_status' => $oldStatus,
                        'new_status' => $issue->status,
                        'changed_at' => now(),
                    ]);
                }

                $issue->save();
            });
        } catch (Throwable $exception) {
            if ($newPhotoPath) {
                Storage::disk('local')->delete($newPhotoPath);
            }

            throw $exception;
        }

        if ($newPhotoPath && $oldPhotoPath !== $newPhotoPath) {
            Storage::disk('local')->delete($oldPhotoPath);
        }

        return response()->json([
            'issue' => IssueResource::make($issue->load(['reporter', 'statusHistory']))->resolve($request),
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
}
