<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — TaraPickle
|--------------------------------------------------------------------------
|
| Public:      POST /api/auth/register, POST /api/auth/login
| Protected:   auth:sanctum — logout, me, and the profile endpoints
|
*/

Route::prefix('auth')->group(function () {
    // Rate limits guard against credential brute-forcing and account-creation spam.
    Route::post('register', [AuthController::class, 'register'])
        ->middleware('throttle:5,1')
        ->name('auth.register');
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('me', [AuthController::class, 'me'])->name('auth.me');
    });
});

Route::middleware('auth:sanctum')->prefix('profile')->group(function () {
    Route::get('/', [ProfileController::class, 'show'])->name('profile.show');
    Route::patch('/', [ProfileController::class, 'update'])->name('profile.update');
});
