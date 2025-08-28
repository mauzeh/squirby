<?php

use App\Http\Controllers\DailyLogController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\MealController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\WorkoutController;
use App\Http\Controllers\MeasurementController;
use Illuminate\Support\Facades\Route;

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

Route::resource('measurements', MeasurementController::class);
Route::post('measurements/destroy-selected', [MeasurementController::class, 'destroySelected'])->name('measurements.destroy-selected');
Route::resource('exercises', ExerciseController::class);
Route::get('exercises/{exercise}/logs', [ExerciseController::class, 'showLogs'])->name('exercises.show-logs');

Route::resource('workouts', WorkoutController::class);

Route::post('workouts/import-tsv', [WorkoutController::class, 'importTsv'])->name('workouts.import-tsv');

Route::post('workouts/destroy-selected', [WorkoutController::class, 'destroySelected'])->name('workouts.destroy-selected');

Route::get('/', function () {
    return redirect()->route('daily-logs.index');
});
