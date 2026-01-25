<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\DateTitleService;
use App\Services\MobileEntry\LiftLogService;
use App\Services\MobileEntry\FoodLogService;
use App\Services\MobileEntry\DateContextBuilder;
use App\Services\MobileEntry\SessionMessageService;
use App\Services\MobileEntry\ComponentAssembler;
use App\Services\MobileEntry\PRCelebrationService;
use App\Services\MobileEntry\ExerciseCreationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Carbon\Carbon;

class MobileEntryController extends AbstractMobileEntryController
{
    public function __construct(
        DateContextBuilder $dateContextBuilder,
        SessionMessageService $sessionMessageService,
        ComponentAssembler $componentAssembler,
        private LiftLogService $liftLogService,
        private FoodLogService $foodLogService,
        private \App\Services\MobileEntry\BodyLogService $bodyLogService,
        private PRCelebrationService $prCelebrationService,
        private ExerciseCreationService $exerciseCreationService
    ) {
        parent::__construct($dateContextBuilder, $sessionMessageService, $componentAssembler);
    }
    /**
     * Display the lift logging interface
     */
    public function lifts(Request $request): \Illuminate\View\View
    {
        return $this->renderEntryPage($request, 'lifts');
    }
    
    /**
     * Get service data for the entry page
     * Hook implementation - varies by entry type
     */
    protected function getServiceData(
        Request $request,
        array $dateContext,
        array $sessionMessages,
        string $entryType
    ): array {
        $service = $this->getServiceForType($entryType);
        $userId = Auth::id();
        $selectedDate = $dateContext['selectedDate'];
        
        $data = [
            'loggedItems' => $service->generateLoggedItems($userId, $selectedDate),
            'itemSelectionList' => $service->generateItemSelectionList($userId, $selectedDate),
            'interfaceMessages' => $this->buildInterfaceMessages(
                $service,
                $userId,
                $selectedDate,
                $sessionMessages,
                $request
            ),
            'summary' => $service->generateSummary($userId, $selectedDate),
        ];
        
        // Add measurement forms if needed
        if ($entryType === 'measurements') {
            $data['forms'] = $service->generateItemSelectionList($userId, $selectedDate);
        }
        
        return $data;
    }
    
    /**
     * Build view data with PR celebration for lifts
     */
    protected function buildViewData(array $components, array $serviceData, string $entryType, array $dateContext): array
    {
        $data = parent::buildViewData($components, $serviceData, $entryType, $dateContext);
        
        // Add PR celebration for lifts only
        if ($entryType === 'lifts') {
            $data['has_prs'] = $this->prCelebrationService->hasPRsToday(
                Auth::id(),
                $dateContext['selectedDate']
            );
        }
        
        return $data;
    }
    
    /**
     * Check if user is first-time (lifts only)
     */
    protected function isFirstTimeUser(string $entryType): bool
    {
        if ($entryType !== 'lifts') {
            return false;
        }
        
        return \App\Models\LiftLog::where('user_id', Auth::id())->count() === 0;
    }
    
    /**
     * Determine if add button should be shown
     */
    protected function shouldShowAddButton(Request $request, string $entryType): bool
    {
        if ($entryType === 'measurements') {
            return false; // Measurements show forms, not item list
        }
        
        return !$request->boolean('expand_selection');
    }
    
    /**
     * Get the appropriate service for the entry type
     */
    private function getServiceForType(string $entryType)
    {
        return match($entryType) {
            'lifts' => $this->liftLogService,
            'foods' => $this->foodLogService,
            'measurements' => $this->bodyLogService,
        };
    }
    
    /**
     * Build interface messages with contextual help
     */
    private function buildInterfaceMessages($service, int $userId, Carbon $selectedDate, array $sessionMessages, Request $request): array
    {
        $interfaceMessages = $service->generateInterfaceMessages($sessionMessages);
        
        $contextualMessages = $service->generateContextualHelpMessages(
            $userId,
            $selectedDate,
            $request->boolean('expand_selection')
        );
        
        if (!empty($contextualMessages)) {
            $interfaceMessages['messages'] = array_merge(
                $interfaceMessages['messages'] ?? [],
                $contextualMessages
            );
            $interfaceMessages['hasMessages'] = true;
            $interfaceMessages['messageCount'] = count($interfaceMessages['messages']);
        }
        
        return $interfaceMessages;
    }

    /**
     * Create a new exercise from the mobile interface
     */
    public function createExercise(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'exercise_name' => 'required|string|max:255',
            'date' => 'nullable|date'
        ]);
        
        $result = $this->exerciseCreationService->createOrFindExercise(
            userId: Auth::id(),
            exerciseName: $validated['exercise_name'],
            date: $validated['date'] ?? null
        );
        
        return redirect()
            ->route('lift-logs.create', $result['routeParams'])
            ->with($result['messageType'], $result['message']);
    }

    /**
     * Display the food logging interface
     */
    public function foods(Request $request): \Illuminate\View\View
    {
        return $this->renderEntryPage($request, 'foods');
    }

    /**
     * Create a new ingredient from the mobile interface
     */
    public function createIngredient(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ingredient_name' => 'required|string|max:255',
            'date' => 'nullable|date'
        ]);
        
        $selectedDate = $validated['date'] 
            ? Carbon::parse($validated['date'])
            : Carbon::today();
        
        $result = $this->foodLogService->createIngredient(
            Auth::id(),
            $validated['ingredient_name'],
            $selectedDate
        );
        
        $messageType = $result['success'] ? 'success' : 'error';
        
        return redirect()
            ->route('mobile-entry.foods', ['date' => $selectedDate->toDateString()])
            ->with($messageType, $result['message']);
    }

    /**
     * Display the measurements logging interface
     */
    public function measurements(Request $request): \Illuminate\View\View
    {
        return $this->renderEntryPage($request, 'measurements');
    }
}
