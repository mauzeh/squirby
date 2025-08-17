<?php

use App\Http\Controllers\DailyLogController;
use App\Http\Controllers\IngredientController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DailyLogController::class, 'index'])->name('daily_logs.index');
Route::post('/daily-logs', [DailyLogController::class, 'store'])->name('daily_logs.store');

Route::resource('ingredients', IngredientController::class);
