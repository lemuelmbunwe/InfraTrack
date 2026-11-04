<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && in_array($user->role?->name, ['admin', 'contractor'], true);
    }

    public function view(User $user, Assignment $assignment): Response
    {
        if (! $user->is_active) {
            return Response::denyAsNotFound();
        }

        if ($user->role?->name === 'admin') {
            return Response::allow();
        }

        if (
            $user->role?->name === 'contractor'
            && $assignment->issueAllocations()
                ->active()
                ->where('contractor_id', $user->id)
                ->exists()
        ) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }
}
