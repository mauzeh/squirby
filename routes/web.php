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
        return redirect()->route('food-logs.index');
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
    Route::post('exercises/destroy-selected', [ExerciseController::class, 'destroySelected'])->name('exercises.destroy-selected');
    Route::post('exercises/{exercise}/promote', [ExerciseController::class, 'promote'])->name('exercises.promote');
    Route::post('exercises/{exercise}/unpromote', [ExerciseController::class, 'unpromote'])->name('exercises.unpromote');
    Route::get('exercises/{exercise}/logs', [ExerciseController::class, 'showLogs'])->name('exercises.show-logs');
    Route::get('exercises/{exercise}/merge', [ExerciseController::class, 'showMerge'])->name('exercises.show-merge');
    Route::post('exercises/{exercise}/merge', [ExerciseController::class, 'merge'])->name('exercises.merge');

    Route::resource('lift-logs', LiftLogController::class)->except(['show']);

    // Exercise Recommendations
    Route::get('recommendations', [RecommendationController::class, 'index'])->name('recommendations.index');

    // Workouts
    Route::resource('workouts', WorkoutController::class)->except(['show']);
    Route::get('workouts/{workout}/add-exercise', [WorkoutController::class, 'addExercise'])->name('workouts.add-exercise');
    Route::post('workouts/{workout}/create-exercise', [WorkoutController::class, 'createExercise'])->name('workouts.create-exercise');
    Route::get('workouts/{workout}/exercises/{exercise}/move', [WorkoutController::class, 'moveExercise'])->name('workouts.move-exercise');
    Route::delete('workouts/{workout}/exercises/{exercise}', [WorkoutController::class, 'removeExercise'])->name('workouts.remove-exercise');
    Route::get('workouts-browse', [WorkoutController::class, 'browse'])->name('workouts.browse');
    Route::get('workouts/{workout}/apply', [WorkoutController::class, 'apply'])->name('workouts.apply');

    // Mobile Entry - Supports date parameter
    Route::get('mobile-entry/lifts', [MobileEntryController::class, 'lifts'])->name('mobile-entry.lifts');
    Route::get('mobile-entry/foods', [MobileEntryController::class, 'foods'])->name('mobile-entry.foods');
    Route::get('mobile-entry/measurements', [MobileEntryController::class, 'measurements'])->name('mobile-entry.measurements');
    
    // Mobile Entry Helper Routes
    Route::post('mobile-entry/create-exercise', [MobileEntryController::class, 'createExercise'])->name('mobile-entry.create-exercise');
    Route::get('mobile-entry/add-lift-form/{exercise}', [MobileEntryController::class, 'addLiftForm'])->name('mobile-entry.add-lift-form');
    Route::delete('mobile-entry/remove-form/{id}', [MobileEntryController::class, 'removeForm'])->name('mobile-entry.remove-form');
    
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

    Route::post('lift-logs/destroy-selected', [LiftLogController::class, 'destroySelected'])->name('lift-logs.destroy-selected');

});

// Google OAuth routes
Route::get('auth/google', [App\Http\Controllers\Auth\SocialiteController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('auth/google/callback', [App\Http\Controllers\Auth\SocialiteController::class, 'handleGoogleCallback'])->name('auth.google.callback');

require __DIR__.'/auth.php';

Route::middleware(['auth', 'admin'])->group(function () {
    Route::resource('users', UserController::class);
    Route::get('users/{user}/impersonate', [UserController::class, 'impersonate'])->name('users.impersonate');
});

Route::get('users/impersonate/leave', [UserController::class, 'leaveImpersonate'])->name('users.leave-impersonate');
Route::get('lift-logs/quick-add/{exercise}/{date}', [LiftLogController::class, 'quickAdd'])->name('lift-logs.quick-add');