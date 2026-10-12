<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function contractors(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user?->is_active && $user->role?->name === 'admin', 403);

        $contractors = User::query()
            ->with('role')
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('name', 'contractor'))
            ->orderBy('name')
            ->get();

        return response()->json([
            'contractors' => UserResource::collection($contractors)->resolve($request),
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $role = Role::query()->where('name', $validated['role'])->firstOrFail();

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role_id' => $role->id,
            'super' => (bool) ($validated['super'] ?? false),
            'is_active' => true,
        ]);

        return response()->json([
            'user' => UserResource::make($user->load('role'))->resolve($request),
        ], 201);
    }
}
