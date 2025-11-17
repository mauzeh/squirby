<?php

namespace App\Services\MobileEntry;

use App\Models\MobileLiftForm;
use App\Models\LiftLog;
use App\Models\Exercise;
use App\Services\TrainingProgressionService;
use App\Services\ExerciseAliasService;
use App\Services\Factories\LiftLogFormFactory;
use App\Services\RecommendationEngine;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Services\MobileEntry\MobileEntryBaseService;
use App\Services\ComponentBuilder as C;

class LiftLogService extends MobileEntryBaseService
{
    protected TrainingProgressionService $trainingProgressionService;
    protected LiftDataCacheService $cacheService;
    protected ExerciseAliasService $aliasService;
    protected RecommendationEngine $recommendationEngine;
    protected LiftLogFormFactory $liftLogFormFactory;
    protected \App\Services\LiftLogTableRowBuilder $tableRowBuilder;

    public function __construct(
        TrainingProgressionService $trainingProgressionService,
        LiftDataCacheService $cacheService,
        ExerciseAliasService $aliasService,
        RecommendationEngine $recommendationEngine,
        LiftLogFormFactory $liftLogFormFactory,
        \App\Services\LiftLogTableRowBuilder $tableRowBuilder
    ) {
        $this->trainingProgressionService = $trainingProgressionService;
        $this->cacheService = $cacheService;
        $this->aliasService = $aliasService;
        $this->recommendationEngine = $recommendationEngine;
        $this->liftLogFormFactory = $liftLogFormFactory;
        $this->tableRowBuilder = $tableRowBuilder;
    }

    /**
     * Generate forms based on user's mobile lift forms for the selected date
     * 
     * @param int $userId
     * @param Carbon $selectedDate
     * @param array $redirectParams Optional redirect parameters to pass through forms
     * @return array
     */
    public function generateForms($userId, Carbon $selectedDate, array $redirectParams = [])
    {
        // Get user to check preferences
        $user = \App\Models\User::find($userId);
        
        // Get mobile lift forms with exercise relationship
        $mobileForms = MobileLiftForm::with(['exercise'])
            ->forUserAndDate($userId, $selectedDate)
            ->get();
        
        if ($mobileForms->isEmpty()) {
            return [];
        }
        
        // Apply aliases to form exercises
        $mobileForms->each(function ($form) use ($user) {
            if ($form->exercise) {
                $displayName = $this->aliasService->getDisplayName($form->exercise, $user);
                $form->exercise->title = $displayName;
            }
        });
        
        // Get all exercise IDs for batch queries
        $exerciseIds = $mobileForms->pluck('exercise.id')->filter()->unique()->toArray();
        
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
        
        foreach ($mobileForms as $form) {
            if (!$form->exercise) {
                continue; // Skip if exercise doesn't exist
            }
            
            $exercise = $form->exercise;
            
            // Get last session data from cached results
            $lastSession = $lastSessionsData[$exercise->id] ?? null;
            
            // Get progression suggestions from batch results
            $progressionSuggestion = $progressionSuggestions[$exercise->id] ?? null;
            
            // Determine default weight based on last session or exercise type
            $defaultWeight = $this->getDefaultWeight($exercise, $lastSession, $userId);
            
            // Determine default reps and sets from progression service or fallback
            $defaultReps = $progressionSuggestion->reps ?? ($lastSession['reps'] ?? 5);
            $defaultSets = $progressionSuggestion->sets ?? ($lastSession['sets'] ?? 3);
            
            // Generate messages based on last session
            $messages = $this->generateFormMessagesForMobileForms($form, $lastSession, $userId);
            
            // Prepare default values for the factory
            $defaults = [
                'weight' => $defaultWeight,
                'reps' => $defaultReps,
                'sets' => $defaultSets,
                'band_color' => $lastSession['band_color'] ?? 'red',
                'comments' => '',
            ];

            // Use the factory to build the complete form
            $formData = $this->liftLogFormFactory->buildForm(
                $form,
                $exercise,
                $user,
                $defaults,
                $messages,
                $selectedDate,
                $redirectParams
            );
            
            $forms[] = $formData;
        }
        
        return $forms;
    }

    /**
     * Generate an edit form component for an existing lift log
     * 
     * @param LiftLog $liftLog The lift log to edit
     * @param int $userId The user ID
     * @param array $redirectParams Optional redirect parameters (redirect_to, date, etc.)
     * @return array Form component data
     */
    public function generateEditFormComponent(LiftLog $liftLog, $userId, array $redirectParams = [])
    {
        // Load necessary relationships
        $liftLog->load(['exercise', 'liftSets']);
        
        // Get user
        $user = \App\Models\User::find($userId);
        
        // Apply alias to exercise title
        if ($liftLog->exercise) {
            $displayName = $this->aliasService->getDisplayName($liftLog->exercise, $user);
            $liftLog->exercise->title = $displayName;
        }
        
        // Extract data from the lift log
        $firstSet = $liftLog->liftSets->first();
        
        // Prepare defaults from existing lift log data
        $defaults = [
            'weight' => $firstSet->weight ?? 0,
            'reps' => $firstSet->reps ?? 0,
            'sets' => $liftLog->liftSets->count(),
            'band_color' => $firstSet->band_color ?? 'red',
            'comments' => $liftLog->comments ?? '',
        ];
        
        // Create a mock MobileLiftForm for the factory (it needs this for form ID generation)
        $mockForm = new MobileLiftForm();
        $mockForm->id = 'edit-' . $liftLog->id;
        $mockForm->user_id = $userId;
        $mockForm->exercise_id = $liftLog->exercise_id;
        
        // No messages for edit forms (user is editing existing data)
        $messages = [];
        
        // Build the form using the factory, but override some settings for edit mode
        $formData = $this->liftLogFormFactory->buildForm(
            $mockForm,
            $liftLog->exercise,
            $user,
            $defaults,
            $messages,
            Carbon::parse($liftLog->logged_at),
            []
        );
        
        // Override form settings for edit mode
        $formData['id'] = 'edit-lift-' . $liftLog->id;
        $formData['formAction'] = route('lift-logs.update', $liftLog->id);
        $formData['method'] = 'PUT';
        $formData['deleteAction'] = route('lift-logs.destroy', $liftLog->id);
        $formData['deleteParams'] = $redirectParams; // Pass redirect params to delete action
        
        // Update hidden fields for edit mode
        $formData['hiddenFields'] = [
            '_method' => 'PUT',
            'exercise_id' => $liftLog->exercise_id,
            'date' => $liftLog->logged_at->toDateString(),
            'logged_at' => $liftLog->logged_at->format('H:i'),
        ];
        
        // Add redirect parameters if provided
        if (!empty($redirectParams['redirect_to'])) {
            $formData['hiddenFields']['redirect_to'] = $redirectParams['redirect_to'];
            // The 'date' field from the lift log will be used for the redirect
        }
        
        // Update button text
        $formData['buttons']['submit'] = 'Update ' . $liftLog->exercise->title;
        
        // Return in the component structure expected by flexible view
        return ['type' => 'form', 'data' => $formData];
    }

    /**
     * Generate messages for a form based on mobile lift form and last session
     * 
     * @param \App\Models\MobileLiftForm $form
     * @param array|null $lastSession
     * @param int|null $userId
     * @return array
     */
    private function generateFormMessagesForMobileForms($form, $lastSession, $userId = null)
    {
        $messages = [];
        
        // Add instructional message for new users or first-time exercises
        if (!$lastSession) {
            $messages[] = [
                'type' => 'tip',
                'prefix' => 'How to log:',
                'text' => str_replace(':exercise', $form->exercise->title, config('mobile_entry_messages.form_guidance.how_to_log'))
            ];
        }
        
        // Add last session info if available
        if ($lastSession) {
            // Format the resistance/weight info using exercise type strategy
            $strategy = $form->exercise->getTypeStrategy();
            $labels = $strategy->getFieldLabels();
            
            // Create a mock lift log for formatting
            $mockLiftLog = new \App\Models\LiftLog();
            $mockLiftLog->exercise = $form->exercise;
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
        
        // Add progression suggestion
        if ($lastSession && $userId) {
            $suggestion = $this->trainingProgressionService->getSuggestionDetails(
                $userId, 
                $form->exercise_id
            );
            
            if ($suggestion) {
                $strategy = $form->exercise->getTypeStrategy();
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
            } elseif ($form->exercise->getTypeStrategy()->getTypeName() !== 'bodyweight') {
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
            ->with(['exercise' => function ($query) use ($userId) {
                $query->with(['aliases' => function ($aliasQuery) use ($userId) {
                    $aliasQuery->where('user_id', $userId);
                }]);
            }, 'liftSets'])
            ->orderBy('logged_at', 'desc')
            ->get();

        // Build table rows using shared service
        $rows = $this->tableRowBuilder->buildRows($logs, [
            'showDateBadge' => false, // Don't show date badge on mobile-entry (same day)
            'showCheckbox' => false,
            'showViewLogsAction' => true, // Show view logs action
            'showDeleteAction' => true, // Show delete button on mobile-entry
            'wrapActions' => false, // Keep all 3 buttons on same line
            'includeEncouragingMessage' => true, // Show encouraging messages
            'redirectContext' => 'mobile-entry-lifts',
            'selectedDate' => $selectedDate->toDateString(),
        ]);

        $tableBuilder = C::table()
            ->rows($rows)
            ->emptyMessage(config('mobile_entry_messages.empty_states.no_workouts_logged'))
            ->confirmMessage('deleteItem', 'Are you sure you want to delete this lift log entry? This action cannot be undone.')
            ->ariaLabel('Logged workouts')
            ->spacedRows();

        return $tableBuilder->build();
    }  
  /**
     * Generate item selection list based on user's accessible exercises
     * 
     * Simplified 3-category system for better mobile UX:
     * 
     * 1. Recommended (Top 10 AI Recommendations)
     *    - Label: <i class="fas fa-star"></i> Recommended
     *    - Style: 'in-program' (green, prominent)
     *    - Priority: 1
     *    - Ordered by recommendation engine score (highest score first)
     *    - Based on muscle balance, movement diversity, recovery, and training history
     *    - Only shows exercises performed in last 31 days
     * 
     * 2. Recent (Last 7 Days)
     *    - Label: Recent
     *    - Style: 'recent' (green, lighter)
     *    - Priority: 2
     *    - Exercises performed in the last 7 days (not already in top 10 recommendations)
     *    - Ordered alphabetically
     * 
     * 3. All Others
     *    - No label
     *    - Style: 'regular' (gray)
     *    - Priority: 3
     *    - All remaining exercises (custom and regular)
     *    - Ordered alphabetically
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

        // Get exercises already in today's mobile lift forms (to exclude from selection list)
        $formExerciseIds = MobileLiftForm::where('user_id', $userId)
            ->whereDate('date', $selectedDate->toDateString())
            ->pluck('exercise_id')
            ->toArray();

        // Get exercises already logged today (to exclude from recent list)
        $loggedTodayExerciseIds = LiftLog::where('user_id', $userId)
            ->whereDate('logged_at', $selectedDate->toDateString())
            ->pluck('exercise_id')
            ->unique()
            ->toArray();

        // Get recent exercises (last 7 days, excluding today) for the "Recent" category
        $recentExerciseIds = LiftLog::where('user_id', $userId)
            ->where('logged_at', '>=', Carbon::now()->subDays(7))
            ->whereNotIn('exercise_id', $loggedTodayExerciseIds)
            ->pluck('exercise_id')
            ->unique()
            ->toArray();

        // Get last performed dates for all exercises in a single query
        $lastPerformedDates = LiftLog::where('user_id', $userId)
            ->whereIn('exercise_id', $exercises->pluck('id'))
            ->select('exercise_id', \DB::raw('MAX(logged_at) as last_logged_at'))
            ->groupBy('exercise_id')
            ->pluck('last_logged_at', 'exercise_id');

        // Get top 10 recommended exercises using the recommendation engine
        $recommendations = $this->recommendationEngine->getRecommendations($userId, 10);
        
        // Create a map of exercise IDs to their recommendation rankings
        $recommendationMap = [];
        foreach ($recommendations as $index => $recommendation) {
            $exerciseId = $recommendation['exercise']->id;
            $recommendationMap[$exerciseId] = $index + 1;  // Rank 1 is highest, rank 10 is lowest
        }

        $items = [];
        
        foreach ($exercises as $exercise) {
            // Skip exercises that are already in today's mobile lift forms
            if (in_array($exercise->id, $formExerciseIds)) {
                continue;
            }
            
            // Calculate "X ago" label for last performed date
            $lastPerformedLabel = '';
            if (isset($lastPerformedDates[$exercise->id])) {
                $lastPerformed = Carbon::parse($lastPerformedDates[$exercise->id]);
                $lastPerformedLabel = $lastPerformed->diffForHumans(['short' => true]);
            }
            
            // Simplified 3-category system
            if (isset($recommendationMap[$exercise->id])) {
                // Category 1: Recommended (Top 10 from AI)
                $rank = $recommendationMap[$exercise->id];
                $itemType = [
                    'label' => '<i class="fas fa-star"></i> Recommended',
                    'cssClass' => 'in-program',  // Green, prominent
                    'priority' => 1,
                    'subPriority' => $rank  // Preserve recommendation engine order
                ];
            } elseif (in_array($exercise->id, $recentExerciseIds)) {
                // Category 2: Recent (Last 7 days, not in top 10)
                $itemType = [
                    'label' => 'Recent',
                    'cssClass' => 'recent',  // Green, lighter
                    'priority' => 2,
                    'subPriority' => 0
                ];
            } else {
                // Category 3: All Others (show last performed date)
                $itemType = [
                    'label' => $lastPerformedLabel,
                    'cssClass' => 'regular',  // Gray
                    'priority' => 3,
                    'subPriority' => 0
                ];
            }

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

        // Sort items: by priority first, then by subPriority (for recommendations), then alphabetical by name
        usort($items, function ($a, $b) {
            // First sort by priority (lower number = higher priority)
            $priorityComparison = $a['type']['priority'] <=> $b['type']['priority'];
            if ($priorityComparison !== 0) {
                return $priorityComparison;
            }
            
            // If same priority, sort by subPriority (for recommendations to maintain engine order)
            $subPriorityA = $a['type']['subPriority'] ?? 0;
            $subPriorityB = $b['type']['subPriority'] ?? 0;
            $subPriorityComparison = $subPriorityA <=> $subPriorityB;
            if ($subPriorityComparison !== 0) {
                return $subPriorityComparison;
            }
            
            // If same priority and subPriority, sort alphabetically by name
            return strcmp($a['name'], $b['name']);
        });

        return [
            'noResultsMessage' => config('mobile_entry_messages.empty_states.no_exercises_found'),
            'createForm' => [
                'action' => route('mobile-entry.create-exercise'),
                'method' => 'POST',
                'inputName' => 'exercise_name',
                'submitText' => '+',
                'buttonTextTemplate' => 'Create "{term}"',
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
     * Add an exercise form by finding the exercise and creating a mobile lift form entry
     * 
     * @param int $userId
     * @param string $exerciseIdentifier Exercise canonical name or ID
     * @param Carbon $selectedDate
     * @return array Result with success/error status and message
     */
    public function addExerciseForm($userId, $exerciseIdentifier, Carbon $selectedDate)
    {
        // Find the exercise by canonical name or ID
        // Use a closure to properly scope the OR condition
        $exercise = Exercise::where(function ($query) use ($exerciseIdentifier) {
                $query->where('canonical_name', $exerciseIdentifier);
                // Only check ID if the identifier is numeric to avoid type coercion issues
                if (is_numeric($exerciseIdentifier)) {
                    $query->orWhere('id', $exerciseIdentifier);
                }
            })
            ->availableToUser($userId)
            ->first();
        
        if (!$exercise) {
            return [
                'success' => false,
                'message' => config('mobile_entry_messages.error.exercise_not_found')
            ];
        }
        
        // Check if mobile lift form already exists
        $existingForm = MobileLiftForm::where('user_id', $userId)
            ->where('exercise_id', $exercise->id)
            ->whereDate('date', $selectedDate->toDateString())
            ->first();
        
        if ($existingForm) {
            return [
                'success' => false,
                'message' => str_replace(':exercise', $exercise->title, config('mobile_entry_messages.error.exercise_already_in_program'))
            ];
        }
        
        // Create a mobile lift form entry for this exercise
        MobileLiftForm::create([
            'user_id' => $userId,
            'exercise_id' => $exercise->id,
            'date' => $selectedDate
        ]);
        
        return [
            'success' => true,
            'message' => str_replace(':exercise', $exercise->title, config('mobile_entry_messages.success.exercise_added'))
        ];
    }

    /**
     * Create a new exercise and add it to mobile lift forms
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
        
        // Create a mobile lift form entry for this exercise
        MobileLiftForm::create([
            'user_id' => $userId,
            'exercise_id' => $exercise->id,
            'date' => $selectedDate
        ]);
        
        return [
            'success' => true,
            'message' => str_replace(':exercise', $exercise->title, config('mobile_entry_messages.success.exercise_created'))
        ];
    }

    /**
     * Remove a mobile lift form entry from the interface
     * 
     * @param int $userId
     * @param string $formId Form ID (format: lift-{id})
     * @return array Result with success/error status and message
     */
    public function removeForm($userId, $formId)
    {
        // Extract mobile lift form ID from form ID (format: lift-{id})
        if (!str_starts_with($formId, 'lift-')) {
            return [
                'success' => false,
                'message' => config('mobile_entry_messages.error.form_invalid_format')
            ];
        }
        
        $liftFormId = str_replace('lift-', '', $formId);
        
        $form = MobileLiftForm::where('id', $liftFormId)
            ->where('user_id', $userId)
            ->with('exercise')
            ->first();
        
        if (!$form) {
            return [
                'success' => false,
                'message' => config('mobile_entry_messages.error.form_not_found')
            ];
        }
        
        $exerciseTitle = $form->exercise->title ?? 'Exercise';
        $form->delete();
        
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
        
        // Check if user has any mobile lift forms for today
        $formCount = MobileLiftForm::where('user_id', $userId)
            ->whereDate('date', $selectedDate->toDateString())
            ->count();
            
        // Check if user has logged anything today
        $loggedCount = LiftLog::where('user_id', $userId)
            ->whereDate('logged_at', $selectedDate->toDateString())
            ->count();
            
        // Check if user has forms but hasn't logged them all yet
        $incompleteCount = max(0, $formCount - $loggedCount);
        
        if ($formCount === 0 && $loggedCount === 0) {
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