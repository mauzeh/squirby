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
use App\Http\Controllers\WorkoutController;
use App\Http\Controllers\BodyLogController;
use App\Http\Controllers\MeasurementTypeController;

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
    Route::post('food-logs/add-meal', [FoodLogController::class, 'addMealToLog'])->name('food-logs.add-meal');
    Route::post('food-logs/destroy-selected', [FoodLogController::class, 'destroySelected'])->name('food-logs.destroy-selected');
    Route::post('food-logs/import-tsv', [FoodLogController::class, 'importTsv'])->name('food-logs.import-tsv');
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
    Route::post('body-logs/import-tsv', [BodyLogController::class, 'importTsv'])->name('body-logs.import-tsv');
    Route::get('body-logs/type/{measurementType}', [BodyLogController::class, 'showByType'])->name('body-logs.show-by-type');

    Route::resource('measurement-types', MeasurementTypeController::class)->except(['show']);

    Route::resource('exercises', ExerciseController::class);
    Route::get('exercises/{exercise}/logs', [ExerciseController::class, 'showLogs'])->name('exercises.show-logs');

    Route::resource('workouts', WorkoutController::class);

    Route::post('workouts/import-tsv', [WorkoutController::class, 'importTsv'])->name('workouts.import-tsv');

    Route::post('workouts/destroy-selected', [WorkoutController::class, 'destroySelected'])->name('workouts.destroy-selected');
});

require __DIR__.'/auth.php';

Route::middleware(['auth', 'admin'])->group(function () {
    Route::resource('users', UserController::class);
    Route::get('users/{user}/impersonate', [UserController::class, 'impersonate'])->name('users.impersonate');
});

Route::get('users/impersonate/leave', [UserController::class, 'leaveImpersonate'])->name('users.leave-impersonate');