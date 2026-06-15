<?php

use App\Sync\Controllers\AuthController;
use App\Sync\Controllers\LogController;
use App\Sync\Controllers\BlueprintController;
use App\Sync\Controllers\PreferencesController;
use App\Sync\Controllers\RestoreController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'throttle:sync-per-user', 'throttle:sync-global', 'device-id', 'log-sync-request'])->group(function () {
    Route::post('/logs', [LogController::class, 'store']);
    Route::delete('/logs/{liftLog}', [LogController::class, 'destroy']);
    Route::post('/blueprint', [BlueprintController::class, 'store']);
    Route::post('/preferences', [PreferencesController::class, 'store']);
    Route::get('/restore', [RestoreController::class, 'index']);
});
