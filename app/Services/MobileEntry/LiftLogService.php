<?php

namespace App\Services\MobileEntry;

use App\Models\LiftLog;
use App\Models\Exercise;
use App\Models\MobileLiftForm;
use App\Models\User;
use App\Services\TrainingProgressionService;
use App\Services\ExerciseAliasService;
use App\Services\Factories\LiftLogFormFactory;
use App\Services\LiftLogTableRowBuilder;
use App\Services\ComponentBuilder as C;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Services\MobileEntry\MobileEntryBaseService;

class LiftLogService extends MobileEntryBaseService
{
    protected TrainingProgressionService $trainingProgressionService;
    protected LiftDataCacheService $cacheService;
    protected ExerciseAliasService $aliasService;
    protected LiftLogFormFactory $liftLogFormFactory;
    protected LiftLogTableRowBuilder $tableRowBuilder;

    public function __construct(
        TrainingProgressionService $trainingProgressionService,
        LiftDataCacheService $cacheService,
        ExerciseAliasService $aliasService,
        LiftLogFormFactory $liftLogFormFactory,
        LiftLogTableRowBuilder $tableRowBuilder
    ) {
        $this->trainingProgressionService = $trainingProgressionService;
        $this->cacheService = $cacheService;
        $this->aliasService = $aliasService;
        $this->liftLogFormFactory = $liftLogFormFactory;
        $this->tableRowBuilder = $tableRowBuilder;
    }

    /**
     * Generate a standalone form for a specific exercise
     * 
     * @param int $exerciseId The exercise ID
     * @param int $userId The user ID
     * @param Carbon $selectedDate The selected date
     * @param array $redirectParams Optional redirect parameters
     * @return array Form component data
     */
    public function generateStandaloneForm(
        int $exerciseId,
        int $userId,
        Carbon $selectedDate,
        array $redirectParams = []
    ) {
        // Get the exercise
        $exercise = Exercise::where('id', $exerciseId)
            ->availableToUser($userId)
            ->first();
        
        if (!$exercise) {
            throw new \Exception('Exercise not found or not accessible');
        }
        
        // Get user
        $user = User::find($userId);
        
        // Apply alias to exercise title
        $displayName = $this->aliasService->getDisplayName($exercise, $user);
        $exercise->title = $displayName;
        
        // Get last session data
        $lastSession = $this->getLastSessionData($exercise->id, $selectedDate, $userId);
        
        // Get progression suggestion
        $progressionSuggestion = null;
        if ($lastSession) {
            $progressionSuggestion = $this->trainingProgressionService->getSuggestionDetails(
                $userId, 
                $exercise->id
            );
        }
        
        // Determine default weight, reps, and sets based on user preference
        if ($user->shouldPrefillSuggestedValues()) {
            // Use suggested values from progression service
            $defaultWeight = $this->getDefaultWeight($exercise, $lastSession, $userId);
            $defaultReps = $progressionSuggestion->reps ?? ($lastSession['reps'] ?? 5);
            $defaultSets = $progressionSuggestion->sets ?? ($lastSession['sets'] ?? 3);
        } else {
            // Use last workout values only
            $defaultWeight = $lastSession['weight'] ?? $exercise->getTypeStrategy()->getDefaultStartingWeight($exercise);
            $defaultReps = $lastSession['reps'] ?? 5;
            $defaultSets = $lastSession['sets'] ?? 3;
        }
        
        // Create a temporary MobileLiftForm for message generation
        $tempForm = new MobileLiftForm();
        $tempForm->id = 'standalone-' . $exerciseId;
        $tempForm->user_id = $userId;
        $tempForm->exercise_id = $exerciseId;
        $tempForm->setRelation('exercise', $exercise);
        
        // Generate messages based on last session
        $messages = $this->generateFormMessagesForMobileForms($tempForm, $lastSession, $user);
        
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
            $tempForm,
            $exercise,
            $user,
            $defaults,
            $messages,
            $selectedDate,
            $redirectParams
        );
        
        // Override the delete action since this is a standalone form (no MobileLiftForm to delete)
        $formData['data']['deleteAction'] = null;
        
        return $formData;
    }

    /**
     * Generate a complete page with title and form for creating a lift log
     * 
     * @param int $exerciseId The exercise ID
     * @param int $userId The user ID
     * @param Carbon $selectedDate The selected date
     * @param string $backUrl The URL to return to
     * @param array $redirectParams Optional redirect parameters
     * @return array Components array for the page
     */
    public function generateCreatePage(
        int $exerciseId,
        int $userId,
        Carbon $selectedDate,
        string $backUrl,
        array $redirectParams = []
    ) {
        // Generate the form (this will throw exception if exercise not found)
        $formComponent = $this->generateStandaloneForm(
            $exerciseId,
            $userId,
            $selectedDate,
            $redirectParams
        );
        
        // Get the exercise for the title (we know it exists now)
        $exercise = Exercise::where('id', $exerciseId)
            ->availableToUser($userId)
            ->first();
        
        // Get user
        $user = User::find($userId);
        
        // Apply alias to exercise title
        $displayName = $this->aliasService->getDisplayName($exercise, $user);
        
        // Build components array with title and back button
        $components = [];
        
        // Add title with back button
        $components[] = C::title('Log ' . $displayName)
            ->subtitle($selectedDate->format('l, F j, Y'))
            ->backButton('fa-arrow-left', $backUrl, 'Back')
            ->build();
        
        // Add the form
        $components[] = $formComponent;
        
        return $components;
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
        $user = User::find($userId);
        
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
        
        // Add friendly date message for edit forms
        $loggedDate = Carbon::parse($liftLog->logged_at);
        $dateMessage = $this->generateFriendlyDateMessage($loggedDate);
        
        $messages = [
            [
                'type' => 'info',
                'prefix' => 'Date:',
                'text' => $dateMessage
            ]
        ];
        
        // Build the form using the factory, but override some settings for edit mode
        $formComponent = $this->liftLogFormFactory->buildForm(
            $mockForm,
            $liftLog->exercise,
            $user,
            $defaults,
            $messages,
            Carbon::parse($liftLog->logged_at),
            []
        );
        
        // Override form settings for edit mode
        $formComponent['data']['id'] = 'edit-lift-' . $liftLog->id;
        $formComponent['data']['formAction'] = route('lift-logs.update', $liftLog->id);
        $formComponent['data']['method'] = 'PUT';
        $formComponent['data']['deleteAction'] = route('lift-logs.destroy', $liftLog->id);
        // Pass redirect params and exercise_id to delete action
        $formComponent['data']['deleteParams'] = array_merge($redirectParams, ['exercise_id' => $liftLog->exercise_id]);
        
        // Update hidden fields for edit mode
        $formComponent['data']['hiddenFields'] = [
            '_method' => 'PUT',
            'exercise_id' => $liftLog->exercise_id,
            'date' => $liftLog->logged_at->toDateString(),
            'logged_at' => $liftLog->logged_at->format('H:i'),
        ];
        
        // Add redirect parameters if provided
        if (!empty($redirectParams['redirect_to'])) {
            $formComponent['data']['hiddenFields']['redirect_to'] = $redirectParams['redirect_to'];
            // The 'date' field from the lift log will be used for the redirect
        }
        
        // Update button text
        $formComponent['data']['buttons']['submit'] = 'Update ' . $liftLog->exercise->title;
        
        // Return in the component structure expected by flexible view
        return $formComponent;
    }

    /**
     * Generate messages for a form based on mobile lift form and last session
     * 
     * @param MobileLiftForm $form
     * @param array|null $lastSession
     * @param User|null $user
     * @return array
     */
    private function generateFormMessagesForMobileForms($form, $lastSession, $user = null)
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
            $mockLiftLog = new LiftLog();
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
        
        // Add progression suggestion only if user has the preference enabled
        if ($lastSession && $user && $user->shouldPrefillSuggestedValues()) {
            $suggestion = $this->trainingProgressionService->getSuggestionDetails(
                $user->id, 
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
     * @param Exercise $exercise
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
     * Adaptive system that prioritizes exercises based on user experience:
     * 
     * For New Users (< 5 total lift logs):
     * 1. Popular Exercises (Essential beginner-friendly exercises)
     *    - Label: Popular
     *    - Style: 'in-program' (green, prominent)
     *    - Priority: 1
     *    - Curated list of most common beginner exercises
     * 
     * 2. Recent (Last 7 Days)
     *    - Label: Recent
     *    - Style: 'recent' (green, lighter)
     *    - Priority: 2
     *    - Exercises performed in the last 7 days
     * 
     * 3. All Others
     *    - No label or last performed date
     *    - Style: 'regular' (gray)
     *    - Priority: 3
     *    - All remaining exercises, ordered alphabetically
     * 
     * For Experienced Users (≥ 5 total lift logs):
     * 1. Previously Logged Exercises
     *    - Label: Shows last performed date (e.g., "2 days ago")
     *    - Style: 'in-program' (green, prominent)
     *    - Priority: 1
     *    - Exercises with workout history for this user
     * 
     * 2. Never Logged Exercises
     *    - Label: Empty
     *    - Style: 'regular' (gray)
     *    - Priority: 2
     *    - Exercises never performed by this user, ordered alphabetically
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
        $user = User::find($userId);
        $exercises = $this->aliasService->applyAliasesToExercises($exercises, $user);

        // Get exercises already logged today (to exclude from recent list)
        $loggedTodayExerciseIds = LiftLog::where('user_id', $userId)
            ->whereDate('logged_at', $selectedDate->toDateString())
            ->pluck('exercise_id')
            ->unique()
            ->toArray();

        // Get recent exercises (last 7 days, excluding today) for the "Recent" category
        $recentExerciseIds = LiftLog::where('user_id', $userId)
            ->where('logged_at', '>=', $selectedDate->copy()->subDays(7))
            ->where('logged_at', '<', $selectedDate->startOfDay())
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

        // Check if user is new (has fewer than 5 total lift logs)
        $totalLiftLogs = LiftLog::where('user_id', $userId)->count();
        $isNewUser = $totalLiftLogs < 5;

        // Simplified prioritization based on user experience
        $prioritizedExerciseMap = [];
        
        if ($isNewUser) {
            // For new users: show common beginner-friendly exercises at the top
            $prioritizedExerciseMap = $this->getCommonExercisesForNewUsers($exercises);
        }

        $items = [];
        
        foreach ($exercises as $exercise) {
            // Calculate "X ago" label for last performed date
            $lastPerformedLabel = '';
            if (isset($lastPerformedDates[$exercise->id])) {
                $lastPerformed = Carbon::parse($lastPerformedDates[$exercise->id]);
                $lastPerformedLabel = $lastPerformed->diffForHumans(['short' => true]);
            }
            
            // Simplified category system
            if ($isNewUser && isset($prioritizedExerciseMap[$exercise->id])) {
                // Category 1: Popular exercises for new users
                $rank = $prioritizedExerciseMap[$exercise->id];
                $itemType = [
                    'label' => 'Popular',
                    'cssClass' => 'in-program',  // Green, prominent
                    'priority' => 1,
                    'subPriority' => $rank  // Preserve ordering
                ];
            } elseif ($isNewUser && in_array($exercise->id, $recentExerciseIds)) {
                // Category 2: Recent exercises for new users
                $itemType = [
                    'label' => 'Recent',
                    'cssClass' => 'recent',  // Green, lighter
                    'priority' => 2,
                    'subPriority' => 0
                ];
            } elseif (!$isNewUser && isset($lastPerformedDates[$exercise->id])) {
                // Category 1: Exercises with logs for experienced users
                $itemType = [
                    'label' => $lastPerformedLabel,
                    'cssClass' => 'in-program',  // Green, show they have history
                    'priority' => 1,
                    'subPriority' => 0
                ];
            } else {
                // Category 2/3: All others (never logged or no special priority)
                $priority = $isNewUser ? 3 : 2;  // Lower priority for new users, medium for experienced
                $itemType = [
                    'label' => $lastPerformedLabel,
                    'cssClass' => 'regular',  // Gray
                    'priority' => $priority,
                    'subPriority' => 0
                ];
            }
            
            // Determine href based on user preference and exercise history
            if ($user->shouldUseMetricsFirstLoggingFlow()) {
                // Check if this exercise has any logs for this user
                $hasLogs = isset($lastPerformedDates[$exercise->id]);
                
                if ($hasLogs) {
                    // Metrics-first flow: go to exercise logs page first (only if exercise has history)
                    $href = route('exercises.show-logs', [
                        'exercise' => $exercise->id,
                        'from' => 'mobile-entry-lifts',
                        'date' => $selectedDate->toDateString()
                    ]);
                } else {
                    // No history: skip metrics page and go directly to logging form
                    $routeParams = [
                        'exercise_id' => $exercise->id,
                        'redirect_to' => 'mobile-entry-lifts'
                    ];
                    
                    // Only include date if we're NOT viewing today
                    if (!$selectedDate->isToday()) {
                        $routeParams['date'] = $selectedDate->toDateString();
                    }
                    
                    $href = route('lift-logs.create', $routeParams);
                }
            } else {
                // Default flow: go directly to lift log creation
                $routeParams = [
                    'exercise_id' => $exercise->id,
                    'redirect_to' => 'mobile-entry-lifts'
                ];
                
                // Only include date if we're NOT viewing today
                if (!$selectedDate->isToday()) {
                    $routeParams['date'] = $selectedDate->toDateString();
                }
                
                $href = route('lift-logs.create', $routeParams);
            }
            
            $items[] = [
                'id' => 'exercise-' . $exercise->id,
                'name' => $exercise->title,
                'type' => $itemType,
                'href' => $href
            ];
        }

        // Sort items: by priority first, then by subPriority (for popular exercises ranking), then alphabetical by name
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

        // Prepare hidden fields for create form
        $hiddenFields = [];
        // Only include date if we're NOT viewing today
        if (!$selectedDate->isToday()) {
            $hiddenFields['date'] = $selectedDate->toDateString();
        }

        return [
            'noResultsMessage' => config('mobile_entry_messages.empty_states.no_exercises_found'),
            'createForm' => [
                'action' => route('mobile-entry.create-exercise'),
                'method' => 'POST',
                'inputName' => 'exercise_name',
                'submitText' => '+',
                'buttonTextTemplate' => 'Create "{term}"',
                'ariaLabel' => 'Create new exercise',
                'hiddenFields' => $hiddenFields
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
    /**
     * Generate contextual help messages based on user's current state
     * 
     * @param int $userId
     * @param Carbon $selectedDate
     * @return array
     */
    public function generateContextualHelpMessages($userId, Carbon $selectedDate, $expandSelection = false)
    {
        $messages = [];

        // If the selection is expanded, show a specific guiding message.
        if ($expandSelection) {
            $messages[] = [
                'type' => 'info',
                'prefix' => 'Let\'s go!',
                'text' => config('mobile_entry_messages.contextual_help.pick_exercise')
            ];
            return $messages;
        }
        
        // Check if user has logged anything today
        $loggedCount = LiftLog::where('user_id', $userId)
            ->whereDate('logged_at', $selectedDate->toDateString())
            ->count();
        
        if ($loggedCount === 0) {
            // No exercises logged yet
            $messages[] = [
                'type' => 'info',
                'prefix' => 'Getting started:',
                'text' => config('mobile_entry_messages.contextual_help.getting_started')
            ];
        }
        
        return $messages;
    }
    
    /**
     * Generate a friendly date message for display
     * 
     * @param Carbon $date
     * @return string
     */
    private function generateFriendlyDateMessage(Carbon $date): string
    {
        $now = Carbon::now();
        
        if ($date->isToday()) {
            return 'Today';
        }
        
        if ($date->isYesterday()) {
            return 'Yesterday';
        }
        
        if ($date->isTomorrow()) {
            return 'Tomorrow';
        }
        
        $daysDiff = (int) abs($now->diffInDays($date));
        
        if ($daysDiff <= 7 && $date->isPast()) {
            return $daysDiff . ' days ago (' . $date->format('l, M j') . ')';
        }
        
        if ($daysDiff <= 7 && $date->isFuture()) {
            return 'In ' . $daysDiff . ' days (' . $date->format('l, M j') . ')';
        }
        
        // For dates more than a week away
        return $date->format('l, F j, Y');
    }

    /**
     * Get the top 10 most logged exercises by non-admin users for new user prioritization
     * 
     * Returns a map of exercise IDs to their priority ranking (1-10)
     * Only includes exercises that exist in the user's available exercises
     * 
     * @param \Illuminate\Support\Collection $exercises Available exercises for the user
     * @return array Map of exercise_id => priority_rank
     */
    private function getCommonExercisesForNewUsers($exercises): array
    {
        // Get available exercise IDs to filter the query
        $availableExerciseIds = $exercises->pluck('id')->toArray();
        
        if (empty($availableExerciseIds)) {
            return [];
        }
        
        // Find top 10 most logged exercises by non-admin users
        $topExercises = LiftLog::select('exercise_id', \DB::raw('COUNT(*) as log_count'))
            ->whereIn('exercise_id', $availableExerciseIds)
            ->whereHas('user', function ($query) {
                $query->whereDoesntHave('roles', function ($roleQuery) {
                    $roleQuery->where('name', 'Admin');
                });
            })
            ->groupBy('exercise_id')
            ->orderBy('log_count', 'desc')
            ->limit(10)
            ->pluck('exercise_id')
            ->toArray();
        
        // Create priority map (1 = highest priority)
        $priorityMap = [];
        foreach ($topExercises as $index => $exerciseId) {
            $priorityMap[$exerciseId] = $index + 1;
        }
        
        return $priorityMap;
    }
}