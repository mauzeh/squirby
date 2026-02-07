<?php

namespace App\Services;

use App\Models\Exercise;
use App\Services\ChartService;
use App\Services\ExercisePRService;
use App\Services\ComponentBuilder;
use App\Services\ExerciseAliasService;
use App\Services\LiftLogTableRowBuilder;
use App\Services\MobileEntry\LiftLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class ExercisePageService
{
    public function __construct(
        private ChartService $chartService,
        private ExercisePRService $exercisePRService,
        private ExerciseAliasService $exerciseAliasService,
        private LiftLogTableRowBuilder $liftLogTableRowBuilder,
        private LiftLogService $liftLogService
    ) {}

    /**
     * Generate the unified exercise page with tabbed interface
     */
    public function generatePage(
        Exercise $exercise,
        int $userId,
        string $defaultTab = 'history',
        ?string $from = null,
        ?string $date = null,
        array $redirectParams = []
    ): array {
        $displayName = $this->exerciseAliasService->getDisplayName($exercise, Auth::user());
        $backUrl = $this->determineBackUrl($from, $date, $exercise->id, $redirectParams);
        $activeTab = $this->determineActiveTab($defaultTab);
        
        $tabComponents = [
            'help' => $this->generateHelpComponents($displayName),
            'history' => $this->generateMetricsComponents($exercise, $userId),
            'log' => $this->generateLogComponents($exercise->id, $userId, $date, $redirectParams)
        ];
        
        return $this->buildPageComponents($displayName, $date, $backUrl, $activeTab, $tabComponents);
    }

    /**
     * Determine the active tab based on session state and default
     */
    private function determineActiveTab(string $defaultTab): string
    {
        $errors = session()->get('errors', new \Illuminate\Support\MessageBag());
        if ($errors->any()) {
            return 'log';
        } elseif (session('success')) {
            return 'history';
        }
        
        return $defaultTab;
    }

    /**
     * Determine the back URL based on context
     */
    private function determineBackUrl(?string $from, ?string $date, int $exerciseId, array $redirectParams): string
    {
        // Check redirect parameters first (from lift-logs/create context)
        if (isset($redirectParams['redirect_to'])) {
            $redirectTo = $redirectParams['redirect_to'];
            $workoutId = $redirectParams['workout_id'] ?? null;
            $dateString = $date ? Carbon::parse($date)->toDateString() : Carbon::today()->toDateString();
            
            if ($redirectTo === 'workouts') {
                return route('workouts.index', $workoutId ? ['workout_id' => $workoutId] : []);
            } elseif ($redirectTo === 'mobile-entry-lifts') {
                return route('mobile-entry.lifts', ['date' => $dateString]);
            } elseif ($redirectTo === 'exercises-logs') {
                return route('exercises.show-logs', ['exercise' => $exerciseId]);
            } else {
                return route('mobile-entry.lifts', ['date' => $dateString]);
            }
        }
        
        // Handle exercises/{id}/logs context
        if ($from === 'mobile-entry-lifts') {
            return route('mobile-entry.lifts', $date ? ['date' => $date] : []);
        } elseif ($from === 'lift-logs-index') {
            return route('lift-logs.index');
        } else {
            return route('lift-logs.index');
        }
    }

    /**
     * Generate help tab components
     */
    private function generateHelpComponents(string $displayName): array
    {
        return [
            ComponentBuilder::markdown('
# Getting Started

Track your ' . strtolower($displayName) . ' progress with this app.

## How to Use

- **Log Now**: Record a new workout with weight, reps, and sets
- **My Metrics**: View your progress charts and workout history')->build(),
        ];
    }

    /**
     * Generate metrics tab components (PR cards, charts, table)
     */
    private function generateMetricsComponents(Exercise $exercise, int $userId): array
    {
        // Load necessary relationships and data
        $exercise->load(['aliases' => function ($query) use ($userId) {
            $query->where('user_id', $userId);
        }]);
        
        $liftLogs = $exercise->liftLogs()
            ->with(['liftSets', 'exercise.aliases' => function ($query) use ($userId) {
                $query->where('user_id', $userId);
            }])
            ->where('user_id', $userId)
            ->orderBy('logged_at', 'desc')
            ->get();

        $chartData = $this->chartService->generateProgressChart($liftLogs, $exercise);
        
        $components = [];
        
        // Empty state message
        if ($liftLogs->isEmpty()) {
            $components[] = ComponentBuilder::messages()
                ->info('No training data yet. Use the "Log Now" tab to record your first workout for this exercise.')
                ->build();
            return $components;
        }
        
        // PR Cards and Calculator (if supported)
        if ($this->exercisePRService->supportsPRTracking($exercise)) {
            $components = array_merge($components, $this->buildPRComponents($exercise));
        }
        
        // Chart (if we have data)
        if ($liftLogs->isNotEmpty() && !empty($chartData['datasets'])) {
            $components[] = $this->buildChart($exercise, $liftLogs, $chartData);
        }
        
        // Table (if we have data)
        if ($liftLogs->isNotEmpty()) {
            $components[] = $this->buildTable($liftLogs);
        }
        
        return $components;
    }

    /**
     * Generate log tab components
     */
    private function generateLogComponents(int $exerciseId, int $userId, ?string $date, array $redirectParams): array
    {
        try {
            $logDate = $date ? Carbon::parse($date) : Carbon::today();
            $formComponent = $this->liftLogService->generateFormComponent(
                $exerciseId,
                $userId,
                $logDate,
                $redirectParams
            );
            return [$formComponent];
        } catch (\Exception) {
            return [
                ComponentBuilder::messages()
                    ->error('Unable to load the logging form. Please try again.')
                    ->build()
            ];
        }
    }

    /**
     * Build the complete page with tabbed interface
     */
    private function buildPageComponents(string $displayName, ?string $date, string $backUrl, string $activeTab, array $tabComponents): array
    {
        $components = [];
        
        // Add title with back button
        $subtitle = $date ? Carbon::parse($date)->format('l, F j, Y') : null;
        $titleBuilder = ComponentBuilder::title($displayName)
            ->backButton('fa-arrow-left', $backUrl, 'Back')
            ->condensed();
            
        if ($subtitle) {
            $titleBuilder->subtitle($subtitle);
        }
        
        $components[] = $titleBuilder->build();
        
        // Add session messages if any
        if ($sessionMessages = ComponentBuilder::messagesFromSession()) {
            $components[] = $sessionMessages;
        }
        
        // Add tabbed interface
        $components[] = ComponentBuilder::tabs('exercise-tabs')
            ->tab('help', 'Help', $tabComponents['help'], 'fa-question-circle', $activeTab === 'help', true)
            ->tab('log', 'Log Now', $tabComponents['log'], 'fa-plus', $activeTab === 'log')
            ->tab('history', 'My Metrics', $tabComponents['history'], 'fa-chart-line', $activeTab === 'history')
            ->ariaLabels([
                'section' => 'Exercise interface with help, metrics and logging views',
                'tabList' => 'Switch between help, metrics and logging views',
                'tabPanel' => 'Content for selected tab'
            ])
            ->build();
        
        return $components;
    }
    /**
     * Build PR Cards and Calculator components
     */
    private function buildPRComponents(Exercise $exercise): array
    {
        $components = [];
        $prData = $this->exercisePRService->getPRData($exercise, Auth::user(), 10);
        $estimated1RM = null;
        
        // Check if we have any actual 1-3 rep PRs
        $hasActualPRs = $prData && (
            ($prData['rep_1'] ?? null) !== null ||
            ($prData['rep_2'] ?? null) !== null ||
            ($prData['rep_3'] ?? null) !== null
        );
        
        // If no actual PRs, get estimated 1RM from best lift
        if (!$hasActualPRs) {
            $estimated1RM = $this->exercisePRService->getEstimated1RM($exercise, Auth::user());
        }
        
        if (!$prData && !$estimated1RM) {
            return $components;
        }
        
        // Build PR Cards component only if we have actual PR data
        if ($prData) {
            $prCardsBuilder = ComponentBuilder::prCards('Heaviest Lifts')
                ->scrollable();
            
            // Find the most recent PR to highlight
            $mostRecentPRKey = $this->exercisePRService->getMostRecentPRKey($prData);
            
            // Show PRs for 1-10 reps
            for ($reps = 1; $reps <= 10; $reps++) {
                $key = "rep_{$reps}";
                $label = "1 × {$reps}";
                $isRecent = ($key === $mostRecentPRKey);
                
                if (isset($prData[$key]) && $prData[$key] !== null) {
                    $prCardsBuilder->card($label, $prData[$key]['weight'], 'lbs', $prData[$key]['date'], $isRecent);
                } else {
                    $prCardsBuilder->card($label, null, 'lbs', null, false);
                }
            }
            
            $components[] = $prCardsBuilder->build();
        }
        
        // Build Calculator Grid component
        $calculatorGrid = $this->exercisePRService->getCalculatorGrid(
            $exercise,
            $prData ?? [],
            $estimated1RM
        );
        
        if ($calculatorGrid) {
            $gridTitle = $calculatorGrid['is_estimated'] 
                ? '1-Rep Max Percentages (Estimated)' 
                : '1-Rep Max Percentages';
            
            // Add info message if data is estimated
            if ($calculatorGrid['is_estimated']) {
                $components[] = ComponentBuilder::messages()
                    ->info('The % table is estimated based on your previous lifts using a standard formula. For more accurate training percentages, test your actual 1, 2, or 3 rep max.')
                    ->build();
            }
            // Add warning if PR data is stale (older than 6 months)
            elseif ($prData && $this->exercisePRService->isPRDataStale($prData)) {
                $components[] = ComponentBuilder::messages()
                    ->warning('Your max lift data is over 6 months old. Consider retesting your 1, 2, or 3 rep max to ensure accurate training percentages.')
                    ->build();
            }
            
            $components[] = ComponentBuilder::calculatorGrid($gridTitle)
                ->columns($calculatorGrid['columns'])
                ->percentages([100, 95, 90, 85, 80, 75, 70, 65, 60, 55, 50, 45])
                ->rows($calculatorGrid['rows'])
                ->build();
        }
        
        return $components;
    }

    /**
     * Build the progress chart component
     */
    private function buildChart(Exercise $exercise, $liftLogs, array $chartData): array
    {
        $strategy = $exercise->getTypeStrategy();
        $chartTitle = $strategy->getChartTitle();
        
        // Determine appropriate time scale and format based on data range
        $oldestLog = $liftLogs->last();
        $newestLog = $liftLogs->first();
        $daysDiff = $oldestLog->logged_at->diffInDays($newestLog->logged_at);
        
        // Choose time unit and display format based on data span
        if ($daysDiff > 730) { // More than 2 years
            $timeUnit = 'month';
            $displayFormat = 'MMM yyyy'; // "Jan 2023"
        } elseif ($daysDiff > 365) { // More than 1 year
            $timeUnit = 'month';
            $displayFormat = 'MMM yy'; // "Jan 23"
        } elseif ($daysDiff > 90) { // More than 3 months
            $timeUnit = 'month';
            $displayFormat = 'MMM d'; // "Jan 15"
        } else {
            $timeUnit = 'day';
            $displayFormat = 'MMM d'; // "Jan 15"
        }
        
        $chartBuilder = ComponentBuilder::chart('progressChart', $chartTitle)
            ->type('line')
            ->datasets($chartData['datasets'])
            ->timeScale($timeUnit, $displayFormat)
            ->showLegend()
            ->showTimeframeSelector()
            ->ariaLabel($exercise->title . ' progress chart')
            ->containerClass('chart-container-styled')
            ->height(300)
            ->noAspectRatio()
            ->labelColors();
        
        // Only use beginAtZero for non-1RM charts
        if ($chartTitle !== '1RM Progress') {
            $chartBuilder->beginAtZero();
        }
        
        return $chartBuilder->build();
    }

    /**
     * Build the lift logs table component
     */
    private function buildTable($liftLogs): array
    {
        $rows = $this->liftLogTableRowBuilder->buildRows($liftLogs, [
            'showDateBadge' => true,
            'showCheckbox' => false,
            'showViewLogsAction' => false, // Don't show "view logs" when already viewing logs
            'showDeleteAction' => false,
            'redirectContext' => 'exercises-logs', // For edit/delete redirects
        ]);
        
        return ComponentBuilder::table()
            ->rows($rows)
            ->emptyMessage('No lift logs found for this exercise.')
            ->ariaLabel('Exercise logs')
            ->spacedRows()
            ->build();
    }
}