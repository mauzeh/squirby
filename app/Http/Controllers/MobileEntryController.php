<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\DateTitleService;
use App\Services\MobileEntry\LiftLogService;
use App\Services\MobileEntry\FoodLogService;
use Illuminate\Support\Facades\Auth;

class MobileEntryController extends Controller
{
    /**
     * Display the lift logging interface
     * 
     * Specialized mobile interface for logging weightlifting exercises.
     * Supports date-based navigation and pre-configured lift forms.
     * 
     * @param Request $request
     * @antml:parameter name="DateTitleService $dateTitleService
     * @return \Illuminate\View\View
     */
    public function lifts(Request $request, DateTitleService $dateTitleService, LiftLogService $formService)
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
        
        // Get session messages for display
        $sessionMessages = [
            'success' => session('success'),
            'error' => session('error'),
            'warning' => session('warning'),
            'info' => session('info') ?: $request->input('completion_info')
        ];
        
        // Add validation errors if they exist
        if ($errors = session('errors')) {
            $errorMessages = $errors->all();
            if (!empty($errorMessages)) {
                $sessionMessages['error'] = implode(' ', $errorMessages);
            }
        }
        
        // Generate logged items using the service
        $loggedItems = $formService->generateLoggedItems(Auth::id(), $selectedDate);
        
        // Generate item selection list using the service
        $itemSelectionList = $formService->generateItemSelectionList(Auth::id(), $selectedDate);
        
        // Generate interface messages
        $interfaceMessages = $formService->generateInterfaceMessages($sessionMessages);
        
        // Check if there are any logs for today
        $hasLogsToday = \App\Models\LiftLog::where('user_id', Auth::id())
            ->whereDate('logged_at', $selectedDate->toDateString())
            ->exists();
        
        // Check if user wants to expand selection manually
        $shouldExpandSelection = $request->boolean('expand_selection');
        
        // Always add contextual help messages
        $contextualMessages = $formService->generateContextualHelpMessages(Auth::id(), $selectedDate, $shouldExpandSelection);
        if (!empty($contextualMessages)) {
            $interfaceMessages['messages'] = array_merge($interfaceMessages['messages'] ?? [], $contextualMessages);
            $interfaceMessages['hasMessages'] = true;
            $interfaceMessages['messageCount'] = count($interfaceMessages['messages']);
        }
        
        // Build components array using ComponentBuilder
        $components = [];
        
        // Navigation - disable "Next" button when viewing today or future dates
        $components[] = \App\Services\ComponentBuilder::navigation()
            ->prev('← Prev', route('mobile-entry.lifts', ['date' => $prevDay->toDateString()]))
            ->center('Today', route('mobile-entry.lifts', ['date' => $today->toDateString()]))
            ->next('Next →', route('mobile-entry.lifts', ['date' => $nextDay->toDateString()]), $selectedDate->lt($today))
            ->ariaLabel('Date navigation')
            ->build();
        
        // Title
        $components[] = \App\Services\ComponentBuilder::title(
            $dateTitleData['main'],
            $dateTitleData['subtitle'] ?? null
        )->build();
        
        // Interface messages
        if ($interfaceMessages['hasMessages']) {
            $messagesBuilder = \App\Services\ComponentBuilder::messages();
            foreach ($interfaceMessages['messages'] as $message) {
                $messagesBuilder->add($message['type'], $message['text'], $message['prefix'] ?? null);
            }
            $components[] = $messagesBuilder->build();
        }
        
        // Summary (if exists)
        $summary = $formService->generateSummary(Auth::id(), $selectedDate);
        if ($summary) {
            $summaryBuilder = \App\Services\ComponentBuilder::summary();
            foreach ($summary['values'] as $key => $value) {
                $summaryBuilder->item($key, $value, $summary['labels'][$key] ?? null);
            }
            $components[] = $summaryBuilder->build();
        }
        
        // Add Lift button (hide if selection is expanded)
        if (!$shouldExpandSelection) {
            $components[] = \App\Services\ComponentBuilder::button('Log Now')
                ->ariaLabel('Add new exercise')
                ->addClass('btn-add-item')
                ->addClass('btn-log-now')
                ->icon('fa-plus')
                ->build();
        }
        
        // Item selection list
        $itemListBuilder = \App\Services\ComponentBuilder::itemList()
            ->filterPlaceholder($itemSelectionList['filterPlaceholder'])
            ->noResultsMessage($itemSelectionList['noResultsMessage']);

        if ($shouldExpandSelection) {
            $itemListBuilder->initialState('expanded')->showCancelButton(false);
        }
        
        foreach ($itemSelectionList['items'] as $item) {
            $itemListBuilder->item(
                $item['id'],
                $item['name'],
                $item['href'],
                $item['type']['label'],
                $item['type']['cssClass'],
                $item['type']['priority']
            );
        }
        
        if (isset($itemSelectionList['createForm'])) {
            $itemListBuilder->createForm(
                $itemSelectionList['createForm']['action'],
                $itemSelectionList['createForm']['inputName'],
                $itemSelectionList['createForm']['hiddenFields'],
                $itemSelectionList['createForm']['buttonTextTemplate'] ?? 'Create "{term}"',
                $itemSelectionList['createForm']['method'] ?? 'POST'
            );
        }
        
        $components[] = $itemListBuilder->build();
        
        // Logged items (now using table component with full component data)
        $components[] = $loggedItems;
        
        // Check if all logged items today are PRs (for celebration)
        $todaysLogs = \App\Models\LiftLog::where('user_id', Auth::id())
            ->whereDate('logged_at', $selectedDate->toDateString())
            ->with(['exercise', 'liftSets'])
            ->get();
        
        $allPRs = false;
        if ($todaysLogs->isNotEmpty()) {
            // Get all exercise IDs from today
            $exerciseIds = $todaysLogs->pluck('exercise_id')->unique();
            
            // Get all historical logs for PR calculation
            $allLogs = \App\Models\LiftLog::where('user_id', Auth::id())
                ->whereIn('exercise_id', $exerciseIds)
                ->with(['exercise', 'liftSets'])
                ->orderBy('logged_at', 'asc')
                ->get();
            
            // Calculate PR log IDs
            $tableRowBuilder = app(\App\Services\LiftLogTableRowBuilder::class);
            $reflection = new \ReflectionClass($tableRowBuilder);
            $method = $reflection->getMethod('calculatePRLogIds');
            $method->setAccessible(true);
            $prLogIds = $method->invoke($tableRowBuilder, $allLogs);
            
            // Check if all today's logs are PRs
            $todaysLogIds = $todaysLogs->pluck('id')->toArray();
            $allPRs = !empty($todaysLogIds) && count(array_intersect($todaysLogIds, $prLogIds)) === count($todaysLogIds);
        }
        
        $data = [
            'components' => $components,
            'autoscroll' => true,
            'all_prs' => $allPRs
        ];

        return view('mobile-entry.flexible', compact('data'));
    }

    /**
     * Create a new exercise from the mobile interface and redirect to lift log creation
     * 
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function createExercise(Request $request)
    {
        $request->validate([
            'exercise_name' => 'required|string|max:255',
            'date' => 'nullable|date'
        ]);
        
        $selectedDate = $request->input('date') 
            ? \Carbon\Carbon::parse($request->input('date')) 
            : \Carbon\Carbon::today();
        
        // Check if exercise with similar name already exists
        $existingExercise = \App\Models\Exercise::where('title', $request->input('exercise_name'))
            ->availableToUser(Auth::id())
            ->first();
        
        if ($existingExercise) {
            // Exercise exists, redirect to create lift log for it
            $routeParams = [
                'exercise_id' => $existingExercise->id,
                'redirect_to' => 'mobile-entry-lifts'
            ];
            
            // Only include date if we're NOT viewing today
            if (!$selectedDate->isToday()) {
                $routeParams['date'] = $selectedDate->toDateString();
            }
            
            return redirect()->route('lift-logs.create', $routeParams);
        }
        
        // Generate unique canonical name
        $canonicalName = $this->generateUniqueCanonicalName($request->input('exercise_name'), Auth::id());
        
        // Create the new exercise
        $exercise = \App\Models\Exercise::create([
            'title' => $request->input('exercise_name'),
            'user_id' => Auth::id(),
            'exercise_type' => 'regular',
            'canonical_name' => $canonicalName
        ]);
        
        // Redirect to lift log creation page
        $routeParams = [
            'exercise_id' => $exercise->id,
            'redirect_to' => 'mobile-entry-lifts'
        ];
        
        // Only include date if we're NOT viewing today
        if (!$selectedDate->isToday()) {
            $routeParams['date'] = $selectedDate->toDateString();
        }
        
        return redirect()->route('lift-logs.create', $routeParams)
            ->with('success', 'Exercise "' . $exercise->title . '" created! Now log your first set.');
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
        return \App\Models\Exercise::where('canonical_name', $canonicalName)
            ->availableToUser($userId)
            ->exists();
    }

    /**
     * Display the food logging interface
     * 
     * Specialized mobile interface for logging food intake.
     * Supports date-based navigation and quick food entry forms.
     * 
     * @param Request $request
     * @param DateTitleService $dateTitleService
     * @param FoodLogService $formService
     * @return \Illuminate\View\View
     */
    public function foods(Request $request, DateTitleService $dateTitleService, FoodLogService $formService)
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
        
        // Get session messages for display
        $sessionMessages = [
            'success' => session('success'),
            'error' => session('error'),
            'warning' => session('warning'),
            'info' => session('info')
        ];
        
        // Add validation errors if they exist
        if ($errors = session('errors')) {
            $errorMessages = $errors->all();
            if (!empty($errorMessages)) {
                $sessionMessages['error'] = implode(' ', $errorMessages);
            }
        }
        
        // Clean up old forms to prevent database bloat (deprecated - will be removed)
        $formService->cleanupOldForms(Auth::id(), $selectedDate);
        
        // No forms generated - users navigate directly to create forms like lifts
        
        // Generate logged items using the service
        $loggedItems = $formService->generateLoggedItems(Auth::id(), $selectedDate);
        
        // Generate item selection list using the service
        $itemSelectionList = $formService->generateItemSelectionList(Auth::id(), $selectedDate);
        
        // Generate interface messages
        $interfaceMessages = $formService->generateInterfaceMessages($sessionMessages);
        
        // Add contextual help messages if no session messages exist
        if (!$interfaceMessages['hasMessages']) {
            $contextualMessages = $formService->generateContextualHelpMessages(Auth::id(), $selectedDate);
            if (!empty($contextualMessages)) {
                $interfaceMessages = [
                    'messages' => $contextualMessages,
                    'hasMessages' => true,
                    'messageCount' => count($contextualMessages)
                ];
            }
        }
        
        // Build components array using ComponentBuilder
        $components = [];
        
        // Navigation - disable "Next" button when viewing today or future dates
        $components[] = \App\Services\ComponentBuilder::navigation()
            ->prev('← Prev', route('mobile-entry.foods', ['date' => $prevDay->toDateString()]))
            ->center('Today', route('mobile-entry.foods', ['date' => $today->toDateString()]))
            ->next('Next →', route('mobile-entry.foods', ['date' => $nextDay->toDateString()]), $selectedDate->lt($today))
            ->ariaLabel('Date navigation')
            ->build();
        
        // Title
        $components[] = \App\Services\ComponentBuilder::title(
            $dateTitleData['main'],
            $dateTitleData['subtitle'] ?? null
        )->build();
        
        // Interface messages
        if ($interfaceMessages['hasMessages']) {
            $messagesBuilder = \App\Services\ComponentBuilder::messages();
            foreach ($interfaceMessages['messages'] as $message) {
                $messagesBuilder->add($message['type'], $message['text'], $message['prefix'] ?? null);
            }
            $components[] = $messagesBuilder->build();
        }
        
        // Summary (if exists)
        $summary = $formService->generateSummary(Auth::id(), $selectedDate);
        if ($summary) {
            $summaryBuilder = \App\Services\ComponentBuilder::summary();
            foreach ($summary['values'] as $key => $value) {
                $summaryBuilder->item($key, $value, $summary['labels'][$key] ?? null);
            }
            $components[] = $summaryBuilder->build();
        }
        
        // Add Food button
        $components[] = \App\Services\ComponentBuilder::button('Log Now')
            ->ariaLabel('Add new food item')
            ->addClass('btn-add-item')
            ->addClass('btn-log-now')
            ->icon('fa-plus')
            ->build();
        
        // Item selection list
        $itemListBuilder = \App\Services\ComponentBuilder::itemList()
            ->filterPlaceholder($itemSelectionList['filterPlaceholder'])
            ->noResultsMessage($itemSelectionList['noResultsMessage']);
        
        foreach ($itemSelectionList['items'] as $item) {
            $itemListBuilder->item(
                $item['id'],
                $item['name'],
                $item['href'],
                $item['type']['label'],
                $item['type']['cssClass'],
                $item['type']['priority']
            );
        }
        
        if (isset($itemSelectionList['createForm'])) {
            $itemListBuilder->createForm(
                $itemSelectionList['createForm']['action'],
                $itemSelectionList['createForm']['inputName'],
                $itemSelectionList['createForm']['hiddenFields'],
                $itemSelectionList['createForm']['buttonTextTemplate'] ?? 'Create "{term}"',
                $itemSelectionList['createForm']['method'] ?? 'POST'
            );
        }
        
        $components[] = $itemListBuilder->build();
        
        // No forms section - users navigate directly to create forms
        
        // Logged items
        $components[] = $loggedItems;
        
        $data = [
            'components' => $components,
            'autoscroll' => true
        ];

        return view('mobile-entry.flexible', compact('data'));
    }

    /**
     * Create a new ingredient from the mobile interface
     * 
     * @param Request $request
     * @param FoodLogService $formService
     * @return \Illuminate\Http\RedirectResponse
     */
    public function createIngredient(Request $request, FoodLogService $formService)
    {
        $request->validate([
            'ingredient_name' => 'required|string|max:255',
            'date' => 'nullable|date'
        ]);
        
        $selectedDate = $request->input('date') 
            ? \Carbon\Carbon::parse($request->input('date')) 
            : \Carbon\Carbon::today();
        
        $result = $formService->createIngredient(Auth::id(), $request->input('ingredient_name'), $selectedDate);
        
        $messageType = $result['success'] ? 'success' : 'error';
        
        return redirect()->route('mobile-entry.foods', ['date' => $selectedDate->toDateString()])
            ->with($messageType, $result['message']);
    }

    /**
     * Display the measurements logging interface
     * 
     * Specialized mobile interface for logging body measurements.
     * Shows all measurement types as forms (no add/remove functionality).
     * 
     * @param Request $request
     * @param DateTitleService $dateTitleService
     * @param \App\Services\MobileEntry\BodyLogService $formService
     * @return \Illuminate\View\View
     */
    public function measurements(Request $request, DateTitleService $dateTitleService, \App\Services\MobileEntry\BodyLogService $formService)
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
        
        // Get session messages for display
        $sessionMessages = [
            'success' => session('success'),
            'error' => session('error'),
            'warning' => session('warning'),
            'info' => session('info')
        ];
        
        // Add validation errors if they exist
        if ($errors = session('errors')) {
            $errorMessages = $errors->all();
            if (!empty($errorMessages)) {
                $sessionMessages['error'] = implode(' ', $errorMessages);
            }
        }
        
        // Generate forms for all measurement types (always show all)
        $forms = $formService->generateForms(Auth::id(), $selectedDate);
        
        // Generate logged items using the service
        $loggedItems = $formService->generateLoggedItems(Auth::id(), $selectedDate);
        
        // No item selection list needed - all measurement types show as forms automatically
        $itemSelectionList = [
            'items' => [],
            'ariaLabels' => [
                'section' => 'Item selection list'
            ],
            'filterPlaceholder' => 'Filter items...',
            'noResultsMessage' => 'No items found.'
        ];
        
        // Generate interface messages
        $interfaceMessages = $formService->generateInterfaceMessages($sessionMessages);
        
        // Add contextual help messages if no session messages exist
        if (!$interfaceMessages['hasMessages']) {
            $contextualMessages = $formService->generateContextualHelpMessages(Auth::id(), $selectedDate);
            if (!empty($contextualMessages)) {
                $interfaceMessages = [
                    'messages' => $contextualMessages,
                    'hasMessages' => true,
                    'messageCount' => count($contextualMessages)
                ];
            }
        }
        
        // Build components array using ComponentBuilder
        $components = [];
        
        // Navigation - disable "Next" button when viewing today or future dates
        $components[] = \App\Services\ComponentBuilder::navigation()
            ->prev('← Prev', route('mobile-entry.measurements', ['date' => $prevDay->toDateString()]))
            ->center('Today', route('mobile-entry.measurements', ['date' => $today->toDateString()]))
            ->next('Next →', route('mobile-entry.measurements', ['date' => $nextDay->toDateString()]), $selectedDate->lt($today))
            ->ariaLabel('Date navigation')
            ->build();
        
        // Title
        $components[] = \App\Services\ComponentBuilder::title(
            $dateTitleData['main'],
            $dateTitleData['subtitle'] ?? null
        )->build();
        
        // Interface messages
        if ($interfaceMessages['hasMessages']) {
            $messagesBuilder = \App\Services\ComponentBuilder::messages();
            foreach ($interfaceMessages['messages'] as $message) {
                $messagesBuilder->add($message['type'], $message['text'], $message['prefix'] ?? null);
            }
            $components[] = $messagesBuilder->build();
        }
        
        // No summary for measurements
        // No add button needed - all measurement types show as forms automatically
        
        // Forms
        $components = array_merge($components, $forms);
        
        // Logged items
        $components[] = $loggedItems;
        
        $data = [
            'components' => $components,
            'autoscroll' => false
        ];

        return view('mobile-entry.flexible', compact('data'));
    }
}
