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
        
        // Capture redirect parameters from request to pass through forms
        $redirectParams = [];
        if ($request->has('redirect_to')) {
            $redirectParams['redirect_to'] = $request->input('redirect_to');
        }
        if ($request->has('workout_id')) {
            $redirectParams['workout_id'] = $request->input('workout_id');
        }
        
        // Generate forms based on mobile lift forms using the service
        $forms = $formService->generateForms(Auth::id(), $selectedDate, $redirectParams);
        
        // Generate logged items using the service
        $loggedItems = $formService->generateLoggedItems(Auth::id(), $selectedDate);
        
        // If there are forms available to log, don't show the empty message for logged items
        if (!empty($forms) && isset($loggedItems['emptyMessage'])) {
            $loggedItems['emptyMessage'] = ''; // Set to empty string instead of unsetting
        }
        
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
        
        // Navigation
        $components[] = \App\Services\ComponentBuilder::navigation()
            ->prev('← Prev', route('mobile-entry.lifts', ['date' => $prevDay->toDateString()]))
            ->center('Today', route('mobile-entry.lifts', ['date' => $today->toDateString()]))
            ->next('Next →', route('mobile-entry.lifts', ['date' => $nextDay->toDateString()]))
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
        
        // Add Exercise button
        $components[] = \App\Services\ComponentBuilder::button('Add Exercise')
            ->ariaLabel('Add new exercise')
            ->addClass('btn-add-item')
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
        
        // Forms
        $components = array_merge($components, $forms);
        
        // Logged items (now using table component with full component data)
        $components[] = $loggedItems;
        
        $data = [
            'components' => $components,
            'autoscroll' => true
        ];

        return view('mobile-entry.flexible', compact('data'));
    }

    /**
     * Add a form for a specific exercise to the mobile interface
     * 
     * @param Request $request
     * @param LiftLogService $formService
     * @param string $exercise Exercise canonical name or ID
     * @return \Illuminate\Http\RedirectResponse
     */
    public function addLiftForm(Request $request, LiftLogService $formService, $exercise)
    {
        $selectedDate = $request->input('date') 
            ? \Carbon\Carbon::parse($request->input('date')) 
            : \Carbon\Carbon::today();
        
        $result = $formService->addExerciseForm(Auth::id(), $exercise, $selectedDate);
        
        $messageType = $result['success'] ? 'success' : 'error';
        
        // Pass through redirect parameters if they exist
        $redirectParams = ['date' => $selectedDate->toDateString()];
        
        if ($request->has('redirect_to')) {
            $redirectParams['redirect_to'] = $request->input('redirect_to');
        }
        
        if ($request->has('workout_id')) {
            $redirectParams['workout_id'] = $request->input('workout_id');
        }
        
        return redirect()->route('mobile-entry.lifts', $redirectParams)
            ->with($messageType, $result['message']);
    }

    /**
     * Create a new exercise from the mobile interface
     * 
     * @param Request $request
     * @param LiftLogService $formService
     * @return \Illuminate\Http\RedirectResponse
     */
    public function createExercise(Request $request, LiftLogService $formService)
    {
        $request->validate([
            'exercise_name' => 'required|string|max:255',
            'date' => 'nullable|date'
        ]);
        
        $selectedDate = $request->input('date') 
            ? \Carbon\Carbon::parse($request->input('date')) 
            : \Carbon\Carbon::today();
        
        $result = $formService->createExercise(Auth::id(), $request->input('exercise_name'), $selectedDate);
        
        $messageType = $result['success'] ? 'success' : 'error';
        
        return redirect()->route('mobile-entry.lifts', ['date' => $selectedDate->toDateString()])
            ->with($messageType, $result['message']);
    }

    /**
     * Remove a form from the mobile interface
     * 
     * @param Request $request
     * @param LiftLogService $formService
     * @param string $id Form ID
     * @return \Illuminate\Http\RedirectResponse
     */
    public function removeForm(Request $request, LiftLogService $formService, $id)
    {
        $selectedDate = $request->input('date') 
            ? \Carbon\Carbon::parse($request->input('date')) 
            : \Carbon\Carbon::today();
        
        $result = $formService->removeForm(Auth::id(), $id);
        
        $messageType = $result['success'] ? 'success' : 'error';
        
        return redirect()->route('mobile-entry.lifts', ['date' => $selectedDate->toDateString()])
            ->with($messageType, $result['message']);
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
        
        // Clean up old forms to prevent database bloat
        $formService->cleanupOldForms(Auth::id(), $selectedDate);
        
        // Generate forms based on selected items or quick entries
        $forms = $formService->generateForms(Auth::id(), $selectedDate, $request);
        
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
        
        // Navigation
        $components[] = \App\Services\ComponentBuilder::navigation()
            ->prev('← Prev', route('mobile-entry.foods', ['date' => $prevDay->toDateString()]))
            ->center('Today', route('mobile-entry.foods', ['date' => $today->toDateString()]))
            ->next('Next →', route('mobile-entry.foods', ['date' => $nextDay->toDateString()]))
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
        $components[] = \App\Services\ComponentBuilder::button('Add Food')
            ->ariaLabel('Add new food item')
            ->addClass('btn-add-item')
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
        
        // Forms
        foreach ($forms as $form) {
            $components[] = ['type' => 'form', 'data' => $form];
        }
        
        // Logged items
        $components[] = ['type' => 'items', 'data' => $loggedItems];
        
        $data = [
            'components' => $components,
            'autoscroll' => true
        ];

        return view('mobile-entry.flexible', compact('data'));
    }

    /**
     * Add a form for a specific food item to the mobile interface
     * 
     * @param Request $request
     * @param FoodLogService $formService
     * @param string $type 'ingredient' or 'meal'
     * @param int $id Food item ID
     * @return \Illuminate\Http\RedirectResponse
     */
    public function addFoodForm(Request $request, FoodLogService $formService, $type, $id)
    {
        $selectedDate = $request->input('date') 
            ? \Carbon\Carbon::parse($request->input('date')) 
            : \Carbon\Carbon::today();
        
        $result = $formService->addFoodForm(Auth::id(), $type, $id, $selectedDate);
        
        $messageType = $result['success'] ? 'success' : 'error';
        
        return redirect()->route('mobile-entry.foods', ['date' => $selectedDate->toDateString()])
            ->with($messageType, $result['message']);
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
     * Remove a food form from the mobile interface
     * 
     * @param Request $request
     * @param FoodLogService $formService
     * @param string $id Form ID
     * @return \Illuminate\Http\RedirectResponse
     */
    public function removeFoodForm(Request $request, FoodLogService $formService, $id)
    {
        $selectedDate = $request->input('date') 
            ? \Carbon\Carbon::parse($request->input('date')) 
            : \Carbon\Carbon::today();
        
        $result = $formService->removeFoodForm(Auth::id(), $id);
        
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
        
        // Navigation
        $components[] = \App\Services\ComponentBuilder::navigation()
            ->prev('← Prev', route('mobile-entry.measurements', ['date' => $prevDay->toDateString()]))
            ->center('Today', route('mobile-entry.measurements', ['date' => $today->toDateString()]))
            ->next('Next →', route('mobile-entry.measurements', ['date' => $nextDay->toDateString()]))
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
        foreach ($forms as $form) {
            $components[] = ['type' => 'form', 'data' => $form];
        }
        
        // Logged items
        $components[] = ['type' => 'items', 'data' => $loggedItems];
        
        $data = [
            'components' => $components,
            'autoscroll' => false
        ];

        return view('mobile-entry.flexible', compact('data'));
    }



}