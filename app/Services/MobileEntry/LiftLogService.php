<?php

namespace App\Services\MobileEntry;

use App\Models\Program;
use App\Models\LiftLog;
use App\Models\Exercise;
use App\Services\TrainingProgressionService;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class LiftLogService
{
    protected TrainingProgressionService $trainingProgressionService;

    public function __construct(TrainingProgressionService $trainingProgressionService)
    {
        $this->trainingProgressionService = $trainingProgressionService;
    }
    /**
     * Generate forms based on user's programs for the selected date
     * 
     * @param int $userId
     * @param Carbon $selectedDate
     * @param bool $includeCompleted Whether to include already completed programs (default: false)
     * @return array
     */
    public function generateProgramForms($userId, Carbon $selectedDate, $includeCompleted = false)
    {
        // Get user to check preferences
        $user = \App\Models\User::find($userId);
        
        // Get user's programs for the selected date with completion status preloaded
        $query = Program::where('user_id', $userId)
            ->whereDate('date', $selectedDate->toDateString())
            ->with(['exercise'])
            ->withCompletionStatus();
            
        // Optionally filter out completed programs
        if (!$includeCompleted) {
            $query->incomplete();
        }
        
        $programs = $query->orderBy('priority', 'asc')->get();
        
        if ($programs->isEmpty()) {
            return [];
        }
        
        // Get all exercise IDs for batch queries
        $exerciseIds = $programs->pluck('exercise.id')->filter()->unique();
        
        // Batch fetch last session data for all exercises
        $lastSessionsData = $this->getBatchLastSessionData($exerciseIds->toArray(), $selectedDate, $userId);
        
        // Batch fetch progression suggestions for all exercises
        $progressionSuggestions = [];
        foreach ($exerciseIds as $exerciseId) {
            if (isset($lastSessionsData[$exerciseId])) {
                $progressionSuggestions[$exerciseId] = $this->trainingProgressionService->getSuggestionDetails(
                    $userId, 
                    $exerciseId
                );
            }
        }
        
        $forms = [];
        
        foreach ($programs as $program) {
            if (!$program->exercise) {
                continue; // Skip if exercise doesn't exist
            }
            
            $exercise = $program->exercise;
            
            // Get last session data from batch results
            $lastSession = $lastSessionsData[$exercise->id] ?? null;
            
            // Generate form ID
            $formId = 'program-' . $program->id;
            
            // Get progression suggestions from batch results
            $progressionSuggestion = $progressionSuggestions[$exercise->id] ?? null;
            
            // Determine default weight based on last session or exercise type
            $defaultWeight = $this->getDefaultWeight($exercise, $lastSession, $userId);
            
            // Determine default reps and sets from progression service or fallback
            $defaultReps = $progressionSuggestion->reps ?? $program->reps ?? ($lastSession['reps'] ?? 5);
            $defaultSets = $progressionSuggestion->sets ?? $program->sets ?? ($lastSession['sets'] ?? 3);
            
            // Generate messages based on last session and program
            $messages = $this->generateFormMessages($program, $lastSession, $userId);
            
            // Check if program is completed (this is already optimized in the scope)
            $isCompleted = $program->isCompleted();
            
            // Build numeric fields based on exercise type
            $numericFields = [];
            
            // Add weight or band color field based on exercise type
            if ($exercise->band_type) {
                // For banded exercises, add band color selector instead of weight
                $bandColors = config('bands.colors', []);
                $defaultBandColor = $lastSession['band_color'] ?? 'red'; // Default to red band
                
                $numericFields[] = [
                    'id' => $formId . '-band-color',
                    'name' => 'band_color',
                    'label' => 'Band Color:',
                    'type' => 'select',
                    'defaultValue' => $defaultBandColor,
                    'options' => array_map(function($color) {
                        return ['value' => $color, 'label' => ucfirst($color)];
                    }, array_keys($bandColors)),
                    'ariaLabels' => [
                        'field' => 'Select band color'
                    ]
                ];
            } else {
                // For weighted exercises, add weight field
                // For bodyweight exercises, only show weight field if user has show_extra_weight enabled
                $shouldShowWeightField = !$exercise->is_bodyweight || ($user && $user->shouldShowExtraWeight());
                
                if ($shouldShowWeightField) {
                    $numericFields[] = [
                        'id' => $formId . '-weight',
                        'name' => 'weight',
                        'label' => $exercise->is_bodyweight ? 'Added Weight (lbs):' : 'Weight (lbs):',
                        'defaultValue' => $defaultWeight,
                        'increment' => $exercise->is_bodyweight ? 2.5 : 5,
                        'min' => 0,
                        'max' => 600,
                        'ariaLabels' => [
                            'decrease' => 'Decrease weight',
                            'increase' => 'Increase weight'
                        ]
                    ];
                }
            }
            
            // Add reps and sets fields
            $numericFields[] = [
                'id' => $formId . '-reps',
                'name' => 'reps',
                'label' => 'Reps:',
                'defaultValue' => $defaultReps,
                'increment' => 1,
                'min' => 1,
                'max' => 50,
                'ariaLabels' => [
                    'decrease' => 'Decrease reps',
                    'increase' => 'Increase reps'
                ]
            ];
            
            $numericFields[] = [
                'id' => $formId . '-rounds',
                'name' => 'rounds',
                'label' => 'Sets:',
                'defaultValue' => $defaultSets,
                'increment' => 1,
                'min' => 1,
                'max' => 10,
                'ariaLabels' => [
                    'decrease' => 'Decrease sets',
                    'increase' => 'Increase sets'
                ]
            ];
            
            $forms[] = [
                'id' => $formId,
                'type' => 'exercise',
                'title' => $exercise->title,
                'itemName' => $exercise->title,
                'formAction' => route('lift-logs.store'),
                'deleteAction' => route('mobile-entry.remove-form', ['id' => $formId]),
                'deleteParams' => [
                    'date' => $selectedDate->toDateString()
                ],
                'messages' => $messages,
                'numericFields' => $numericFields,
                'commentField' => [
                    'id' => $formId . '-comment',
                    'name' => 'comments',
                    'label' => 'Notes:',
                    'placeholder' => 'RPE, form notes, how did it feel?',
                    'defaultValue' => ''
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
                    'date' => $selectedDate->toDateString(),
                    'redirect_to' => 'mobile-entry-lifts'
                ],
                // Completion status
                'isCompleted' => $isCompleted,
                'completionStatus' => $isCompleted ? 'completed' : 'pending'
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
            'comments' => $lastLog->comments,
            'band_color' => $firstSet->band_color
        ];
    }

    /**
     * Get last session data for multiple exercises in a single batch query
     * 
     * @param array $exerciseIds
     * @param Carbon $beforeDate
     * @param int $userId
     * @return array Keyed by exercise_id
     */
    protected function getBatchLastSessionData(array $exerciseIds, Carbon $beforeDate, $userId)
    {
        if (empty($exerciseIds)) {
            return [];
        }

        // Get the most recent log for each exercise using a subquery approach
        // This is more compatible across different database systems
        $lastLogs = LiftLog::where('user_id', $userId)
            ->whereIn('exercise_id', $exerciseIds)
            ->where('logged_at', '<', $beforeDate->toDateString())
            ->whereIn('id', function ($query) use ($userId, $exerciseIds, $beforeDate) {
                $query->select(\DB::raw('MAX(id)'))
                    ->from('lift_logs')
                    ->where('user_id', $userId)
                    ->whereIn('exercise_id', $exerciseIds)
                    ->where('logged_at', '<', $beforeDate->toDateString())
                    ->groupBy('exercise_id');
            })
            ->with(['liftSets'])
            ->get();

        $results = [];
        
        foreach ($lastLogs as $lastLog) {
            if ($lastLog->liftSets->isEmpty()) {
                continue;
            }
            
            $firstSet = $lastLog->liftSets->first();
            
            $results[$lastLog->exercise_id] = [
                'weight' => $firstSet->weight,
                'reps' => $firstSet->reps,
                'sets' => $lastLog->liftSets->count(),
                'date' => $lastLog->logged_at->format('M j'),
                'comments' => $lastLog->comments,
                'band_color' => $firstSet->band_color
            ];
        }
        
        return $results;
    }

    /**
     * Determine default weight for an exercise
     * 
     * @param \App\Models\Exercise $exercise
     * @param array|null $lastSession
     * @return float
     */
    public function getDefaultWeight($exercise, $lastSession, $userId = null)
    {
        if ($exercise->is_bodyweight) {
            return $lastSession['weight'] ?? 0; // Added weight for bodyweight exercises
        }
        
        if ($lastSession && $userId) {
            // Use TrainingProgressionService for intelligent progression
            $suggestion = $this->trainingProgressionService->getSuggestionDetails(
                $userId, 
                $exercise->id
            );
            
            if ($suggestion && isset($suggestion->suggestedWeight)) {
                return $suggestion->suggestedWeight;
            }
            
            // Fallback to simple progression if service fails
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
    public function generateFormMessages($program, $lastSession, $userId = null)
    {
        $messages = [];
        
        // Add last session info if available
        if ($lastSession) {
            // Format the resistance/weight info based on exercise type
            if ($program->exercise->band_type) {
                // For banded exercises, show band color
                $bandColor = $lastSession['band_color'] ?? 'Unknown';
                $resistanceText = ucfirst($bandColor) . ' band';
                $messageText = $resistanceText . ' × ' . $lastSession['reps'] . ' reps × ' . $lastSession['sets'] . ' sets';
            } elseif ($program->exercise->is_bodyweight) {
                // For bodyweight exercises, only show additional weight if present
                if ($lastSession['weight'] > 0) {
                    $resistanceText = '+' . $lastSession['weight'] . ' lbs';
                    $messageText = $resistanceText . ' × ' . $lastSession['reps'] . ' reps × ' . $lastSession['sets'] . ' sets';
                } else {
                    // No weight mentioned, just reps and sets
                    $messageText = $lastSession['reps'] . ' reps × ' . $lastSession['sets'] . ' sets';
                }
            } else {
                // For regular weighted exercises
                $resistanceText = $lastSession['weight'] . ' lbs';
                $messageText = $resistanceText . ' × ' . $lastSession['reps'] . ' reps × ' . $lastSession['sets'] . ' sets';
            }
            
            $messages[] = [
                'type' => 'info',
                'prefix' => 'Last session (' . $lastSession['date'] .'):',
                'text' => $messageText
            ];
        }
        
        // Add last session comments if available
        if ($lastSession && !empty($lastSession['comments'])) {
            $messages[] = [
                'type' => 'neutral',
                'prefix' => 'Last notes:',
                'text' => $lastSession['comments']
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
        if ($lastSession && $userId) {
            $suggestion = $this->trainingProgressionService->getSuggestionDetails(
                $userId, 
                $program->exercise_id
            );
            
            if ($suggestion) {
                if (isset($suggestion->band_color)) {
                    // Banded exercise suggestion
                    $sets = $suggestion->sets ?? $lastSession['sets'] ?? 3;
                    $messages[] = [
                        'type' => 'tip',
                        'prefix' => 'Suggestion:',
                        'text' => 'Try ' . $suggestion->band_color . ' band × ' . $suggestion->reps . ' reps × ' . $sets . ' sets today'
                    ];
                } elseif (isset($suggestion->suggestedWeight) && !$program->exercise->is_bodyweight) {
                    // Weighted exercise suggestion
                    $sets = $suggestion->sets ?? $lastSession['sets'] ?? 3;
                    $messages[] = [
                        'type' => 'tip',
                        'prefix' => 'Suggestion:',
                        'text' => 'Try ' . $suggestion->suggestedWeight . ' lbs × ' . $suggestion->reps . ' reps × ' . $sets . ' sets today'
                    ];
                } elseif ($program->exercise->is_bodyweight && isset($suggestion->reps)) {
                    // Bodyweight exercise suggestion
                    $sets = $suggestion->sets ?? $lastSession['sets'] ?? 3;
                    $messages[] = [
                        'type' => 'tip',
                        'prefix' => 'Suggestion:',
                        'text' => 'Try ' . $suggestion->reps . ' reps × ' . $sets . ' sets today'
                    ];
                }
            } elseif (!$program->exercise->is_bodyweight) {
                // Fallback to simple progression if service fails
                $sets = $lastSession['sets'] ?? 3;
                $reps = $lastSession['reps'] ?? 5;
                $messages[] = [
                    'type' => 'tip',
                    'prefix' => 'Suggestion:',
                    'text' => 'Try ' . ($lastSession['weight'] + 5) . ' lbs × ' . $reps . ' reps × ' . $sets . ' sets today'
                ];
            }
        }
        
        return $messages;
    }

    /**
     * Generate summary data based on user's logs for the selected date
     * 
     * @param int $userId
     * @param Carbon $selectedDate
     * @return null
     */
    public function generateSummary($userId, Carbon $selectedDate)
    {
        return null;
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

            // Get first set data directly from loaded relationship to avoid additional queries
            $firstSet = $log->liftSets->first();
            $setCount = $log->liftSets->count();
            
            // Generate display text without using accessors that might trigger queries
            if ($log->exercise->band_type) {
                $weightText = 'Band: ' . ($firstSet->band_color ?? 'N/A');
            } elseif ($log->exercise->is_bodyweight) {
                // For bodyweight exercises, only show additional weight if present
                if ($firstSet->weight > 0) {
                    $weightText = '+' . $firstSet->weight . ' lbs';
                } else {
                    $weightText = '';
                }
            } else {
                $weightText = $firstSet->weight . ' lbs';
            }
            
            // Generate reps/sets text directly
            $repsSetsText = $setCount . ' x ' . $firstSet->reps;
            
            // Combine weight and reps/sets for the message
            if ($log->exercise->is_bodyweight && $firstSet->weight == 0) {
                // For bodyweight with no additional weight, just show reps/sets
                $formattedMessage = $repsSetsText;
            } else {
                // For all other cases, show weight × reps/sets
                $formattedMessage = $weightText . ' × ' . $repsSetsText;
            }

            $items[] = [
                'id' => $log->id,
                'title' => $log->exercise->title,
                'editAction' => route('lift-logs.edit', ['lift_log' => $log->id]),
                'deleteAction' => route('lift-logs.destroy', ['lift_log' => $log->id]),
                'deleteParams' => [
                    'redirect_to' => 'mobile-entry-lifts',
                    'date' => $selectedDate->toDateString()
                ],
                'message' => [
                    'type' => 'success',
                    'prefix' => 'Completed!',
                    'text' => $formattedMessage
                ],
                'freeformText' => $log->comments
            ];
        }

        $result = [
            'items' => $items,
            'confirmMessages' => [
                'deleteItem' => 'Are you sure you want to delete this lift log entry? This action cannot be undone.',
                'removeForm' => 'Are you sure you want to remove this exercise from today\'s program?'
            ],
            'ariaLabels' => [
                'section' => 'Logged entries',
                'editItem' => 'Edit logged entry',
                'deleteItem' => 'Delete logged entry'
            ]
        ];

        // Only include empty message when there are no items
        if (empty($items)) {
            $result['emptyMessage'] = 'No entries logged yet today!';
        }

        return $result;
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
        // Get user's accessible exercises
        $exercises = Exercise::availableToUser($userId)
            ->orderBy('title', 'asc')
            ->get();

        // Get the top 5 most recently used exercises for this user in a single query
        $recentExerciseIds = $this->getTopRecentExerciseIds($userId, $selectedDate, 5);

        // Get all exercises that are in today's program in a single query
        $programExerciseIds = Program::where('user_id', $userId)
            ->whereDate('date', $selectedDate->toDateString())
            ->pluck('exercise_id')
            ->toArray();

        $items = [];
        
        foreach ($exercises as $exercise) {
            // Determine item type based on program and recent usage (optimized)
            $itemType = $this->determineItemTypeOptimized($exercise, $userId, $recentExerciseIds, $programExerciseIds);

            $items[] = [
                'id' => 'exercise-' . $exercise->id,
                'name' => $exercise->title,
                'type' => $itemType,
                'href' => route('mobile-entry.add-lift-form', [
                    'exercise' => $exercise->canonical_name ?? $exercise->id,
                    'date' => $selectedDate->toDateString()
                ])
            ];
        }

        // Sort items: by priority first, then alphabetical by name
        usort($items, function ($a, $b) {
            // First sort by priority (lower number = higher priority)
            $priorityComparison = $a['type']['priority'] <=> $b['type']['priority'];
            if ($priorityComparison !== 0) {
                return $priorityComparison;
            }
            
            // If same priority, sort alphabetically by name
            return strcmp($a['name'], $b['name']);
        });

        return [
            'noResultsMessage' => 'No exercises found. Hit "+" to save as new exercise.',
            'createForm' => [
                'action' => route('mobile-entry.create-exercise'),
                'method' => 'POST',
                'inputName' => 'exercise_name',
                'submitText' => '+',
                'ariaLabel' => 'Create new exercise',
                'hiddenFields' => [
                    'date' => $selectedDate->toDateString()
                ]
            ],
            'items' => $items,
            'ariaLabels' => [
                'section' => 'Exercise selection list',
                'selectItem' => 'Select this exercise to log'
            ],
            'filterPlaceholder' => 'Filter exercises...'
        ];
    }

    /**
     * Get the top N most recently used exercise IDs for a user
     * 
     * @param int $userId
     * @param Carbon $selectedDate
     * @param int $limit
     * @return array
     */
    protected function getTopRecentExerciseIds($userId, Carbon $selectedDate, $limit = 5)
    {
        return LiftLog::where('user_id', $userId)
            ->where('logged_at', '<', $selectedDate->toDateString())
            ->where('logged_at', '>=', $selectedDate->copy()->subDays(30))
            ->select('exercise_id')
            ->groupBy('exercise_id')
            ->orderByRaw('MAX(logged_at) DESC')
            ->limit($limit)
            ->pluck('exercise_id')
            ->toArray();
    }

    /**
     * Determine the item type configuration for an exercise
     * 
     * @param \App\Models\Exercise $exercise
     * @param int $userId
     * @param Carbon $selectedDate
     * @param array $recentExerciseIds
     * @return array Item type configuration with label and cssClass
     */
    protected function determineItemType($exercise, $userId, Carbon $selectedDate, $recentExerciseIds = [])
    {
        // Check if exercise is in today's program
        $inProgram = Program::where('user_id', $userId)
            ->where('exercise_id', $exercise->id)
            ->whereDate('date', $selectedDate->toDateString())
            ->exists();

        // Check if exercise is in the top 5 most recently used
        $isTopRecent = in_array($exercise->id, $recentExerciseIds);

        // Check if it's a user's custom exercise
        $isCustom = $exercise->user_id === $userId;

        // Determine type based on priority: recent > custom > regular > in-program
        if ($isTopRecent) {
            return $this->getItemTypeConfig('recent');
        } elseif ($isCustom) {
            return $this->getItemTypeConfig('custom');
        } elseif ($inProgram) {
            return $this->getItemTypeConfig('in-program');
        } else {
            return $this->getItemTypeConfig('regular');
        }
    }

    /**
     * Optimized version of determineItemType that uses pre-fetched data
     * 
     * @param \App\Models\Exercise $exercise
     * @param int $userId
     * @param array $recentExerciseIds
     * @param array $programExerciseIds
     * @return array Item type configuration with label and cssClass
     */
    protected function determineItemTypeOptimized($exercise, $userId, $recentExerciseIds = [], $programExerciseIds = [])
    {
        // Check if exercise is in today's program (using pre-fetched data)
        $inProgram = in_array($exercise->id, $programExerciseIds);

        // Check if exercise is in the top 5 most recently used
        $isTopRecent = in_array($exercise->id, $recentExerciseIds);

        // Check if it's a user's custom exercise
        $isCustom = $exercise->user_id === $userId;

        // Determine type based on priority: recent > custom > regular > in-program
        if ($isTopRecent) {
            return $this->getItemTypeConfig('recent');
        } elseif ($isCustom) {
            return $this->getItemTypeConfig('custom');
        } elseif ($inProgram) {
            return $this->getItemTypeConfig('in-program');
        } else {
            return $this->getItemTypeConfig('regular');
        }
    }

    /**
     * Get item type configuration
     * 
     * @param string $typeKey
     * @return array Configuration with label and cssClass
     */
    protected function getItemTypeConfig($typeKey)
    {
        $itemTypes = [
            'recent' => [
                'label' => 'Recent',
                'cssClass' => 'recent',
                'priority' => 1
            ],
            'custom' => [
                'label' => 'My Exercise',
                'cssClass' => 'custom',
                'priority' => 2
            ],
            'regular' => [
                'label' => 'Available',
                'cssClass' => 'regular',
                'priority' => 3
            ],
            'in-program' => [
                'label' => 'In Program',
                'cssClass' => 'in-program',
                'priority' => 4
            ]
        ];

        return $itemTypes[$typeKey] ?? $itemTypes['regular'];
    }

    /**
     * Get program completion statistics for a given date
     * 
     * @param int $userId
     * @param Carbon $selectedDate
     * @return array
     */
    public function getProgramCompletionStats($userId, Carbon $selectedDate)
    {
        $totalPrograms = Program::where('user_id', $userId)
            ->whereDate('date', $selectedDate->toDateString())
            ->count();
            
        $completedPrograms = Program::where('user_id', $userId)
            ->whereDate('date', $selectedDate->toDateString())
            ->completed()
            ->count();
            
        $incompletePrograms = $totalPrograms - $completedPrograms;
        $completionPercentage = $totalPrograms > 0 ? round(($completedPrograms / $totalPrograms) * 100) : 0;
        
        return [
            'total' => $totalPrograms,
            'completed' => $completedPrograms,
            'incomplete' => $incompletePrograms,
            'completionPercentage' => $completionPercentage
        ];
    }

    /**
     * Get incomplete programs for a given date
     * 
     * @param int $userId
     * @param Carbon $selectedDate
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getIncompletePrograms($userId, Carbon $selectedDate)
    {
        return Program::where('user_id', $userId)
            ->whereDate('date', $selectedDate->toDateString())
            ->incomplete()
            ->with(['exercise'])
            ->orderBy('priority', 'asc')
            ->get();
    }

    /**
     * Get completed programs for a given date
     * 
     * @param int $userId
     * @param Carbon $selectedDate
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getCompletedPrograms($userId, Carbon $selectedDate)
    {
        return Program::where('user_id', $userId)
            ->whereDate('date', $selectedDate->toDateString())
            ->completed()
            ->with(['exercise'])
            ->orderBy('priority', 'asc')
            ->get();
    }

    /**
     * Add an exercise form by finding the exercise and creating a program entry
     * 
     * @param int $userId
     * @param string $exerciseIdentifier Exercise canonical name or ID
     * @param Carbon $selectedDate
     * @return array Result with success/error status and message
     */
    public function addExerciseForm($userId, $exerciseIdentifier, Carbon $selectedDate)
    {
        // Find the exercise by canonical name or ID
        $exercise = Exercise::where('canonical_name', $exerciseIdentifier)
            ->orWhere('id', $exerciseIdentifier)
            ->availableToUser($userId)
            ->first();
        
        if (!$exercise) {
            return [
                'success' => false,
                'message' => 'Exercise not found or not accessible.'
            ];
        }
        
        // Check if program entry already exists
        $existingProgram = Program::where('user_id', $userId)
            ->where('exercise_id', $exercise->id)
            ->whereDate('date', $selectedDate->toDateString())
            ->first();
        
        if ($existingProgram) {
            return [
                'success' => false,
                'message' => "{$exercise->title} is already in today's program."
            ];
        }
        
        // Get the minimum priority for existing programs on this date
        $minPriority = Program::where('user_id', $userId)
            ->whereDate('date', $selectedDate->toDateString())
            ->min('priority') ?? 100;
        
        // Assign a lower priority (smaller number) to put it at the top
        $newPriority = max(1, $minPriority - 1);
        
        // Create a program entry for this exercise
        Program::create([
            'user_id' => $userId,
            'exercise_id' => $exercise->id,
            'date' => $selectedDate,
            'sets' => 3, // Default values
            'reps' => 5,
            'priority' => $newPriority,
            'comments' => null
        ]);
        
        return [
            'success' => true,
            'message' => "Added {$exercise->title} to today's program."
        ];
    }

    /**
     * Create a new exercise and add it to the program
     * 
     * @param int $userId
     * @param string $exerciseName
     * @param Carbon $selectedDate
     * @return array Result with success/error status and message
     */
    public function createExercise($userId, $exerciseName, Carbon $selectedDate)
    {
        // Check if exercise with similar name already exists
        $existingExercise = Exercise::where('title', $exerciseName)
            ->availableToUser($userId)
            ->first();
        
        if ($existingExercise) {
            return [
                'success' => false,
                'message' => "Exercise '{$exerciseName}' already exists."
            ];
        }
        
        // Generate unique canonical name
        $canonicalName = $this->generateUniqueCanonicalName($exerciseName, $userId);
        
        // Create the new exercise
        $exercise = Exercise::create([
            'title' => $exerciseName,
            'user_id' => $userId,
            'is_bodyweight' => false, // Default to weighted exercise
            'canonical_name' => $canonicalName
        ]);
        
        // Get the minimum priority for existing programs on this date
        $minPriority = Program::where('user_id', $userId)
            ->whereDate('date', $selectedDate->toDateString())
            ->min('priority') ?? 100;
        
        // Assign a lower priority (smaller number) to put it at the top
        $newPriority = max(1, $minPriority - 1);
        
        // Create a program entry for this exercise
        Program::create([
            'user_id' => $userId,
            'exercise_id' => $exercise->id,
            'date' => $selectedDate,
            'sets' => 3,
            'reps' => 5,
            'priority' => $newPriority,
            'comments' => 'New exercise created'
        ]);
        
        return [
            'success' => true,
            'message' => "Created new exercise: {$exercise->title}"
        ];
    }

    /**
     * Remove a program entry (form) from the interface
     * 
     * @param int $userId
     * @param string $formId Form ID (format: program-{id})
     * @return array Result with success/error status and message
     */
    public function removeForm($userId, $formId)
    {
        // Extract program ID from form ID (format: program-{id})
        if (!str_starts_with($formId, 'program-')) {
            return [
                'success' => false,
                'message' => 'Invalid form ID format.'
            ];
        }
        
        $programId = str_replace('program-', '', $formId);
        
        $program = Program::where('id', $programId)
            ->where('user_id', $userId)
            ->with('exercise')
            ->first();
        
        if (!$program) {
            return [
                'success' => false,
                'message' => 'Program entry not found or not accessible.'
            ];
        }
        
        $exerciseTitle = $program->exercise->title ?? 'Exercise';
        $program->delete();
        
        return [
            'success' => true,
            'message' => "Removed {$exerciseTitle} from today's program."
        ];
    }

    /**
     * Generate a unique canonical name for an exercise
     * 
     * @param string $title
     * @param int $userId
     * @return string
     */
    private function generateUniqueCanonicalName($title, $userId)
    {
        $baseCanonicalName = \Illuminate\Support\Str::slug($title, '_');
        $canonicalName = $baseCanonicalName;
        $counter = 1;

        // Keep checking until we find a unique canonical name for this user
        while ($this->canonicalNameExists($canonicalName, $userId)) {
            $canonicalName = $baseCanonicalName . '_' . $counter;
            $counter++;
        }

        return $canonicalName;
    }

    /**
     * Check if a canonical name already exists for the user
     * 
     * @param string $canonicalName
     * @param int $userId
     * @return bool
     */
    private function canonicalNameExists($canonicalName, $userId)
    {
        return Exercise::where('canonical_name', $canonicalName)
            ->availableToUser($userId)
            ->exists();
    }

    /**
     * Generate system messages from session flash data
     * 
     * @param array $sessionMessages
     * @return array
     */
    private function generateSystemMessages($sessionMessages)
    {
        $messages = [];
        
        if (isset($sessionMessages['success'])) {
            $messages[] = [
                'type' => 'success',
                'prefix' => 'Success:',
                'text' => $sessionMessages['success']
            ];
        }
        
        if (isset($sessionMessages['error'])) {
            $messages[] = [
                'type' => 'error',
                'prefix' => 'Error:',
                'text' => $sessionMessages['error']
            ];
        }
        
        if (isset($sessionMessages['warning'])) {
            $messages[] = [
                'type' => 'warning',
                'prefix' => 'Warning:',
                'text' => $sessionMessages['warning']
            ];
        }
        
        if (isset($sessionMessages['info'])) {
            $messages[] = [
                'type' => 'info',
                'prefix' => 'Info:',
                'text' => $sessionMessages['info']
            ];
        }
        
        return $messages;
    }

    /**
     * Generate interface messages from session data only
     * 
     * @param array $sessionMessages
     * @return array
     */
    public function generateInterfaceMessages($sessionMessages = [])
    {
        $systemMessages = $this->generateSystemMessages($sessionMessages);
        
        return [
            'messages' => $systemMessages,
            'hasMessages' => !empty($systemMessages),
            'messageCount' => count($systemMessages)
        ];
    }
}