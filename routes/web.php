<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

// Custom Controllers
use App\Http\Controllers\FoodLogController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\MealController;

use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\LiftLogController;
use App\Http\Controllers\BodyLogController;
use App\Http\Controllers\MeasurementTypeController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\RecommendationController; // Keep this import

// Breeze Routes
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('food-logs.index');
    }
    return redirect()->route('login');
});



Route::middleware('auth')->group(function () {
    // Breeze Profile Routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Custom Application Routes (Protected by 'auth' middleware)
    Route::resource('food-logs', FoodLogController::class)->except(['show']);
    Route::get('food-logs/mobile-entry', [FoodLogController::class, 'mobileEntry'])->name('food-logs.mobile-entry');
    Route::post('food-logs/add-meal', [FoodLogController::class, 'addMealToLog'])->name('food-logs.add-meal');
    Route::post('food-logs/destroy-selected', [FoodLogController::class, 'destroySelected'])->name('food-logs.destroy-selected');
    Route::post('food-logs/export', [FoodLogController::class, 'export'])->name('food-logs.export');
    Route::post('food-logs/export-all', [FoodLogController::class, 'exportAll'])->name('food-logs.export-all');

    

    Route::resource('ingredients', IngredientController::class)->except([
        'show'
    ]);

    Route::resource('meals', MealController::class)->except([
        'show'
    ]);

    Route::post('meals/create-from-logs', [MealController::class, 'createFromLogs'])->name('meals.create-from-logs');

    Route::resource('body-logs', BodyLogController::class)->except(['show']);
    Route::post('body-logs/destroy-selected', [BodyLogController::class, 'destroySelected'])->name('body-logs.destroy-selected');
    Route::get('body-logs/type/{measurementType}', [BodyLogController::class, 'showByType'])->name('body-logs.show-by-type');

    Route::resource('measurement-types', MeasurementTypeController::class)->except(['show']);

    Route::resource('exercises', ExerciseController::class);
    Route::post('exercises/destroy-selected', [ExerciseController::class, 'destroySelected'])->name('exercises.destroy-selected');
    Route::post('exercises/{exercise}/promote', [ExerciseController::class, 'promote'])->name('exercises.promote');
    Route::post('exercises/{exercise}/unpromote', [ExerciseController::class, 'unpromote'])->name('exercises.unpromote');
    Route::get('exercises/{exercise}/logs', [ExerciseController::class, 'showLogs'])->name('exercises.show-logs');

    Route::resource('lift-logs', LiftLogController::class)->except(['show']);

    Route::resource('programs', ProgramController::class);
    Route::post('programs/destroy-selected', [ProgramController::class, 'destroySelected'])->name('programs.destroy-selected');

    // Exercise Recommendations (Keep these routes)
    Route::get('recommendations', [RecommendationController::class, 'index'])->name('recommendations.index');



    Route::post('lift-logs/destroy-selected', [LiftLogController::class, 'destroySelected'])->name('lift-logs.destroy-selected');
    Route::get('lift-logs/mobile-entry', [LiftLogController::class, 'mobileEntry'])->name('lift-logs.mobile-entry');
});

// Google OAuth routes
Route::get('auth/google', [App\Http\Controllers\Auth\SocialiteController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('auth/google/callback', [App\Http\Controllers\Auth\SocialiteController::class, 'handleGoogleCallback'])->name('auth.google.callback');

require __DIR__.'/auth.php';

Route::middleware(['auth', 'admin'])->group(function () {
    Route::resource('users', UserController::class);
    Route::get('users/{user}/impersonate', [UserController::class, 'impersonate'])->name('users.impersonate');
    // Remove all exercise-intelligence routes here
});

Route::get('users/impersonate/leave', [UserController::class, 'leaveImpersonate'])->name('users.leave-impersonate');    Route::get('lift-logs/quick-add/{exercise}/{date}', [LiftLogController::class, 'quickAdd'])->name('lift-logs.quick-add');
    Route::get('programs/quick-add/{exercise}/{date}', [ProgramController::class, 'quickAdd'])->name('programs.quick-add');
    Route::post('programs/quick-create/{date}', [ProgramController::class, 'quickCreate'])->name('programs.quick-create');
    Route::get('programs/{program}/move-down', [ProgramController::class, 'moveDown'])->name('programs.move-down');
    Route::get('programs/{program}/move-up', [ProgramController::class, 'moveUp'])->name('programs.move-up');