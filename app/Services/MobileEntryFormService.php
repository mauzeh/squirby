<?php

namespace App\Services;

use App\Models\Program;
use App\Models\LiftLog;
use App\Models\Exercise;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class MobileEntryFormService
{
    /**
     * Generate forms based on user's programs for the selected date
     * 
     * @param int $userId
     * @param Carbon $selectedDate
     * @return array
     */
    public function generateProgramForms($userId, Carbon $selectedDate)
    {
        // Get user's programs for the selected date
        $programs = Program::where('user_id', $userId)
            ->whereDate('date', $selectedDate->toDateString())
            ->with(['exercise'])
            ->orderBy('priority', 'asc')
            ->get();
        
        $forms = [];
        
        foreach ($programs as $program) {
            if (!$program->exercise) {
                continue; // Skip if exercise doesn't exist
            }
            
            $exercise = $program->exercise;
            
            // Get last session data for this exercise
            $lastSession = $this->getLastSessionData($exercise->id, $selectedDate, $userId);
            
            // Generate form ID
            $formId = 'program-' . $program->id;
            
            // Determine default weight based on last session or exercise type
            $defaultWeight = $this->getDefaultWeight($exercise, $lastSession);
            
            // Generate messages based on last session and program
            $messages = $this->generateFormMessages($program, $lastSession);
            
            $forms[] = [
                'id' => $formId,
                'type' => 'exercise',
                'title' => $exercise->title,
                'itemName' => $exercise->title,
                'formAction' => route('lift-logs.store'),
                'deleteAction' => route('mobile-entry.remove-form', ['id' => $formId]),
                'messages' => $messages,
                'numericFields' => [
                    [
                        'id' => $formId . '-weight',
                        'name' => 'weight',
                        'label' => $exercise->is_bodyweight ? 'Added Weight (lbs):' : 'Weight (lbs):',
                        'defaultValue' => $defaultWeight,
                        'increment' => $exercise->is_bodyweight ? 2.5 : 5,
                        'min' => $exercise->is_bodyweight ? 0 : 45,
                        'max' => 600,
                        'ariaLabels' => [
                            'decrease' => 'Decrease weight',
                            'increase' => 'Increase weight'
                        ]
                    ],
                    [
                        'id' => $formId . '-reps',
                        'name' => 'reps',
                        'label' => 'Reps:',
                        'defaultValue' => $program->reps ?? ($lastSession['reps'] ?? 5),
                        'increment' => 1,
                        'min' => 1,
                        'max' => 50,
                        'ariaLabels' => [
                            'decrease' => 'Decrease reps',
                            'increase' => 'Increase reps'
                        ]
                    ],
                    [
                        'id' => $formId . '-sets',
                        'name' => 'sets',
                        'label' => 'Sets:',
                        'defaultValue' => $program->sets ?? ($lastSession['sets'] ?? 3),
                        'increment' => 1,
                        'min' => 1,
                        'max' => 10,
                        'ariaLabels' => [
                            'decrease' => 'Decrease sets',
                            'increase' => 'Increase sets'
                        ]
                    ]
                ],
                'commentField' => [
                    'id' => $formId . '-comment',
                    'name' => 'comment',
                    'label' => 'Notes:',
                    'placeholder' => 'RPE, form notes, how did it feel?',
                    'defaultValue' => $program->comments ?? ''
                ],
                'buttons' => [
                    'decrement' => '-',
                    'increment' => '+',
                    'submit' => 'Log ' . $exercise->title
                ],
                'ariaLabels' => [
                    'section' => $exercise->title . ' entry',
                    'deleteForm' => 'Remove this exercise form'
                ],
                // Hidden fields for form submission
                'hiddenFields' => [
                    'exercise_id' => $exercise->id,
                    'program_id' => $program->id,
                    'logged_at' => $selectedDate->toDateString()
                ]
            ];
        }
        
        return $forms;
    }

    /**
     * Get last session data for an exercise
     * 
     * @param int $exerciseId
     * @param Carbon $beforeDate
     * @param int $userId
     * @return array|null
     */
    public function getLastSessionData($exerciseId, Carbon $beforeDate, $userId)
    {
        $lastLog = LiftLog::where('user_id', $userId)
            ->where('exercise_id', $exerciseId)
            ->where('logged_at', '<', $beforeDate->toDateString())
            ->with(['liftSets'])
            ->orderBy('logged_at', 'desc')
            ->first();
        
        if (!$lastLog || $lastLog->liftSets->isEmpty()) {
            return null;
        }
        
        $firstSet = $lastLog->liftSets->first();
        
        return [
            'weight' => $firstSet->weight,
            'reps' => $firstSet->reps,
            'sets' => $lastLog->liftSets->count(),
            'date' => $lastLog->logged_at->format('M j'),
            'comments' => $lastLog->comments
        ];
    }

    /**
     * Determine default weight for an exercise
     * 
     * @param \App\Models\Exercise $exercise
     * @param array|null $lastSession
     * @return float
     */
    public function getDefaultWeight($exercise, $lastSession)
    {
        if ($exercise->is_bodyweight) {
            return $lastSession['weight'] ?? 0; // Added weight for bodyweight exercises
        }
        
        if ($lastSession) {
            // Suggest a small progression from last session
            return $lastSession['weight'] + 5;
        }
        
        // Default starting weights for common exercises
        $defaults = [
            'bench_press' => 135,
            'squat' => 185,
            'deadlift' => 225,
            'overhead_press' => 95,
            'barbell_row' => 115,
        ];
        
        $canonicalName = $exercise->canonical_name ?? '';
        return $defaults[$canonicalName] ?? 95; // Default to 95 lbs
    }

    /**
     * Generate messages for a form based on program and last session
     * 
     * @param \App\Models\Program $program
     * @param array|null $lastSession
     * @return array
     */
    public function generateFormMessages($program, $lastSession)
    {
        $messages = [];
        
        // Add last session info if available
        if ($lastSession) {
            $messages[] = [
                'type' => 'info',
                'prefix' => 'Last session (' . $lastSession['date'] .'):',
                'text' => $lastSession['weight'] . ' lbs × ' . $lastSession['reps'] . ' reps × ' . $lastSession['sets'] . ' sets'
            ];
        }
        
        // Add program comments if available
        if ($program->comments) {
            $messages[] = [
                'type' => 'tip',
                'prefix' => 'Program notes:',
                'text' => $program->comments
            ];
        }
        
        // Add progression suggestion
        if ($lastSession && !$program->exercise->is_bodyweight) {
            $messages[] = [
                'type' => 'tip',
                'prefix' => 'Suggestion:',
                'text' => 'Try ' . ($lastSession['weight'] + 5) . ' lbs today'
            ];
        }
        
        return $messages;
    }

    /**
     * Generate summary data based on user's lift logs for the selected date
     * 
     * @param int $userId
     * @param Carbon $selectedDate
     * @return array
     */
    public function generateLiftSummary($userId, Carbon $selectedDate)
    {
        // Get today's lift logs
        $todaysLogs = LiftLog::where('user_id', $userId)
            ->whereDate('logged_at', $selectedDate->toDateString())
            ->with(['liftSets'])
            ->get();

        // Calculate total weight lifted today
        $totalWeight = 0;
        $totalSets = 0;
        foreach ($todaysLogs as $log) {
            foreach ($log->liftSets as $set) {
                $totalWeight += $set->weight * $set->reps;
                $totalSets++;
            }
        }

        // Get unique exercises completed today
        $exercisesCompleted = $todaysLogs->unique('exercise_id')->count();

        // Calculate average intensity (placeholder - could be based on 1RM percentages)
        $averageIntensity = $totalSets > 0 ? min(100, ($totalWeight / $totalSets) / 10) : 0;

        return [
            'values' => [
                'total' => (int) $totalWeight,
                'completed' => $exercisesCompleted,
                'average' => (int) $averageIntensity,
                'today' => $totalSets
            ],
            'labels' => [
                'total' => 'Total Weight (lbs)',
                'completed' => 'Exercises',
                'average' => 'Avg Intensity %',
                'today' => 'Sets Today'
            ],
            'ariaLabels' => [
                'section' => 'Lift session summary'
            ]
        ];
    }

    /**
     * Generate logged items data for the selected date
     * 
     * @param int $userId
     * @param Carbon $selectedDate
     * @return array
     */
    public function generateLoggedItems($userId, Carbon $selectedDate)
    {
        $logs = LiftLog::where('user_id', $userId)
            ->whereDate('logged_at', $selectedDate->toDateString())
            ->with(['exercise', 'liftSets'])
            ->orderBy('logged_at', 'desc')
            ->get();

        $items = [];
        foreach ($logs as $log) {
            if ($log->liftSets->isEmpty()) {
                continue;
            }

            $firstSet = $log->liftSets->first();
            $setCount = $log->liftSets->count();
            
            // Format display value
            $displayValue = $log->exercise->is_bodyweight && $firstSet->weight == 0
                ? 'BW×' . $firstSet->reps . '×' . $setCount
                : $firstSet->weight . '×' . $firstSet->reps . '×' . $setCount;

            $items[] = [
                'id' => $log->id,
                'value' => $displayValue,
                'editAction' => route('lift-logs.edit', ['lift_log' => $log->id]),
                'deleteAction' => route('lift-logs.destroy', ['lift_log' => $log->id]),
                'message' => [
                    'type' => 'neutral',
                    'prefix' => $log->exercise->title . ':',
                    'text' => $firstSet->weight . ' lbs × ' . $firstSet->reps . ' reps × ' . $setCount . ' sets'
                ],
                'freeformText' => $log->comments
            ];
        }

        return [
            'emptyMessage' => 'No lifts logged yet today!',
            'title' => 'Today\'s Lifts',
            'items' => $items,
            'ariaLabels' => [
                'section' => 'Logged lifts',
                'editItem' => 'Edit logged lift',
                'deleteItem' => 'Delete logged lift'
            ]
        ];
    }

    /**
     * Generate item selection list based on user's accessible exercises
     * 
     * @param int $userId
     * @param Carbon $selectedDate
     * @return array
     */
    public function generateItemSelectionList($userId, Carbon $selectedDate)
    {
        // Get user's accessible exercises with recent usage data
        $exercises = Exercise::availableToUser($userId)
            ->with(['liftLogs' => function ($query) use ($userId, $selectedDate) {
                $query->where('user_id', $userId)
                    ->where('logged_at', '>=', $selectedDate->copy()->subDays(30))
                    ->orderBy('logged_at', 'desc')
                    ->limit(1);
            }])
            ->orderBy('title', 'asc')
            ->get();

        // Get exercises that are in today's program to highlight them
        $programExerciseIds = Program::where('user_id', $userId)
            ->whereDate('date', $selectedDate->toDateString())
            ->pluck('exercise_id')
            ->toArray();

        // Get recently used exercises (last 7 days) for prioritization
        $recentExerciseIds = LiftLog::where('user_id', $userId)
            ->where('logged_at', '>=', $selectedDate->copy()->subDays(7))
            ->pluck('exercise_id')
            ->unique()
            ->toArray();

        $items = [];
        
        foreach ($exercises as $exercise) {
            // Determine item type based on program and recent usage
            $type = 'regular';
            if (in_array($exercise->id, $programExerciseIds)) {
                $type = 'highlighted'; // In today's program
            } elseif (in_array($exercise->id, $recentExerciseIds)) {
                $type = 'highlighted'; // Used recently
            }

            $items[] = [
                'id' => 'exercise-' . $exercise->id,
                'name' => $exercise->title,
                'type' => $type,
                'href' => route('mobile-entry.add-lift-form', ['exercise' => $exercise->canonical_name ?? $exercise->id])
            ];
        }

        // Sort items: highlighted first, then alphabetical
        usort($items, function ($a, $b) {
            if ($a['type'] === $b['type']) {
                return strcmp($a['name'], $b['name']);
            }
            return $a['type'] === 'highlighted' ? -1 : 1;
        });

        // Limit to reasonable number for mobile interface
        $items = array_slice($items, 0, 20);

        return [
            'noResultsMessage' => 'No exercises found. Hit "+" to save as new exercise.',
            'createForm' => [
                'action' => route('mobile-entry.create-exercise'),
                'method' => 'POST',
                'inputName' => 'exercise_name',
                'submitText' => '+',
                'ariaLabel' => 'Create new exercise'
            ],
            'items' => $items,
            'ariaLabels' => [
                'section' => 'Exercise selection list',
                'selectItem' => 'Select this exercise to log'
            ],
            'filterPlaceholder' => 'Filter exercises...'
        ];
    }
}