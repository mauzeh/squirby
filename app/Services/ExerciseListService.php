<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\LiftLog;
use Illuminate\Support\Facades\DB;

/**
 * Service for generating exercise selection lists
 * Used by both LiftLogController and ExerciseAliasController
 */
class ExerciseListService
{
    protected $aliasService;

    public function __construct(ExerciseAliasService $aliasService)
    {
        $this->aliasService = $aliasService;
    }

    /**
     * Generate an item list component for exercise selection
     * 
     * @param int $userId
     * @param array $options Configuration options:
     *   - 'filter_placeholder' => string (default: 'Search exercises...')
     *   - 'no_results_message' => string (default: 'No exercises found.')
     *   - 'initial_state' => string (default: 'expanded')
     *   - 'show_cancel_button' => bool (default: false)
     *   - 'restrict_height' => bool (default: false)
     *   - 'recent_days' => int (default: 30) - Days to consider for "recent" exercises
     *   - 'url_generator' => callable - Function to generate URL for each exercise
     *   - 'type_label_generator' => callable - Function to generate type label for each exercise
     * @return array Item list component data
     */
    public function generateExerciseList(int $userId, array $options = []): array
    {
        // Set defaults
        $options = array_merge([
            'filter_placeholder' => 'Search exercises...',
            'no_results_message' => 'No exercises found.',
            'initial_state' => 'expanded',
            'show_cancel_button' => false,
            'restrict_height' => false,
            'recent_days' => 30,
            'url_generator' => null,
            'type_label_generator' => null,
        ], $options);

        // Get user's accessible exercises with aliases
        $exercises = Exercise::availableToUser($userId)
            ->with(['aliases' => function ($query) use ($userId) {
                $query->where('user_id', $userId);
            }])
            ->orderBy('title', 'asc')
            ->get();

        // Apply aliases to exercises
        $user = \App\Models\User::find($userId);
        $exercises = $this->aliasService->applyAliasesToExercises($exercises, $user);

        // Get recent exercises (based on lift logs)
        $recentExerciseIds = LiftLog::where('user_id', $userId)
            ->where('logged_at', '>=', now()->subDays($options['recent_days']))
            ->distinct()
            ->pluck('exercise_id')
            ->toArray();

        // Build item list
        $items = [];
        
        foreach ($exercises as $exercise) {
            $isRecent = in_array($exercise->id, $recentExerciseIds);
            
            // Generate URL using provided callback or default
            if ($options['url_generator']) {
                $href = $options['url_generator']($exercise);
            } else {
                $href = '#';
            }
            
            // Generate type label using provided callback or default
            if ($options['type_label_generator']) {
                $typeLabel = $options['type_label_generator']($exercise, $isRecent);
            } else {
                $typeLabel = $isRecent ? 'Recent' : 'All';
            }
            
            $typeCssClass = $isRecent ? 'recent' : 'all';
            $priority = $isRecent ? 1 : 2;
            
            $items[] = [
                'id' => (string) $exercise->id,
                'name' => $exercise->title,
                'href' => $href,
                'type' => [
                    'label' => $typeLabel,
                    'cssClass' => $typeCssClass,
                    'priority' => $priority
                ]
            ];
        }

        return [
            'items' => $items,
            'filterPlaceholder' => $options['filter_placeholder'],
            'noResultsMessage' => $options['no_results_message'],
            'initialState' => $options['initial_state'],
            'showCancelButton' => $options['show_cancel_button'],
            'restrictHeight' => $options['restrict_height'],
        ];
    }

    /**
     * Get exercises that the user has logged (for metrics page)
     * 
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getLoggedExercises(int $userId)
    {
        return Exercise::whereHas('liftLogs', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })
        ->with([
            'aliases' => function ($query) use ($userId) {
                $query->where('user_id', $userId);
            },
            'user' // Load user relationship for badge display
        ])
        ->orderBy('title', 'asc')
        ->get();
    }

    /**
     * Get lift log counts for exercises
     * 
     * @param int $userId
     * @param array $exerciseIds
     * @return \Illuminate\Support\Collection
     */
    public function getExerciseLogCounts(int $userId, array $exerciseIds)
    {
        return LiftLog::where('user_id', $userId)
            ->whereIn('exercise_id', $exerciseIds)
            ->select('exercise_id', DB::raw('count(*) as log_count'))
            ->groupBy('exercise_id')
            ->pluck('log_count', 'exercise_id');
    }
}
