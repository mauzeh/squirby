<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\DateTitleService;
use App\Models\Program;
use App\Models\LiftLog;
use Illuminate\Support\Facades\Auth;

class MobileEntryController extends Controller
{
    /**
     * Display the mobile entry interface
     * 
     * Supports date-based navigation through URL parameters:
     * - /mobile-entry (defaults to today)
     * - /mobile-entry?date=2024-01-15 (specific date)
     * 
     * @param Request $request
     * @param DateTitleService $dateTitleService
     * @return \Illuminate\View\View
     */
    public function index(Request $request, DateTitleService $dateTitleService)
    {
        // Get the selected date from request or default to today
        // Supports formats: Y-m-d, Y/m/d, and other Carbon-parseable formats
        $selectedDate = $request->input('date') 
            ? \Carbon\Carbon::parse($request->input('date')) 
            : \Carbon\Carbon::today();
        
        // Calculate navigation dates for prev/next functionality
        $prevDay = $selectedDate->copy()->subDay();
        $nextDay = $selectedDate->copy()->addDay();
        $today = \Carbon\Carbon::today();
        
        // Generate user-friendly date title with main title and optional subtitle
        $dateTitleData = $dateTitleService->generateDateTitle($selectedDate, $today);
        // All text content and data for the view
        $data = [
            /**
             * Date Navigation Configuration
             * 
             * Provides configurable navigation buttons with dynamic URLs based on selected date.
             * Each button contains both display text and href for proper link functionality.
             * 
             * Structure:
             * - prevButton: Links to previous day (selectedDate - 1 day)
             * - todayButton: Links to current date (always today, regardless of selected date)
             * - nextButton: Links to next day (selectedDate + 1 day)
             * 
             * URLs are generated using Laravel's route() helper with date parameters.
             * This allows the mobile entry interface to work with any date-based route pattern.
             * 
             * Usage Examples:
             * - For food logs: route('food-logs.mobile-entry', ['date' => $date])
             * - For lift logs: route('lift-logs.mobile-entry', ['date' => $date])
             * - For generic mobile entry: route('mobile-entry.index', ['date' => $date])
             */
            'navigation' => [
                'prevButton' => [
                    'text' => '← Prev',
                    'href' => route('mobile-entry.index', ['date' => $prevDay->toDateString()])
                ],
                'todayButton' => [
                    'text' => 'Today',
                    'href' => route('mobile-entry.index', ['date' => $today->toDateString()])
                ],
                'nextButton' => [
                    'text' => 'Next →',
                    'href' => route('mobile-entry.index', ['date' => $nextDay->toDateString()])
                ],
                'dateTitle' => $dateTitleData,
                'ariaLabels' => [
                    'navigation' => 'Date navigation',
                    'previousDay' => 'Previous day',
                    'goToToday' => 'Go to today',
                    'nextDay' => 'Next day'
                ]
            ],
            
            // Summary section
            'summary' => [
                'values' => [
                    'total' => 1250,
                    'completed' => 3,
                    'average' => 85,
                    'today' => 12
                ],
                'labels' => [
                    'total' => 'Total',
                    'completed' => 'Completed',
                    'average' => 'Average',
                    'today' => 'Today'
                ],
                'ariaLabels' => [
                    'section' => 'Daily summary'
                ]
            ],
            
            // Add Item button
            'addItemButton' => [
                'text' => 'Add Item',
                'ariaLabel' => 'Add new item'
            ],
            
            // Item selection list (appears when Add Item is clicked)
            'itemSelectionList' => [
                'noResultsMessage' => 'No items found. Hit "+" to save as new item.',
                'createForm' => [
                    'action' => '#', // route('mobile-entry.create-item'), // Configure this route as needed
                    'method' => 'POST',
                    'inputName' => 'item_name',
                    'submitText' => '+',
                    'ariaLabel' => 'Create new item'
                ],
                'items' => [
                    [
                        'id' => 'item-1',
                        'name' => 'Bench Press',
                        'type' => 'highlighted',
                        'href' => '#' // route('mobile-entry.exercise', ['id' => 1]) or similar
                    ],
                    [
                        'id' => 'item-2',
                        'name' => 'Squats',
                        'type' => 'highlighted',
                        'href' => '#' // route('mobile-entry.exercise', ['id' => 2]) or similar
                    ],
                    [
                        'id' => 'item-3',
                        'name' => 'Chicken Breast',
                        'type' => 'regular',
                        'href' => '#' // route('mobile-entry.food', ['id' => 3]) or similar
                    ],
                    [
                        'id' => 'item-4',
                        'name' => 'Brown Rice',
                        'type' => 'regular',
                        'href' => '#' // route('mobile-entry.food', ['id' => 4]) or similar
                    ],
                    [
                        'id' => 'item-5',
                        'name' => 'Body Weight',
                        'type' => 'regular',
                        'href' => '#' // route('mobile-entry.measurement', ['id' => 5]) or similar
                    ],
                    [
                        'id' => 'item-6',
                        'name' => 'Body Fat %',
                        'type' => 'regular',
                        'href' => '#' // route('mobile-entry.measurement', ['id' => 6]) or similar
                    ],
                    [
                        'id' => 'item-7',
                        'name' => 'Deadlift: This is a very long exercise name to test the overflow behavior',
                        'type' => 'highlighted',
                        'href' => '#' // route('mobile-entry.exercise', ['id' => 7]) or similar
                    ]
                ],
                'ariaLabels' => [
                    'section' => 'Item selection list',
                    'selectItem' => 'Select this item to log'
                ],
                'filterPlaceholder' => 'Filter items...'
            ],
            
            /**
             * Forms Configuration
             * 
             * These forms are rendered immediately visible to the user with predefined data.
             * Each form can have different types, values, and configurations.
             * 
             * Structure:
             * - Forms are always visible (not hidden behind item selection)
             * - Each form can have different field configurations
             * - Values are set from the data array
             * - Forms maintain consistent structure
             */
            'forms' => [
                // Example: Quick Exercise Entry
                [
                    'id' => 'quick-exercise-1',
                    'type' => 'exercise',
                    'title' => 'Morning Push-ups',
                    'itemName' => 'Push-ups',
                    'formAction' => '#', // route('lift-logs.store') or similar
                    'deleteAction' => '#', // route('mobile-entry.remove-form', ['id' => 'quick-exercise-1']) or similar
                    'messages' => [
                        [
                            'type' => 'info',
                            'prefix' => 'Last time:',
                            'text' => '3 sets of 20 reps'
                        ],
                        [
                            'type' => 'tip',
                            'prefix' => 'Goal:',
                            'text' => 'Try to increase by 2 reps today'
                        ]
                    ],
                    'numericFields' => [
                        [
                            'id' => 'quick-exercise-1-sets',
                            'name' => 'sets',
                            'label' => 'Sets:',
                            'defaultValue' => 3,
                            'increment' => 1,
                            'min' => 1,
                            'max' => 10,
                            'ariaLabels' => [
                                'decrease' => 'Decrease sets',
                                'increase' => 'Increase sets'
                            ]
                        ],
                        [
                            'id' => 'quick-exercise-1-reps',
                            'name' => 'reps',
                            'label' => 'Reps:',
                            'defaultValue' => 22,
                            'increment' => 1,
                            'min' => 1,
                            'max' => 100,
                            'ariaLabels' => [
                                'decrease' => 'Decrease reps',
                                'increase' => 'Increase reps'
                            ]
                        ]
                    ],
                    'commentField' => [
                        'id' => 'quick-exercise-1-comment',
                        'name' => 'comment',
                        'label' => 'Notes:',
                        'placeholder' => 'How did it feel?',
                        'defaultValue' => 'Feeling strong today!'
                    ],
                    'buttons' => [
                        'decrement' => '-',
                        'increment' => '+',
                        'submit' => 'Log Push-ups'
                    ],
                    'ariaLabels' => [
                        'section' => 'Quick exercise entry',
                        'deleteForm' => 'Remove this form'
                    ]
                ],
                
                // Example: Quick Food Entry
                [
                    'id' => 'quick-food-1',
                    'type' => 'food',
                    'title' => 'Breakfast Protein Shake',
                    'itemName' => 'Protein Shake',
                    'formAction' => '#', // route('food-logs.store') or similar
                    'deleteAction' => '#', // route('mobile-entry.remove-form', ['id' => 'quick-food-1']) or similar
                    'messages' => [
                        [
                            'type' => 'info',
                            'prefix' => 'Calories:',
                            'text' => '~320 per serving'
                        ],
                        [
                            'type' => 'neutral',
                            'prefix' => 'Protein:',
                            'text' => '25g per scoop'
                        ]
                    ],
                    'numericFields' => [
                        [
                            'id' => 'quick-food-1-scoops',
                            'name' => 'scoops',
                            'label' => 'Scoops:',
                            'defaultValue' => 1.5,
                            'increment' => 0.5,
                            'min' => 0.5,
                            'max' => 5,
                            'ariaLabels' => [
                                'decrease' => 'Decrease scoops',
                                'increase' => 'Increase scoops'
                            ]
                        ]
                    ],
                    'commentField' => [
                        'id' => 'quick-food-1-comment',
                        'name' => 'comment',
                        'label' => 'Notes:',
                        'placeholder' => 'Add any notes...',
                        'defaultValue' => 'Post-workout shake with banana'
                    ],
                    'buttons' => [
                        'decrement' => '-',
                        'increment' => '+',
                        'submit' => 'Log Shake'
                    ],
                    'ariaLabels' => [
                        'section' => 'Quick food entry',
                        'deleteForm' => 'Remove this form'
                    ]
                ],
                
                // Example: Quick Measurement Entry
                [
                    'id' => 'quick-measurement-1',
                    'type' => 'measurement',
                    'title' => 'Daily Weigh-in',
                    'itemName' => 'Body Weight',
                    'formAction' => '#', // route('body-logs.store') or similar
                    'deleteAction' => '#', // route('mobile-entry.remove-form', ['id' => 'quick-measurement-1']) or similar
                    'messages' => [
                        [
                            'type' => 'info',
                            'prefix' => 'Yesterday:',
                            'text' => '184.2 lbs'
                        ],
                        [
                            'type' => 'tip',
                            'prefix' => 'Tip:',
                            'text' => 'Weigh yourself at the same time daily'
                        ]
                    ],
                    'numericFields' => [
                        [
                            'id' => 'quick-measurement-1-weight',
                            'name' => 'weight',
                            'label' => 'Weight (lbs):',
                            'defaultValue' => 184.5,
                            'increment' => 0.1,
                            'min' => 50,
                            'max' => 500,
                            'ariaLabels' => [
                                'decrease' => 'Decrease weight',
                                'increase' => 'Increase weight'
                            ]
                        ]
                    ],
                    'commentField' => [
                        'id' => 'quick-measurement-1-comment',
                        'name' => 'comment',
                        'label' => 'Notes:',
                        'placeholder' => 'How are you feeling?',
                        'defaultValue' => ''
                    ],
                    'buttons' => [
                        'decrement' => '-',
                        'increment' => '+',
                        'submit' => 'Log Weight'
                    ],
                    'ariaLabels' => [
                        'section' => 'Quick measurement entry',
                        'deleteForm' => 'Remove this form'
                    ]
                ]
            ],
            
            // Logged items
            'loggedItems' => [
                'emptyMessage' => 'No logged items yet!',
                'title' => 'Item Entry',
                'items' => [
                    [
                        'id' => 1,
                        'value' => 25,
                        'editAction' => '#', // route('mobile-entry.edit-item', ['id' => 1]) or similar
                        'deleteAction' => '#', // route('mobile-entry.delete-item', ['id' => 1]) or similar
                        'message' => [
                            'type' => 'neutral',
                            'prefix' => 'Comment:',
                            'text' => 'Morning workout completed'
                        ],
                        'freeformText' => 'Felt great today! Energy levels were high and form was solid throughout the entire session. Weather was perfect for outdoor training.'
                    ],
                    [
                        'id' => 2,
                        'value' => 15,
                        'editAction' => '#', // route('mobile-entry.edit-item', ['id' => 2]) or similar
                        'deleteAction' => '#', // route('mobile-entry.delete-item', ['id' => 2]) or similar
                        'message' => [
                            'type' => 'neutral',
                            'prefix' => 'Comment:',
                            'text' => 'Quick afternoon session'
                        ]
                        // No freeformText - demonstrates optional field
                    ],
                    [
                        'id' => 3,
                        'value' => 30,
                        'editAction' => '#', // route('mobile-entry.edit-item', ['id' => 3]) or similar
                        'deleteAction' => '#', // route('mobile-entry.delete-item', ['id' => 3]) or similar
                        'message' => [
                            'type' => 'neutral',
                            'prefix' => 'Comment:',
                            'text' => 'Evening cardio and stretching routine'
                        ],
                        'freeformText' => 'Extended session with 20 minutes of cardio followed by full-body stretching. Really helped with recovery from yesterday\'s intense workout.'
                    ]
                ],
                'ariaLabels' => [
                    'section' => 'Logged items',
                    'editItem' => 'Edit logged item',
                    'deleteItem' => 'Delete logged item'
                ]
            ]
        ];

        return view('mobile-entry.index', compact('data'));
    }

    /**
     * Display the lift logging interface
     * 
     * Specialized mobile interface for logging weightlifting exercises.
     * Supports date-based navigation and pre-configured lift forms.
     * 
     * @param Request $request
     * @param DateTitleService $dateTitleService
     * @return \Illuminate\View\View
     */
    public function lifts(Request $request, DateTitleService $dateTitleService)
    {
        // Get the selected date from request or default to today
        $selectedDate = $request->input('date') 
            ? \Carbon\Carbon::parse($request->input('date')) 
            : \Carbon\Carbon::today();
        
        // Calculate navigation dates
        $prevDay = $selectedDate->copy()->subDay();
        $nextDay = $selectedDate->copy()->addDay();
        $today = \Carbon\Carbon::today();
        
        // Generate date title
        $dateTitleData = $dateTitleService->generateDateTitle($selectedDate, $today);
        
        // Get user's programs for the selected date
        $programs = Program::where('user_id', Auth::id())
            ->whereDate('date', $selectedDate->toDateString())
            ->with(['exercise'])
            ->orderBy('priority', 'asc')
            ->get();
        
        // Generate forms based on programs
        $forms = $this->generateProgramForms($programs, $selectedDate);
        
        $data = [
            'navigation' => [
                'prevButton' => [
                    'text' => '← Prev',
                    'href' => route('mobile-entry.lifts', ['date' => $prevDay->toDateString()])
                ],
                'todayButton' => [
                    'text' => 'Today',
                    'href' => route('mobile-entry.lifts', ['date' => $today->toDateString()])
                ],
                'nextButton' => [
                    'text' => 'Next →',
                    'href' => route('mobile-entry.lifts', ['date' => $nextDay->toDateString()])
                ],
                'dateTitle' => $dateTitleData,
                'ariaLabels' => [
                    'navigation' => 'Date navigation',
                    'previousDay' => 'Previous day',
                    'goToToday' => 'Go to today',
                    'nextDay' => 'Next day'
                ]
            ],
            
            'summary' => [
                'values' => [
                    'total' => 8450, // Total weight lifted (lbs)
                    'completed' => 5, // Exercises completed
                    'average' => 92, // Average intensity %
                    'today' => 3 // Sets completed today
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
            ],
            
            'addItemButton' => [
                'text' => 'Add Exercise',
                'ariaLabel' => 'Add new exercise'
            ],
            
            'itemSelectionList' => [
                'noResultsMessage' => 'No exercises found. Hit "+" to save as new exercise.',
                'createForm' => [
                    'action' => route('mobile-entry.create-exercise'),
                    'method' => 'POST',
                    'inputName' => 'exercise_name',
                    'submitText' => '+',
                    'ariaLabel' => 'Create new exercise'
                ],
                'items' => [
                    [
                        'id' => 'exercise-1',
                        'name' => 'Bench Press',
                        'type' => 'highlighted',
                        'href' => route('mobile-entry.add-lift-form', ['exercise' => 'bench-press'])
                    ],
                    [
                        'id' => 'exercise-2',
                        'name' => 'Squat',
                        'type' => 'highlighted',
                        'href' => route('mobile-entry.add-lift-form', ['exercise' => 'squat'])
                    ],
                    [
                        'id' => 'exercise-3',
                        'name' => 'Deadlift',
                        'type' => 'highlighted',
                        'href' => route('mobile-entry.add-lift-form', ['exercise' => 'deadlift'])
                    ],
                    [
                        'id' => 'exercise-4',
                        'name' => 'Overhead Press',
                        'type' => 'regular',
                        'href' => route('mobile-entry.add-lift-form', ['exercise' => 'overhead-press'])
                    ],
                    [
                        'id' => 'exercise-5',
                        'name' => 'Barbell Row',
                        'type' => 'regular',
                        'href' => route('mobile-entry.add-lift-form', ['exercise' => 'barbell-row'])
                    ],
                    [
                        'id' => 'exercise-6',
                        'name' => 'Pull-ups',
                        'type' => 'regular',
                        'href' => route('mobile-entry.add-lift-form', ['exercise' => 'pull-ups'])
                    ],
                    [
                        'id' => 'exercise-7',
                        'name' => 'Incline Dumbbell Press',
                        'type' => 'regular',
                        'href' => route('mobile-entry.add-lift-form', ['exercise' => 'incline-dumbbell-press'])
                    ]
                ],
                'ariaLabels' => [
                    'section' => 'Exercise selection list',
                    'selectItem' => 'Select this exercise to log'
                ],
                'filterPlaceholder' => 'Filter exercises...'
            ],
            
            'forms' => $forms,
            
            'loggedItems' => [
                'emptyMessage' => 'No lifts logged yet today!',
                'title' => 'Today\'s Lifts',
                'items' => [
                    [
                        'id' => 1,
                        'value' => '225×5×3',
                        'editAction' => route('lift-logs.edit', ['lift_log' => 1]),
                        'deleteAction' => route('lift-logs.destroy', ['lift_log' => 1]),
                        'message' => [
                            'type' => 'neutral',
                            'prefix' => 'Bench Press:',
                            'text' => '225 lbs × 5 reps × 3 sets'
                        ],
                        'freeformText' => 'Felt really strong today. Form was solid throughout all sets. Might try 230 next session.'
                    ],
                    [
                        'id' => 2,
                        'value' => '135×8×3',
                        'editAction' => route('lift-logs.edit', ['lift_log' => 2]),
                        'deleteAction' => route('lift-logs.destroy', ['lift_log' => 2]),
                        'message' => [
                            'type' => 'neutral',
                            'prefix' => 'Overhead Press:',
                            'text' => '135 lbs × 8 reps × 3 sets'
                        ],
                        'freeformText' => 'Shoulders felt tight at first but loosened up by set 2. Good lockout on all reps.'
                    ],
                    [
                        'id' => 3,
                        'value' => 'BW×12×3',
                        'editAction' => route('lift-logs.edit', ['lift_log' => 3]),
                        'deleteAction' => route('lift-logs.destroy', ['lift_log' => 3]),
                        'message' => [
                            'type' => 'neutral',
                            'prefix' => 'Pull-ups:',
                            'text' => 'Bodyweight × 12 reps × 3 sets'
                        ]
                    ]
                ],
                'ariaLabels' => [
                    'section' => 'Logged lifts',
                    'editItem' => 'Edit logged lift',
                    'deleteItem' => 'Delete logged lift'
                ]
            ]
        ];

        return view('mobile-entry.index', compact('data'));
    }

    /**
     * Generate forms based on user's programs for the selected date
     * 
     * @param \Illuminate\Database\Eloquent\Collection $programs
     * @param \Carbon\Carbon $selectedDate
     * @return array
     */
    private function generateProgramForms($programs, $selectedDate)
    {
        $forms = [];
        
        foreach ($programs as $program) {
            if (!$program->exercise) {
                continue; // Skip if exercise doesn't exist
            }
            
            $exercise = $program->exercise;
            
            // Get last session data for this exercise
            $lastSession = $this->getLastSessionData($exercise->id, $selectedDate);
            
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
     * @param \Carbon\Carbon $beforeDate
     * @return array|null
     */
    private function getLastSessionData($exerciseId, $beforeDate)
    {
        $lastLog = LiftLog::where('user_id', Auth::id())
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
    private function getDefaultWeight($exercise, $lastSession)
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
    private function generateFormMessages($program, $lastSession)
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

}