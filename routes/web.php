<?php

use App\Http\Controllers\DailyLogController;
use App\Http\Controllers\IngredientController;
use Illuminate\Support\Facades\Route;

Route::resource('daily-logs', DailyLogController::class)->except(['show']);

Route::resource('ingredients', IngredientController::class)->except([
    'show'
]);

Route::resource('meals', MealController::class)->except([
    'show'
]);

Route::get('/', function () {
    return redirect()->route('daily-logs.index');
});
