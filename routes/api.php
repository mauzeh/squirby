<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\ExerciseIntelligenceController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| Exercise Intelligence API Routes
|--------------------------------------------------------------------------
|
| API routes for AJAX functionality related to exercise intelligence
| and recommendations system.
|
*/

Route::middleware(['auth'])->group(function () {
    // Recommendation API routes
    Route::get('recommendations', [RecommendationController::class, 'api'])->name('api.recommendations');
    Route::get('recommendations/filters', [RecommendationController::class, 'getFilters'])->name('api.recommendations.filters');
    
    // Exercise Intelligence lookup routes
    Route::get('exercise-intelligence/{exercise}', [ExerciseIntelligenceController::class, 'show'])->name('api.exercise-intelligence.show');
    Route::get('exercise-intelligence/muscle-data/validate', [ExerciseIntelligenceController::class, 'validateMuscleData'])->name('api.exercise-intelligence.validate-muscle-data');
});

Route::middleware(['auth', 'admin'])->group(function () {
    // Admin-only API routes for intelligence management
    Route::get('exercise-intelligence/muscles/list', [ExerciseIntelligenceController::class, 'getMusclesList'])->name('api.exercise-intelligence.muscles-list');
    Route::get('exercise-intelligence/archetypes/list', [ExerciseIntelligenceController::class, 'getArchetypesList'])->name('api.exercise-intelligence.archetypes-list');
});