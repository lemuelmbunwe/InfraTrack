<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return response()->json([
        'message' => 'Laravel API is working',
    ]);
});

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:6,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/users', [UserController::class, 'store']);

        Route::get('/issues/{issue}/photo', [IssueController::class, 'photo'])
            ->name('api.v1.issues.photo');
        Route::apiResource('issues', IssueController::class)
            ->only(['index', 'store', 'show', 'update']);
    });
});
