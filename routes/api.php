<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\PollApiController;
use App\Http\Controllers\Api\VoteApiController;

// Auth API (Bearer tokens via Sanctum)
Route::post('/register', [AuthApiController::class, 'register']);
Route::post('/login', [AuthApiController::class, 'login']);
Route::post('/logout', [AuthApiController::class, 'logout'])->middleware('auth:sanctum');

// Public poll endpoints
Route::get('/polls', [PollApiController::class, 'index']);
Route::get('/polls/{poll}', [PollApiController::class, 'show']);
Route::get('/polls/{poll}/results', [PollApiController::class, 'results']);

// Protected endpoints (Bearer token required)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/polls', [PollApiController::class, 'store']);
    Route::post('/polls/{poll}/vote', [VoteApiController::class, 'store']);
});

// Admin endpoints (Bearer token + admin privileges required)
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::put('/polls/{poll}', [\App\Http\Controllers\Api\AdminPollController::class, 'update']);
    Route::delete('/polls/{poll}', [\App\Http\Controllers\Api\AdminPollController::class, 'destroy']); // Soft delete
    Route::post('/polls/{poll}/close', [\App\Http\Controllers\Api\AdminPollController::class, 'close']);
    Route::post('/polls/{poll}/extend', [\App\Http\Controllers\Api\AdminPollController::class, 'extend']);
    Route::post('/polls/{poll}/open', [\App\Http\Controllers\Api\AdminPollController::class, 'open']);
    
    // Soft delete management
    Route::get('/polls/trashed', [\App\Http\Controllers\Api\AdminPollController::class, 'trashed']);
    Route::post('/polls/{id}/restore', [\App\Http\Controllers\Api\AdminPollController::class, 'restore']);
    Route::delete('/polls/{id}/force', [\App\Http\Controllers\Api\AdminPollController::class, 'forceDestroy']);
});

