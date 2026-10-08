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
        return $user->is_active && in_array($user->role?->name, ['admin', 'inspector'], true);
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

        if ($issue->status === 'assigned') {
            return Response::deny('Assigned issues can no longer be edited by Inspectors.');
        }

        return Response::allow();
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
