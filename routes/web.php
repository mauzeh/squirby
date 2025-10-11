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
use App\Http\Controllers\LiftLogController;
use App\Http\Controllers\BodyLogController;
use App\Http\Controllers\MeasurementTypeController;
use App\Http\Controllers\ProgramController;

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
    Route::get('body-logs/type/{measurementType}', [BodyLogController::class, 'showByType'])->name('body-logs.show-by-type');

    Route::resource('measurement-types', MeasurementTypeController::class)->except(['show']);

    Route::resource('exercises', ExerciseController::class);
    Route::get('exercises/{exercise}/logs', [ExerciseController::class, 'showLogs'])->name('exercises.show-logs');

    Route::resource('lift-logs', LiftLogController::class)->except(['show']);

    Route::resource('programs', ProgramController::class);
    Route::post('programs/destroy-selected', [ProgramController::class, 'destroySelected'])->name('programs.destroy-selected');

    /*
    |--------------------------------------------------------------------------
    | TSV Import Routes - Development/Testing Only
    |--------------------------------------------------------------------------
    |
    | These routes handle TSV (Tab-Separated Values) data imports for various
    | data types. They are restricted to non-production environments for
    | security reasons to prevent unauthorized data manipulation in live systems.
    |
    | Protection layers:
    | 1. Environment check: Routes only registered in non-production
    | 2. Middleware: Additional protection via 'no.tsv.in.production' middleware
    | 3. UI hiding: Import forms hidden in production via Blade conditionals
    |
    */
    if (!app()->environment('production')) {
        Route::middleware('no.tsv.in.production')->group(function () {
            Route::post('food-logs/import-tsv', [FoodLogController::class, 'importTsv'])->name('food-logs.import-tsv');
            Route::post('ingredients/import-tsv', [IngredientController::class, 'importTsv'])->name('ingredients.import-tsv');
            Route::post('body-logs/import-tsv', [BodyLogController::class, 'importTsv'])->name('body-logs.import-tsv');
            Route::post('exercises/import-tsv', [ExerciseController::class, 'importTsv'])->name('exercises.import-tsv');
            Route::post('lift-logs/import-tsv', [LiftLogController::class, 'importTsv'])->name('lift-logs.import-tsv');
            Route::post('programs/import', [ProgramController::class, 'import'])->name('programs.import');
        });
    }

    Route::post('lift-logs/destroy-selected', [LiftLogController::class, 'destroySelected'])->name('lift-logs.destroy-selected');
    Route::get('lift-logs/mobile-entry', [LiftLogController::class, 'mobileEntry'])->name('lift-logs.mobile-entry');
});

require __DIR__.'/auth.php';

Route::middleware(['auth', 'admin'])->group(function () {
    Route::resource('users', UserController::class);
    Route::get('users/{user}/impersonate', [UserController::class, 'impersonate'])->name('users.impersonate');
});

Route::get('users/impersonate/leave', [UserController::class, 'leaveImpersonate'])->name('users.leave-impersonate');    Route::get('lift-logs/quick-add/{exercise}/{date}', [LiftLogController::class, 'quickAdd'])->name('lift-logs.quick-add');
    Route::get('programs/quick-add/{exercise}/{date}', [ProgramController::class, 'quickAdd'])->name('programs.quick-add');
    Route::post('programs/quick-create/{date}', [ProgramController::class, 'quickCreate'])->name('programs.quick-create');
    Route::get('programs/{program}/move-down', [ProgramController::class, 'moveDown'])->name('programs.move-down');
    Route::get('programs/{program}/move-up', [ProgramController::class, 'moveUp'])->name('programs.move-up');
