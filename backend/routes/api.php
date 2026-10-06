<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return response()->json([
        'message' => 'Laravel API is working',
    ]);
});

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:6,1');

    Route::get('/auth/me', [AuthController::class, 'me'])
        ->middleware('auth:sanctum');
});
