<?php

namespace App\Services;

use App\Models\Exercise;
use App\Services\ChartService;
use App\Services\ExercisePRService;
use App\Services\ComponentBuilder;
use App\Services\ExerciseAliasService;
use App\Services\LiftLogTableRowBuilder;
use Illuminate\Support\Facades\Auth;

class ExerciseLogsPageService
{
    public function __construct(
        private ChartService $chartService,
        private ExercisePRService $exercisePRService,
        private ExerciseAliasService $exerciseAliasService,
        private LiftLogTableRowBuilder $liftLogTableRowBuilder
    ) {}

    /**
     * Generate the complete exercise logs page components
     */
    public function generatePage(Exercise $exercise, int $userId, ?string $from = null, ?string $date = null): array
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
        $displayName = $this->exerciseAliasService->getDisplayName($exercise, Auth::user());
        
        // Build all components
        $components = [];
        
        // Header with back button
        $components[] = $this->buildHeader($displayName, $from, $date);
        
        // Session messages
        if ($sessionMessages = ComponentBuilder::messagesFromSession()) {
            $components[] = $sessionMessages;
        }
        
        // Empty state message
        if ($liftLogs->isEmpty()) {
            $components[] = ComponentBuilder::messages()
                ->tip('No training data yet. Click "Log now" to record your first workout for this exercise.')
                ->build();
        }
        
        // Log Now button
        $components[] = $this->buildLogNowButton($exercise, $from, $date);
        
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
     * Build the page header with back button
     */
    private function buildHeader(string $displayName, ?string $from, ?string $date): array
    {
        $backUrl = $this->determineBackUrl($from, $date);
        
        return ComponentBuilder::title($displayName)
            ->backButton('fa-arrow-left', $backUrl, 'Back')
            ->build();
    }

    /**
     * Determine the back URL based on context
     */
    private function determineBackUrl(?string $from, ?string $date): string
    {
        if ($from === 'mobile-entry-lifts') {
            return route('mobile-entry.lifts', $date ? ['date' => $date] : []);
        } elseif ($from === 'lift-logs-index') {
            return route('lift-logs.index');
        } else {
            return route('lift-logs.index');
        }
    }

    /**
     * Build the Log Now button
     */
    private function buildLogNowButton(Exercise $exercise, ?string $from, ?string $date): array
    {
        $redirectTo = $from === 'mobile-entry-lifts' ? 'mobile-entry-lifts' : 'exercises-logs';
        $logNowParams = [
            'exercise_id' => $exercise->id,
            'redirect_to' => $redirectTo
        ];
        
        if ($from === 'mobile-entry-lifts' && $date) {
            $logNowParams['date'] = $date;
        }
        
        return ComponentBuilder::button('Log Now')
            ->asLink(route('lift-logs.create', $logNowParams))
            ->build();
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
            
            // Show PRs for 1-10 reps
            for ($reps = 1; $reps <= 10; $reps++) {
                $key = "rep_{$reps}";
                $label = "1 × {$reps}";
                
                if (isset($prData[$key]) && $prData[$key] !== null) {
                    $prCardsBuilder->card($label, $prData[$key]['weight'], 'lbs', $prData[$key]['date']);
                } else {
                    $prCardsBuilder->card($label, null, 'lbs');
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
                    ->info('This 1-rep max is estimated based on your previous lifts using a standard formula. For more accurate training percentages, test your actual 1, 2, or 3 rep max.')
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