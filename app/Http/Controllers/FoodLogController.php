<?php

namespace App\Http\Controllers;

use App\Models\FoodLog;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\NutritionService;
use App\Services\MobileEntry\FoodLogService;
use App\Services\DateNavigationService;
use App\Services\RedirectService;
use Carbon\Carbon;

class FoodLogController extends Controller
{
    protected $nutritionService;
    protected $dateNavigationService;
    protected $foodLogService;
    protected $redirectService;

    public function __construct(
        NutritionService $nutritionService,
        DateNavigationService $dateNavigationService,
        FoodLogService $foodLogService,
        RedirectService $redirectService
    ) {
        $this->nutritionService = $nutritionService;
        $this->dateNavigationService = $dateNavigationService;
        $this->foodLogService = $foodLogService;
        $this->redirectService = $redirectService;
    }

    /**
     * Show the form for creating a new ingredient food log.
     */
    public function createIngredientForm(Request $request, Ingredient $ingredient)
    {
        if ($ingredient->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        $selectedDate = $request->input('date') 
            ? Carbon::parse($request->input('date')) 
            : Carbon::today();

        $form = $this->foodLogService->generateIngredientCreateForm($ingredient, auth()->id(), $selectedDate, $request->input('redirect_to'));
        
        // Add a title component with back button
        $backUrl = route('mobile-entry.foods');
        if ($request->input('date')) {
            $backUrl = route('mobile-entry.foods', ['date' => $request->input('date')]);
        }
            
        $title = \App\Services\ComponentBuilder::title("Log Ingredient: {$ingredient->name}")
            ->backButton('fa-arrow-left', $backUrl, 'Back to Food Log')
            ->build();
        
        $components = [$title];
        
        // Add session messages if any (including validation errors)
        if ($sessionMessages = \App\Services\ComponentBuilder::messagesFromSession()) {
            $components[] = $sessionMessages;
        }
        
        $components[] = $form;
        
        return view('mobile-entry.flexible', [
            'data' => [
                'components' => $components
            ]
        ]);
    }

    /**
     * Show the form for creating a new meal food log.
     */
    public function createMealForm(Request $request, Meal $meal)
    {
        if ($meal->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        $selectedDate = $request->input('date') 
            ? Carbon::parse($request->input('date')) 
            : Carbon::today();

        $form = $this->foodLogService->generateMealCreateForm($meal, auth()->id(), $selectedDate, $request->input('redirect_to'));
        
        // Add a title component with back button
        $backUrl = route('mobile-entry.foods');
        if ($request->input('date')) {
            $backUrl = route('mobile-entry.foods', ['date' => $request->input('date')]);
        }
            
        $title = \App\Services\ComponentBuilder::title("Log Meal: {$meal->name}")
            ->backButton('fa-arrow-left', $backUrl, 'Back to Food Log')
            ->build();
        
        $components = [$title];
        
        // Add session messages if any (including validation errors)
        if ($sessionMessages = \App\Services\ComponentBuilder::messagesFromSession()) {
            $components[] = $sessionMessages;
        }
        
        $components[] = $form;
        
        return view('mobile-entry.flexible', [
            'data' => [
                'components' => $components
            ]
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, FoodLog $foodLog)
    {
        if ($foodLog->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        $form = $this->foodLogService->generateEditForm($foodLog, $request->input('redirect_to'));
        
        // Add a title component
        $title = \App\Services\ComponentBuilder::title('Edit Food Log', $foodLog->logged_at->format('M d, Y - H:i'))->build();
        
        return view('mobile-entry.flexible', [
            'data' => [
                'components' => [$title, $form]
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        // Original desktop store logic
        $request->merge([
            'quantity' => str_replace(',', '.', $request->input('quantity')),
        ]);

        $validated = $request->validate([
            'ingredient_id' => 'required|exists:ingredients,id',
            'quantity' => 'required|numeric|min:0.01',
            'logged_at' => 'required|date_format:H:i',
            'date' => 'nullable|date',  // Make date nullable to handle missing date
            'notes' => 'nullable|string',
        ]);

        $ingredient = Ingredient::find($validated['ingredient_id']);
        $validated['unit_id'] = $ingredient->base_unit_id;

        // If no date provided, default to today (this handles the stale page fix)
        $loggedAtDate = isset($validated['date']) 
            ? Carbon::parse($validated['date'])
            : Carbon::today();
        $validated['logged_at'] = $loggedAtDate->setTimeFromTimeString($validated['logged_at']);

        $logEntry = FoodLog::create(array_merge($validated, ['user_id' => auth()->id()]));

        // Handle mobile entry redirects
        if ($request->has('redirect_to') && in_array($request->input('redirect_to'), ['mobile-entry', 'mobile-entry-foods'])) {
            // Generate celebratory message
            $celebratoryMessage = $this->generateCelebratoryMessage($logEntry);

            // Only include date in redirect if it's not today
            $redirectContext = [];
            if (!$loggedAtDate->isToday()) {
                $redirectContext['date'] = $loggedAtDate->format('Y-m-d');
            }

            return $this->redirectService->getRedirect(
                'food_logs',
                'store',
                $request,
                $redirectContext,
                $celebratoryMessage
            );
        }

        // Only include date in redirect if it's not today
        $redirectContext = [];
        if (!$loggedAtDate->isToday()) {
            $redirectContext['date'] = $loggedAtDate->format('Y-m-d');
        }

        return $this->redirectService->getRedirect(
            'food_logs',
            'store',
            $request,
            $redirectContext,
            'Log entry added successfully!'
        );
    }

    public function update(Request $request, FoodLog $foodLog)
    {
        if ($foodLog->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $validated = $request->validate([
            'ingredient_id' => 'sometimes|exists:ingredients,id',
            'quantity' => 'required|numeric|min:0.01',
            'logged_at' => 'sometimes|date_format:H:i',
            'date' => 'sometimes|date',
            'notes' => 'nullable|string',
        ]);

        if (isset($validated['date']) && isset($validated['logged_at'])) {
            $loggedAtDate = Carbon::parse($validated['date']);
            $validated['logged_at'] = $loggedAtDate->setTimeFromTimeString($validated['logged_at']);
        }

        $foodLog->update($validated);

        return $this->redirectService->getRedirect(
            'food_logs',
            'update',
            $request,
            ['date' => $foodLog->logged_at->format('Y-m-d')],
            'Log entry updated successfully!'
        );
    }

    public function destroy(FoodLog $foodLog, Request $request)
    {
        if ($foodLog->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $date = $foodLog->logged_at->format('Y-m-d');
        $foodLog->delete();

        return $this->redirectService->getRedirect(
            'food_logs',
            'destroy',
            $request,
            ['date' => $date],
            'Log entry deleted successfully!'
        );
    }



    public function addMealToLog(Request $request)
    {
        $validated = $request->validate([
            'meal_id' => 'required|exists:meals,id',
            'portion' => 'required|numeric|min:0.05',
            'logged_at_meal' => 'required|date_format:H:i',
            'meal_date' => 'nullable|date',  // Make meal_date nullable to handle missing date
            'notes' => 'nullable|string',
        ]);

        $meal = Meal::with('ingredients')->find($validated['meal_id']);

        if ($meal->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        // If no meal_date provided, default to today (this handles the stale page fix)
        $selectedDate = isset($validated['meal_date']) 
            ? Carbon::parse($validated['meal_date'])
            : Carbon::today();
        $loggedAt = $selectedDate->setTimeFromTimeString($validated['logged_at_meal']);

        foreach ($meal->ingredients as $ingredient) {
            $notes = $meal->name . ' (Portion: ' . (float) $validated['portion'] . ')';
            if (!empty($meal->comments)) {
                $notes .= ' - ' . $meal->comments;
            }
            if (!empty($validated['notes'])) {
                $notes .= ': ' . $validated['notes'];
            }

            FoodLog::create([
                'ingredient_id' => $ingredient->id,
                'unit_id' => $ingredient->base_unit_id,
                'quantity' => $ingredient->pivot->quantity * $validated['portion'],
                'logged_at' => $loggedAt,
                'notes' => $notes,
                'user_id' => auth()->id(),
            ]);
        }

        // Handle mobile entry redirects
        if ($request->has('redirect_to') && in_array($request->input('redirect_to'), ['mobile-entry', 'mobile-entry-foods'])) {
            // Generate celebratory message for meal
            $celebratoryMessage = $this->generateMealCelebratoryMessage($meal, $validated['portion']);

            // Only include date in redirect if it's not today
            $redirectContext = [];
            if (!$selectedDate->isToday()) {
                $redirectContext['date'] = $selectedDate->format('Y-m-d');
            }

            return $this->redirectService->getRedirect(
                'food_logs',
                'add_meal',
                $request,
                $redirectContext,
                $celebratoryMessage
            );
        }

        // Only include date in redirect if it's not today
        $redirectContext = [];
        if (!$selectedDate->isToday()) {
            $redirectContext['date'] = $selectedDate->format('Y-m-d');
        }

        return $this->redirectService->getRedirect(
            'food_logs',
            'add_meal',
            $request,
            $redirectContext,
            'Meal added to log successfully!'
        );
    }

    /**
     * Generate a celebratory message for food logging
     * 
     * @param \App\Models\FoodLog $foodLog
     * @return string
     */
    private function generateCelebratoryMessage($foodLog)
    {
        $ingredient = $foodLog->ingredient;
        $foodName = $ingredient->name;

        // Calculate nutrition info for the logged quantity
        $calories = round(app(\App\Services\NutritionService::class)->calculateTotalMacro($ingredient, 'calories', (float) $foodLog->quantity));
        $protein = round(app(\App\Services\NutritionService::class)->calculateTotalMacro($ingredient, 'protein', (float) $foodLog->quantity), 1);

        // Generate food description
        $quantityText = $foodLog->quantity . ' ' . $foodLog->unit->name;
        $nutritionText = $calories . ' cal, ' . $protein . 'g protein';
        $foodDescription = $quantityText . ' • ' . $nutritionText;

        // Get celebratory messages from config and replace placeholders
        $celebrationTemplates = config('mobile_entry_messages.success.food_logged');
        $randomTemplate = $celebrationTemplates[array_rand($celebrationTemplates)];

        // Replace placeholders in the template
        return str_replace([':food', ':details'], [$foodName, $foodDescription], $randomTemplate);
    }

    /**
     * Generate a celebratory message for meal logging
     * 
     * @param \App\Models\Meal $meal
     * @param float $portion
     * @return string
     */
    private function generateMealCelebratoryMessage($meal, $portion)
    {
        $mealName = $meal->name;

        // Calculate total nutrition for the meal portion
        $totalCalories = 0;
        $totalProtein = 0;
        $nutritionService = app(\App\Services\NutritionService::class);

        foreach ($meal->ingredients as $ingredient) {
            $quantity = $ingredient->pivot->quantity * $portion;
            $totalCalories += $nutritionService->calculateTotalMacro($ingredient, 'calories', $quantity);
            $totalProtein += $nutritionService->calculateTotalMacro($ingredient, 'protein', $quantity);
        }

        // Generate meal description
        $portionText = $portion == 1 ? '1 serving' : $portion . ' servings';
        $nutritionText = round($totalCalories) . ' cal, ' . round($totalProtein, 1) . 'g protein';
        $mealDescription = $portionText . ' • ' . $nutritionText;

        // Get celebratory messages from config and replace placeholders
        $celebrationTemplates = config('mobile_entry_messages.success.meal_logged');
        $randomTemplate = $celebrationTemplates[array_rand($celebrationTemplates)];

        // Replace placeholders in the template
        return str_replace([':meal', ':details'], [$mealName, $mealDescription], $randomTemplate);
    }
}
