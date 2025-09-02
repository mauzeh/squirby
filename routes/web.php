<?php

use App\Http\Controllers\ProfileController;
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
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\PermissionController;

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
    Route::resource('daily-logs', DailyLogController::class)->except(['show'])->middleware([
        'permission:daily-logs.view|daily-logs.create|daily-logs.update|daily-logs.delete'
    ]);
    Route::post('daily-logs/add-meal', [DailyLogController::class, 'addMealToLog'])->name('daily-logs.add-meal')->middleware('permission:daily-logs.create');
    Route::post('daily-logs/destroy-selected', [DailyLogController::class, 'destroySelected'])->name('daily-logs.destroy-selected')->middleware('permission:daily-logs.delete');
    Route::post('daily-logs/import-tsv', [DailyLogController::class, 'importTsv'])->name('daily-logs.import-tsv')->middleware('permission:daily-logs.create');

    Route::get('export', [ExportController::class, 'showExportForm'])->name('export-form');
    Route::post('export', [ExportController::class, 'export'])->name('export');
    Route::post('export-all', [ExportController::class, 'exportAll'])->name('export-all');

    Route::resource('ingredients', IngredientController::class)->except([
        'show'
    ])->middleware([
        'permission:ingredients.view|ingredients.create|ingredients.update|ingredients.delete'
    ]);

    Route::resource('meals', MealController::class)->except([
        'show'
    ])->middleware([
        'permission:meals.view|meals.create|meals.update|meals.delete'
    ]);

    Route::post('meals/create-from-logs', [MealController::class, 'createFromLogs'])->name('meals.create-from-logs')->middleware('permission:meals.create');

    Route::resource('measurement-logs', MeasurementLogController::class)->except(['show'])->middleware([
        'permission:measurement-logs.view|measurement-logs.create|measurement-logs.update|measurement-logs.delete'
    ]);
    Route::post('measurement-logs/destroy-selected', [MeasurementLogController::class, 'destroySelected'])->name('measurement-logs.destroy-selected')->middleware('permission:measurement-logs.delete');
    Route::post('measurement-logs/import-tsv', [MeasurementLogController::class, 'importTsv'])->name('measurement-logs.import-tsv')->middleware('permission:measurement-logs.create');
    Route::get('measurement-logs/type/{measurementType}', [MeasurementLogController::class, 'showByType'])->name('measurement-logs.show-by-type')->middleware('permission:measurement-logs.view');

    Route::resource('measurement-types', MeasurementTypeController::class)->except(['show'])->middleware([
        'permission:measurement-types.view|measurement-types.create|measurement-types.update|measurement-types.delete'
    ]);

    Route::resource('exercises', ExerciseController::class)->middleware([
        'permission:exercises.view|exercises.create|exercises.update|exercises.delete'
    ]);
    Route::get('exercises/{exercise}/logs', [ExerciseController::class, 'showLogs'])->name('exercises.show-logs')->middleware('permission:exercises.view');

    Route::resource('workouts', WorkoutController::class)->middleware([
        'permission:workouts.view|workouts.create|workouts.update|workouts.delete'
    ]);

    Route::post('workouts/import-tsv', [WorkoutController::class, 'importTsv'])->name('workouts.import-tsv')->middleware('permission:workouts.create');

    Route::post('workouts/destroy-selected', [WorkoutController::class, 'destroySelected'])->name('workouts.destroy-selected')->middleware('permission:workouts.delete');
});

Route::middleware(['auth', 'role:admin'])->name('admin.')->prefix('admin')->group(function () {
    Route::resource('users', UserController::class)->middleware([
        'permission:users.view|users.create|users.update|users.delete'
    ]);
    Route::resource('roles', RoleController::class)->middleware([
        'permission:roles.view|roles.create|roles.update|roles.delete'
    ]);
    Route::resource('permissions', PermissionController::class)->middleware([
        'permission:permissions.view|permissions.assign'
    ]);
});

require __DIR__.'/auth.php';

Route::impersonate();