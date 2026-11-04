<?php

namespace App\Policies;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class IssuePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_active && in_array($user->role?->name, ['admin', 'inspector', 'contractor'], true);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Issue $issue): Response
    {
        if (! $user->is_active) {
            return Response::denyAsNotFound();
        }

        if ($user->role?->name === 'admin') {
            return Response::allow();
        }

        if ($user->role?->name === 'inspector' && $issue->reported_by === $user->id) {
            return Response::allow();
        }

        // INCORPORATED: Check if contractor is assigned using relationship query
        if ($user->role?->name === 'contractor') {
            $isAssigned = $issue->activeAssignment()
                ->where('contractor_id', $user->id)
                ->exists();

            if ($isAssigned) {
                return Response::allow();
            }
        }

        return Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->is_active && $user->role?->name === 'inspector';
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Issue $issue): Response
    {
        if (! $user->is_active) {
            return Response::deny();
        }

        if ($user->role?->name === 'admin') {
            return Response::allow();
        }

        if ($user->role?->name !== 'inspector' || $issue->reported_by !== $user->id) {
            return Response::denyAsNotFound();
        }

        // INCORPORATED: Prevent updates if an active assignment exists in the database
        if ($issue->status !== 'reported' || $issue->activeAssignment()->exists()) {
            return Response::deny('Assigned issues can no longer be edited by Inspectors.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can assign a contractor to the issue.
     */
    public function assign(User $user, Issue $issue): bool
    {
        if (! $user->is_active || $user->role?->name !== 'admin') {
            return false;
        }

        return $issue->status === 'assigned'
            && $issue->activeAssignment()->whereNotNull('assignment_id')->exists();
    }

    /**
     * Determine whether the user can update the status of the issue.
     */
    public function updateStatus(User $user, Issue $issue): bool
    {
        // INCORPORATED: Verify contractor owns the current active assignment
        return $user->is_active
            && $user->role?->name === 'contractor'
            && $issue->activeAssignment()->where('contractor_id', $user->id)->exists();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Issue $issue): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Issue $issue): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Issue $issue): bool
    {
        return false;
    }
}
