<?php

namespace App\Http\View\Composers;

use App\Services\ExerciseAliasService;
use Illuminate\View\View;
use Illuminate\Support\Collection;

class ExerciseAliasComposer
{
    protected ExerciseAliasService $aliasService;

    public function __construct(ExerciseAliasService $aliasService)
    {
        $this->aliasService = $aliasService;
    }

    /**
     * Bind data to the view.
     *
     * @param View $view
     * @return void
     */
    public function compose(View $view): void
    {
        // Only apply aliases if user is authenticated
        if (!auth()->check()) {
            return;
        }

        $viewData = $view->getData();

        // Handle exercises collection
        $exercises = $viewData['exercises'] ?? null;
        if ($exercises instanceof Collection) {
            // Apply aliases to exercises
            $exercisesWithAliases = $this->aliasService->applyAliasesToExercises($exercises, auth()->user());
            
            // Re-sort by display name (title after alias application) while maintaining global/user grouping
            $exercisesWithAliases = $exercisesWithAliases->sortBy([
                ['user_id', 'asc'], // Global exercises (null) first
                ['title', 'asc']    // Then alphabetically by display name
            ])->values(); // Reset keys
            
            $view->with('exercises', $exercisesWithAliases);
        }

        // Handle allExercises collection (used in top-exercises-buttons component)
        $allExercises = $viewData['allExercises'] ?? null;
        if ($allExercises instanceof Collection) {
            $allExercisesWithAliases = $this->aliasService->applyAliasesToExercises($allExercises, auth()->user());
            
            // Re-sort by display name
            $allExercisesWithAliases = $allExercisesWithAliases->sortBy([
                ['user_id', 'asc'],
                ['title', 'asc']
            ])->values();
            
            $view->with('allExercises', $allExercisesWithAliases);
        }

        // Handle displayExercises collection (used in top-exercises-buttons component)
        $displayExercises = $viewData['displayExercises'] ?? null;
        if ($displayExercises instanceof Collection) {
            $displayExercisesWithAliases = $this->aliasService->applyAliasesToExercises($displayExercises, auth()->user());
            $view->with('displayExercises', $displayExercisesWithAliases);
        }

        // Handle single exercise object (for exercise logs view)
        $exercise = $viewData['exercise'] ?? null;
        if ($exercise instanceof \App\Models\Exercise) {
            $displayName = $this->aliasService->getDisplayName($exercise, auth()->user());
            $exercise->title = $displayName;
            $view->with('exercise', $exercise);
        }

        // Handle lift logs collection - apply aliases to their exercises
        $liftLogs = $viewData['liftLogs'] ?? null;
        if ($liftLogs instanceof Collection) {
            $liftLogs->each(function ($liftLog) {
                // Check if this is a model instance (not an array from presenter)
                if (is_object($liftLog) && method_exists($liftLog, 'relationLoaded')) {
                    if ($liftLog->relationLoaded('exercise') && $liftLog->exercise) {
                        $displayName = $this->aliasService->getDisplayName($liftLog->exercise, auth()->user());
                        $liftLog->exercise->title = $displayName;
                    }
                }
            });
        }

        // Handle programs collection - apply aliases to their exercises
        $programs = $viewData['programs'] ?? null;
        if ($programs instanceof Collection) {
            $programs->each(function ($program) {
                if ($program->relationLoaded('exercise') && $program->exercise) {
                    $displayName = $this->aliasService->getDisplayName($program->exercise, auth()->user());
                    $program->exercise->title = $displayName;
                }
            });
        }

        // Handle single program object (for edit view)
        $program = $viewData['program'] ?? null;
        if ($program instanceof \App\Models\Program) {
            if ($program->relationLoaded('exercise') && $program->exercise) {
                $displayName = $this->aliasService->getDisplayName($program->exercise, auth()->user());
                $program->exercise->title = $displayName;
            }
        }

        // Handle mobile entry data structure
        $data = $viewData['data'] ?? null;
        if (is_array($data) && isset($data['itemSelectionList']['items'])) {
            // The items array contains exercise names that need alias application
            // We need to apply aliases to the 'name' field in each item
            // Note: This is already handled by the view composer since exercises are loaded with aliases
            // and the service uses $exercise->title which will already have the alias applied
        }
    }
}
