<?php

use App\Http\Controllers\DailyLogController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\MealController;
use Illuminate\Support\Facades\Route;

Route::resource('daily-logs', DailyLogController::class)->except(['show']);
Route::post('daily-logs/add-meal', [DailyLogController::class, 'addMealToLog'])->name('daily-logs.add-meal');
Route::post('daily-logs/destroy-day', [DailyLogController::class, 'destroyDay'])->name('daily-logs.destroy-day');
Route::get('daily-logs/export', [DailyLogController::class, 'showExportForm'])->name('daily-logs.export-form');
Route::post('daily-logs/export', [DailyLogController::class, 'export'])->name('daily-logs.export');

Route::resource('ingredients', IngredientController::class)->except([
    'show'
]);

Route::resource('meals', MealController::class)->except([
    'show'
]);

Route::post('meals/create-from-logs', [MealController::class, 'createFromLogs'])->name('meals.create-from-logs');

Route::get('/', function () {
    return redirect()->route('daily-logs.index');
});
