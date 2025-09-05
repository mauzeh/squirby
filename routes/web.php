<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

// Custom Controllers
use App\Http\Controllers\DailyLogController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\MealController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\WorkoutController;
use App\Http\Controllers\MeasurementLogController;
use App\Http\Controllers\MeasurementTypeController;

// Breeze Routes
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('daily-logs.index');
    }
    return redirect()->route('login');
});



Route::middleware('auth')->group(function () {
    // Breeze Profile Routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Custom Application Routes (Protected by 'auth' middleware)
    Route::resource('daily-logs', DailyLogController::class)->except(['show']);
    Route::post('daily-logs/add-meal', [DailyLogController::class, 'addMealToLog'])->name('daily-logs.add-meal');
    Route::post('daily-logs/destroy-selected', [DailyLogController::class, 'destroySelected'])->name('daily-logs.destroy-selected');
    Route::post('daily-logs/import-tsv', [DailyLogController::class, 'importTsv'])->name('daily-logs.import-tsv');

    Route::get('export', [ExportController::class, 'showExportForm'])->name('export-form');
    Route::post('export', [ExportController::class, 'export'])->name('export');
    Route::post('export-all', [ExportController::class, 'exportAll'])->name('export-all');

    Route::resource('ingredients', IngredientController::class)->except([
        'show'
    ]);

    Route::resource('meals', MealController::class)->except([
        'show'
    ]);

    Route::post('meals/create-from-logs', [MealController::class, 'createFromLogs'])->name('meals.create-from-logs');

    Route::resource('measurement-logs', MeasurementLogController::class)->except(['show']);
    Route::post('measurement-logs/destroy-selected', [MeasurementLogController::class, 'destroySelected'])->name('measurement-logs.destroy-selected');
    Route::post('measurement-logs/import-tsv', [MeasurementLogController::class, 'importTsv'])->name('measurement-logs.import-tsv');
    Route::get('measurement-logs/type/{measurementType}', [MeasurementLogController::class, 'showByType'])->name('measurement-logs.show-by-type');

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
});