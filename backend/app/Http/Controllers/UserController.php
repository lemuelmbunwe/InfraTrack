<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
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
