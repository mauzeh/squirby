<?php

use App\Http\Controllers\DailyLogController;
use App\Http\Controllers\IngredientController;
use Illuminate\Support\Facades\Route;

Route::resource('daily-logs', DailyLogController::class)->except(['show']);
Route::get('/', [DailyLogController::class, 'index'])->name('daily_logs.index');

Route::resource('ingredients', IngredientController::class)->except([
    'show'
]);
