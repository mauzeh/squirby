<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

// Custom Controllers
use App\Http\Controllers\FoodLogController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\MealController;
use App\Http\Controllers\MobileEntryController;

use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\LiftLogController;
use App\Http\Controllers\BodyLogController;
use App\Http\Controllers\MeasurementTypeController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\LabsController;
use App\Http\Controllers\WorkoutController;

// Breeze Routes
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('mobile-entry.lifts');
    }
    return redirect()->route('login');
});



Route::middleware('auth')->group(function () {
    // Breeze Profile Routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/preferences', [ProfileController::class, 'updatePreferences'])->name('profile.update-preferences');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Custom Application Routes (Protected by 'auth' middleware)


    Route::post('food-logs/add-meal', [FoodLogController::class, 'addMealToLog'])->name('food-logs.add-meal');
    Route::post('food-logs/destroy-selected', [FoodLogController::class, 'destroySelected'])->name('food-logs.destroy-selected');

    Route::get('food-logs/{food_log}/edit', [FoodLogController::class, 'edit'])->name('food-logs.edit');
    Route::post('food-logs', [FoodLogController::class, 'store'])->name('food-logs.store');
    Route::put('food-logs/{food_log}', [FoodLogController::class, 'update'])->name('food-logs.update');
    Route::delete('food-logs/{food_log}', [FoodLogController::class, 'destroy'])->name('food-logs.destroy');


    

    Route::resource('ingredients', IngredientController::class)->except([
        'show'
    ]);

    Route::resource('meals', MealController::class)->except([
        'show'
    ]);



    Route::resource('body-logs', BodyLogController::class)->except(['show', 'index']);
    Route::get('body-logs/type/{measurementType}', [BodyLogController::class, 'showByType'])->name('body-logs.show-by-type');
    Route::resource('measurement-types', MeasurementTypeController::class)->except(['show']);

    Route::resource('exercises', ExerciseController::class);
    Route::post('exercises/{exercise}/promote', [ExerciseController::class, 'promote'])->name('exercises.promote');
    Route::post('exercises/{exercise}/unpromote', [ExerciseController::class, 'unpromote'])->name('exercises.unpromote');
    Route::get('exercises/{exercise}/logs', [ExerciseController::class, 'showLogs'])->name('exercises.show-logs');
    Route::get('exercises/{exercise}/merge', [ExerciseController::class, 'showMerge'])->name('exercises.show-merge');
    Route::post('exercises/{exercise}/merge', [ExerciseController::class, 'merge'])->name('exercises.merge');

    Route::resource('lift-logs', LiftLogController::class)->except(['show']);

    // Exercise Matching Aliases
    Route::get('exercise-aliases/create', [App\Http\Controllers\ExerciseMatchingAliasController::class, 'create'])->name('exercise-aliases.create');
    Route::get('exercise-aliases/store', [App\Http\Controllers\ExerciseMatchingAliasController::class, 'store'])->name('exercise-aliases.store');
    Route::post('exercise-aliases/create-and-link', [App\Http\Controllers\ExerciseMatchingAliasController::class, 'createAndLink'])->name('exercise-aliases.create-and-link');

    // Exercise Recommendations
    Route::get('recommendations', [RecommendationController::class, 'index'])->name('recommendations.index');

    // Workouts - Simple Mode
    Route::get('workouts/create-simple', [App\Http\Controllers\SimpleWorkoutController::class, 'create'])
        ->name('workouts.create-simple');
    Route::post('workouts/store-simple', [App\Http\Controllers\SimpleWorkoutController::class, 'store'])
        ->name('workouts.store-simple');
    Route::get('workouts/{workout}/edit-simple', [App\Http\Controllers\SimpleWorkoutController::class, 'edit'])
        ->name('workouts.edit-simple');
    Route::put('workouts/{workout}/update-simple', [App\Http\Controllers\SimpleWorkoutController::class, 'update'])
        ->name('workouts.update-simple');
    
    // Simple workout exercise management
    Route::get('workouts/new/add-exercise', [App\Http\Controllers\SimpleWorkoutController::class, 'addExercise'])
        ->name('simple-workouts.add-exercise-new');
    Route::post('workouts/new/create-exercise', [App\Http\Controllers\SimpleWorkoutController::class, 'createExercise'])
        ->name('simple-workouts.create-exercise-new');
    Route::get('workouts/{workout}/add-exercise', [App\Http\Controllers\SimpleWorkoutController::class, 'addExercise'])
        ->name('simple-workouts.add-exercise');
    Route::post('workouts/{workout}/create-exercise', [App\Http\Controllers\SimpleWorkoutController::class, 'createExercise'])
        ->name('simple-workouts.create-exercise');
    Route::get('workouts/{workout}/exercises/{exercise}/move', [App\Http\Controllers\SimpleWorkoutController::class, 'moveExercise'])
        ->name('simple-workouts.move-exercise');
    Route::delete('workouts/{workout}/exercises/{exercise}', [App\Http\Controllers\SimpleWorkoutController::class, 'removeExercise'])
        ->name('simple-workouts.remove-exercise');
    
    // Workouts - Advanced Mode (WOD Syntax)
    Route::resource('workouts', WorkoutController::class)->except(['show']);

    // Mobile Entry - Supports date parameter
    Route::get('mobile-entry/lifts', [MobileEntryController::class, 'lifts'])->name('mobile-entry.lifts');
    Route::get('mobile-entry/foods', [MobileEntryController::class, 'foods'])->name('mobile-entry.foods');
    Route::get('mobile-entry/measurements', [MobileEntryController::class, 'measurements'])->name('mobile-entry.measurements');
    
    // Mobile Entry Helper Routes
    Route::post('mobile-entry/create-exercise', [MobileEntryController::class, 'createExercise'])->name('mobile-entry.create-exercise');
    
    // Food Entry Helper Routes
    Route::post('mobile-entry/create-ingredient', [MobileEntryController::class, 'createIngredient'])->name('mobile-entry.create-ingredient');
    Route::get('mobile-entry/add-food-form/{type}/{id}', [MobileEntryController::class, 'addFoodForm'])->name('mobile-entry.add-food-form');
    Route::delete('mobile-entry/remove-food-form/{id}', [MobileEntryController::class, 'removeFoodForm'])->name('mobile-entry.remove-food-form');

    // Labs - Component-Based Architecture Examples
    Route::get('labs/with-nav', [LabsController::class, 'withDateNavigation'])->name('labs.with-nav');
    Route::get('labs/without-nav', [LabsController::class, 'withoutNavigation'])->name('labs.without-nav');
    Route::get('labs/multiple-forms', [LabsController::class, 'multipleForms'])->name('labs.multiple-forms');
    Route::get('labs/custom-order', [LabsController::class, 'customOrder'])->name('labs.custom-order');
    Route::get('labs/multiple-buttons', [LabsController::class, 'multipleButtons'])->name('labs.multiple-buttons');
    Route::get('labs/table-example', [LabsController::class, 'tableExample'])->name('labs.table-example');
    Route::get('labs/table-reorder', [LabsController::class, 'tableWithReorder'])->name('labs.table-reorder');
    Route::get('labs/multiple-lists', [LabsController::class, 'multipleItemLists'])->name('labs.multiple-lists');
    Route::get('labs/title-back-button', [LabsController::class, 'titleWithBackButton'])->name('labs.title-back-button');
    Route::get('labs/table-initial-expanded', [LabsController::class, 'tableInitialExpanded'])->name('labs.table-initial-expanded');
    Route::get('labs/expanded-list', [LabsController::class, 'expandedList'])->name('labs.expanded-list');
    Route::match(['get', 'post'], 'labs/table-bulk-selection', [LabsController::class, 'tableBulkSelection'])->name('labs.table-bulk-selection');
    Route::match(['get', 'post'], 'labs/ingredient-entry', [LabsController::class, 'ingredientEntry'])->name('labs.ingredient-entry');
    Route::get('labs/chart-example', [LabsController::class, 'chartExample'])->name('labs.chart-example');



});

// Google OAuth routes
Route::get('auth/google', [App\Http\Controllers\Auth\SocialiteController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('auth/google/callback', [App\Http\Controllers\Auth\SocialiteController::class, 'handleGoogleCallback'])->name('auth.google.callback');

use App\Http\Controllers\DebugController;

Route::get('/debug/email', [DebugController::class, 'previewFirstLiftEmail']);

require __DIR__.'/auth.php';

Route::middleware(['auth', 'admin'])->group(function () {
    Route::resource('users', UserController::class);
    Route::get('users/{user}/impersonate', [UserController::class, 'impersonate'])->name('users.impersonate');
});

Route::get('users/impersonate/leave', [UserController::class, 'leaveImpersonate'])->name('users.leave-impersonate');
Route::get('lift-logs/quick-add/{exercise}/{date}', [LiftLogController::class, 'quickAdd'])->name('lift-logs.quick-add');
Route::get('/magic-login/{token}', [App\Http\Controllers\MagicLoginController::class, 'login']);
