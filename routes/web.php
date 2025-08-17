<?php

use App\Http\Controllers\DailyLogController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\MealController;
use Illuminate\Support\Facades\Route;

Route::resource('daily-logs', DailyLogController::class)->except(['show']);
Route::post('daily-logs/add-meal', [DailyLogController::class, 'addMealToLog'])->name('daily-logs.add-meal');

Route::resource('ingredients', IngredientController::class)->except([
    'show'
]);

Route::resource('meals', MealController::class)->except([
    'show'
]);

Route::get('/', function () {
    return redirect()->route('daily-logs.index');
});
