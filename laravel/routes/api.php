<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourtController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\QueueController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — TaraPickle
|--------------------------------------------------------------------------
|
| Public:      POST /api/auth/register, POST /api/auth/login
| Protected:   auth:sanctum — logout, me, profile, courts, groups, queue
| Admin:       auth:sanctum + can:manage-court — court management actions
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

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show'])->name('profile.show');
        Route::patch('/', [ProfileController::class, 'update'])->name('profile.update');
    });

    // Courts with live state (waiting count + current match) and the player's
    // squads for the join-queue modal.
    Route::get('courts', [CourtController::class, 'index'])->name('courts.index');
    Route::get('groups', [GroupController::class, 'index'])->name('groups.index');

    // Player-facing queue actions: view the line, join, leave.
    Route::get('courts/{court}/queue', [QueueController::class, 'index'])->name('queue.index');
    Route::post('courts/{court}/queue', [QueueController::class, 'store'])->name('queue.join');
    Route::delete('queue/{queueEntry}', [QueueController::class, 'destroy'])->name('queue.leave');
});

/*
|--------------------------------------------------------------------------
| Queue Engine — organizer / court-manager actions (Prompt 2.1 + 4.1)
|--------------------------------------------------------------------------
|
| Calling players, confirming calls, completing matches, skipping and the
| manual management endpoints are admin-grade and gated by can:manage-court
| (users.is_admin) — see AppServiceProvider::boot.
|
*/
Route::middleware(['auth:sanctum', 'can:manage-court'])->group(function () {
    Route::post('courts/{court}/next-up', [QueueController::class, 'callNext'])->name('queue.call-next');
    Route::post('courts/{court}/confirm-call', [QueueController::class, 'confirmCall'])->name('queue.confirm-call');
    Route::post('queue/{queueEntry}/skip', [QueueController::class, 'skip'])->name('queue.skip');
    Route::post('matches/{game}/complete', [MatchController::class, 'complete'])->name('matches.complete');

    Route::prefix('admin')->group(function () {
        Route::get('users/search', [AdminController::class, 'searchUsers'])->name('admin.users.search');
        Route::post('courts/{court}/queue/add', [AdminController::class, 'addToQueue'])->name('admin.queue.add');
        Route::patch('courts/{court}/queue/reorder', [AdminController::class, 'reorderQueue'])->name('admin.queue.reorder');
        Route::get('analytics', [AnalyticsController::class, 'index'])->name('admin.analytics');
    });
});
