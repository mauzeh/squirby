<?php

use App\Http\Controllers\DailyLogController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DailyLogController::class, 'index'])->name('daily_logs.index');
Route::post('/daily-logs', [DailyLogController::class, 'store'])->name('daily_logs.store');
