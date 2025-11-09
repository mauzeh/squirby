<?php

namespace App\Services\MobileEntry;

use App\Models\Program;
use App\Models\LiftLog;
use App\Models\Exercise;
use App\Services\TrainingProgressionService;
use App\Services\ExerciseAliasService;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Services\MobileEntry\MobileEntryBaseService;

class LiftLogService extends MobileEntryBaseService
{
    protected TrainingProgressionService $trainingProgressionService;
    protected LiftDataCacheService $cacheService;
    protected ExerciseAliasService $aliasService;

    public function __construct(
        TrainingProgressionService $trainingProgressionService,
        LiftDataCacheService $cacheService,
        ExerciseAliasService $aliasService
    ) {
        $this->trainingProgressionService = $trainingProgressionService;
        $this->cacheService = $cacheService;
        $this->aliasService = $aliasService;
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
        
        // Get programs with optimized completion status
        $programs = $this->cacheService->getProgramsWithCompletionStatus($userId, $selectedDate, $includeCompleted);
        
        if ($programs->isEmpty()) {
            return [];
        }
        
        // Apply aliases to program exercises
        $programs->each(function ($program) use ($user) {
            if ($program->exercise) {
                $displayName = $this->aliasService->getDisplayName($program->exercise, $user);
                $program->exercise->title = $displayName;
            }
        });
        
        // Get all exercise IDs for batch queries
        $exerciseIds = $programs->pluck('exercise.id')->filter()->unique()->toArray();
        
        // Get all cached data needed for form generation
        $cachedData = $this->cacheService->getAllCachedData($userId, $selectedDate, $exerciseIds);
        $lastSessionsData = $cachedData['lastSessionData'];
        
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
            
            // Get last session data from cached results
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
            
            // Check if program is completed (uses preloaded data)
            $isCompleted = $program->isCompleted();
            
            // Build numeric fields using exercise type strategy
            $strategy = $exercise->getTypeStrategy();
            $labels = $strategy->getFieldLabels();
            
            // Prepare default values for strategy
            $strategyDefaults = [
                'weight' => $defaultWeight,
                'reps' => $defaultReps,
                'sets' => $defaultSets,
                'band_color' => $lastSession['band_color'] ?? 'red',
            ];
            
            // Get field definitions from strategy
            $fieldDefinitions = $strategy->getFormFieldDefinitions($strategyDefaults, $user);
            
            $numericFields = [];
            
            // Convert strategy field definitions to mobile entry format
            foreach ($fieldDefinitions as $definition) {
                $field = [
                    'id' => $formId . '-' . $definition['name'],
                    'name' => $definition['name'],
                    'label' => $definition['label'],
                    'type' => $definition['type'],
                    'defaultValue' => $definition['defaultValue'],
                ];
                
                // Add type-specific properties
                if ($definition['type'] === 'numeric') {
                    $field['increment'] = $definition['increment'];
                    $field['min'] = $definition['min'];
                    $field['max'] = $definition['max'] ?? 1000;
                    
                    // Generate aria labels from field name, not label (to match existing behavior)
                    $fieldNameForAria = $definition['name'] === 'reps' && $strategy->getTypeName() === 'cardio' ? 'distance' : $definition['name'];
                    $field['ariaLabels'] = [
                        'decrease' => 'Decrease ' . $fieldNameForAria,
                        'increase' => 'Increase ' . $fieldNameForAria
                    ];
                } elseif ($definition['type'] === 'select') {
                    $field['options'] = $definition['options'];
                    $field['ariaLabels'] = [
                        'field' => 'Select ' . strtolower(trim($definition['label'], ':'))
                    ];
                }
                
                // Remove type property for numeric fields to maintain backward compatibility
                if ($definition['type'] === 'numeric') {
                    unset($field['type']);
                }
                
                $numericFields[] = $field;
            }
            
            // Always add sets/rounds field (not handled by strategy field definitions)
            $setsLabel = $labels['sets'] ?? 'Sets:';
            $numericFields[] = [
                'id' => $formId . '-rounds',
                'name' => 'rounds',
                'label' => $setsLabel,
                'defaultValue' => $defaultSets,
                'increment' => 1,
                'min' => 1,
                'ariaLabels' => [
                    'decrease' => 'Decrease ' . strtolower(trim($setsLabel, ':')),
                    'increase' => 'Increase ' . strtolower(trim($setsLabel, ':'))
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
                    'placeholder' => config('mobile_entry_messages.placeholders.workout_notes'),
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
     * Determine default weight for an exercise
     * 
     * @param \App\Models\Exercise $exercise
     * @param array|null $lastSession
     * @return float
     */
    public function getDefaultWeight($exercise, $lastSession, $userId = null)
    {
        $strategy = $exercise->getTypeStrategy();
        
        if ($lastSession && $userId) {
            // Use TrainingProgressionService for intelligent progression
            $suggestion = $this->trainingProgressionService->getSuggestionDetails(
                $userId, 
                $exercise->id
            );
            
            if ($suggestion && isset($suggestion->suggestedWeight)) {
                return $suggestion->suggestedWeight;
            }
        }
        
        // If we have a last session, use strategy's progression logic
        if ($lastSession) {
            return $strategy->getDefaultWeightProgression($lastSession['weight'] ?? 0);
        }
        
        // No last session: use strategy's default starting weight
        return $strategy->getDefaultStartingWeight($exercise);
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
        
        // Add instructional message for new users or first-time exercises
        if (!$lastSession) {
            $messages[] = [
                'type' => 'tip',
                'prefix' => 'How to log:',
                'text' => str_replace(':exercise', $program->exercise->title, config('mobile_entry_messages.form_guidance.how_to_log'))
            ];
        }
        
        // Add last session info if available
        if ($lastSession) {
            // Format the resistance/weight info using exercise type strategy
            $strategy = $program->exercise->getTypeStrategy();
            $labels = $strategy->getFieldLabels();
            
            // Create a mock lift log for formatting
            $mockLiftLog = new \App\Models\LiftLog();
            $mockLiftLog->exercise = $program->exercise;
            $mockLiftLog->setRelation('liftSets', collect([
                (object)[
                    'weight' => $lastSession['weight'] ?? 0,
                    'reps' => $lastSession['reps'] ?? 0,
                    'band_color' => $lastSession['band_color'] ?? null
                ]
            ]));
            
            $resistanceText = $strategy->formatWeightDisplay($mockLiftLog);
            
            // Use strategy labels for consistent terminology
            $repsLabel = strtolower(trim($labels['reps'] ?? 'reps', ':'));
            $setsLabel = strtolower(trim($labels['sets'] ?? 'sets', ':'));
            
            // Use strategy to format the message text
            $messageText = $strategy->formatFormMessageDisplay($lastSession);
            
            $messages[] = [
                'type' => 'info',
                'prefix' => str_replace(':date', $lastSession['date'], config('mobile_entry_messages.form_guidance.last_workout')),
                'text' => $messageText
            ];
        }
        
        // Add last session comments if available
        if ($lastSession && !empty($lastSession['comments'])) {
            $messages[] = [
                'type' => 'neutral',
                'prefix' => config('mobile_entry_messages.form_guidance.your_last_notes'),
                'text' => $lastSession['comments']
            ];
        }
        
        // Add program comments if available
        if ($program->comments) {
            $messages[] = [
                'type' => 'tip',
                'prefix' => config('mobile_entry_messages.form_guidance.todays_focus'),
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
                $strategy = $program->exercise->getTypeStrategy();
                $sets = $suggestion->sets ?? $lastSession['sets'] ?? 3;
                
                if (isset($suggestion->band_color)) {
                    // Banded exercise suggestion
                    $messages[] = [
                        'type' => 'tip',
                        'prefix' => config('mobile_entry_messages.form_guidance.try_this'),
                        'text' => $suggestion->band_color . ' band × ' . $suggestion->reps . ' reps × ' . $sets . ' sets' . config('mobile_entry_messages.form_guidance.suggestion_suffix')
                    ];
                } elseif (isset($suggestion->suggestedWeight) && $strategy->getTypeName() !== 'bodyweight') {
                    // Weighted exercise suggestion
                    $messages[] = [
                        'type' => 'tip',
                        'prefix' => config('mobile_entry_messages.form_guidance.try_this'),
                        'text' => $suggestion->suggestedWeight . ' lbs × ' . $suggestion->reps . ' reps × ' . $sets . ' sets' . config('mobile_entry_messages.form_guidance.suggestion_suffix')
                    ];
                } elseif ($strategy->getTypeName() === 'bodyweight' && isset($suggestion->reps)) {
                    // Bodyweight exercise suggestion
                    $messages[] = [
                        'type' => 'tip',
                        'prefix' => config('mobile_entry_messages.form_guidance.try_this'),
                        'text' => $suggestion->reps . ' reps × ' . $sets . ' sets' . config('mobile_entry_messages.form_guidance.suggestion_suffix')
                    ];
                }
            } elseif ($program->exercise->getTypeStrategy()->getTypeName() !== 'bodyweight') {
                // Fallback to simple progression if service fails
                $sets = $lastSession['sets'] ?? 3;
                $reps = $lastSession['reps'] ?? 5;
                $messages[] = [
                    'type' => 'tip',
                    'prefix' => config('mobile_entry_messages.form_guidance.try_this'),
                    'text' => ($lastSession['weight'] + 5) . ' lbs × ' . $reps . ' reps × ' . $sets . ' sets' . config('mobile_entry_messages.form_guidance.suggestion_suffix')
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
        $user = \App\Models\User::find($userId);
        
        $logs = LiftLog::where('user_id', $userId)
            ->whereDate('logged_at', $selectedDate->toDateString())
            ->with(['exercise' => function ($query) use ($userId) {
                $query->with(['aliases' => function ($aliasQuery) use ($userId) {
                    $aliasQuery->where('user_id', $userId);
                }]);
            }, 'liftSets'])
            ->orderBy('logged_at', 'desc')
            ->get();

        // Apply aliases to exercises
        $logs->each(function ($log) use ($user) {
            if ($log->exercise) {
                $displayName = $this->aliasService->getDisplayName($log->exercise, $user);
                $log->exercise->title = $displayName;
            }
        });

        $items = [];
        foreach ($logs as $log) {
            if ($log->liftSets->isEmpty()) {
                continue;
            }

            // Get first set data directly from loaded relationship to avoid additional queries
            $firstSet = $log->liftSets->first();
            $setCount = $log->liftSets->count();
            
            // Generate display text using exercise type strategy
            $strategy = $log->exercise->getTypeStrategy();
            $formattedMessage = $strategy->formatLoggedItemDisplay($log);

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
            $result['emptyMessage'] = config('mobile_entry_messages.empty_states.no_workouts_logged');
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

        // Get cached data for item type determination
        $cachedData = $this->cacheService->getAllCachedData($userId, $selectedDate);
        $recentExerciseIds = $cachedData['recentExerciseIds'];
        $programExerciseIds = $cachedData['programExerciseIds'];

        $items = [];
        
        foreach ($exercises as $exercise) {
            // Determine item type using cached data
            $itemType = $this->cacheService->determineItemType($exercise, $userId, $recentExerciseIds, $programExerciseIds);

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
            'noResultsMessage' => config('mobile_entry_messages.empty_states.no_exercises_found'),
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
                'selectItem' => 'Add this exercise to today\'s workout'
            ],
            'filterPlaceholder' => config('mobile_entry_messages.placeholders.search_exercises')
        ];
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
                'message' => config('mobile_entry_messages.error.exercise_not_found')
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
                'message' => str_replace(':exercise', $exercise->title, config('mobile_entry_messages.error.exercise_already_in_program'))
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
        
        // Clear cache for this user and date since program data changed
        $this->cacheService->clearCacheForUser($userId, $selectedDate);
        
        return [
            'success' => true,
            'message' => ''
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
                'message' => str_replace(':exercise', $exerciseName, config('mobile_entry_messages.error.exercise_already_exists'))
            ];
        }
        
        // Generate unique canonical name
        $canonicalName = $this->generateUniqueCanonicalName($exerciseName, $userId);
        
        // Create the new exercise
        $exercise = Exercise::create([
            'title' => $exerciseName,
            'user_id' => $userId,
            'exercise_type' => 'regular', // Default to regular exercise
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
            'comments' => config('mobile_entry_messages.program_comments.new_exercise')
        ]);
        
        return [
            'success' => true,
            'message' => str_replace(':exercise', $exercise->title, config('mobile_entry_messages.success.exercise_created'))
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
                'message' => config('mobile_entry_messages.error.form_invalid_format')
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
                'message' => config('mobile_entry_messages.error.form_not_found')
            ];
        }
        
        $exerciseTitle = $program->exercise->title ?? 'Exercise';
        $programDate = $program->date;
        $program->delete();
        
        // Clear cache for this user and date since program data changed
        $this->cacheService->clearCacheForUser($userId, $programDate);
        
        return [
            'success' => true,
            'message' => str_replace(':exercise', $exerciseTitle, config('mobile_entry_messages.success.form_removed'))
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
     * Generate contextual help messages based on user's current state
     * 
     * @param int $userId
     * @param Carbon $selectedDate
     * @return array
     */
    public function generateContextualHelpMessages($userId, Carbon $selectedDate)
    {
        $messages = [];
        
        // Check if user has any programs for today
        $programCount = Program::where('user_id', $userId)
            ->whereDate('date', $selectedDate->toDateString())
            ->count();
            
        // Check if user has logged anything today
        $loggedCount = LiftLog::where('user_id', $userId)
            ->whereDate('logged_at', $selectedDate->toDateString())
            ->count();
            
        // Check if user has any incomplete programs
        $incompleteCount = Program::where('user_id', $userId)
            ->whereDate('date', $selectedDate->toDateString())
            ->incomplete()
            ->count();
        
        if ($programCount === 0 && $loggedCount === 0) {
            // First time user or no exercises added yet
            $messages[] = [
                'type' => 'tip',
                'prefix' => 'Getting started:',
                'text' => config('mobile_entry_messages.contextual_help.getting_started')
            ];
        } elseif ($incompleteCount > 0 && $loggedCount === 0) {
            // Has exercises ready but hasn't logged anything
            $plural = $incompleteCount > 1 ? 's' : '';
            $text = str_replace([':count', ':plural'], [$incompleteCount, $plural], config('mobile_entry_messages.contextual_help.ready_to_log'));
            $messages[] = [
                'type' => 'tip',
                'prefix' => 'Ready to log:',
                'text' => $text
            ];
        } elseif ($incompleteCount > 0 && $loggedCount > 0) {
            // Has logged some but has more to do
            $plural = $incompleteCount > 1 ? 's' : '';
            $text = str_replace([':count', ':plural'], [$incompleteCount, $plural], config('mobile_entry_messages.contextual_help.keep_going'));
            $messages[] = [
                'type' => 'info',
                'prefix' => 'Keep going:',
                'text' => $text
            ];
        } elseif ($incompleteCount === 0 && $loggedCount > 0) {
            // All done for today
            $messages[] = [
                'type' => 'success',
                'prefix' => 'Workout complete:',
                'text' => config('mobile_entry_messages.contextual_help.workout_complete')
            ];
        }
        
        return $messages;
    }
}