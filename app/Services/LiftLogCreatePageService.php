<?php

namespace App\Services;

use App\Models\Exercise;
use App\Services\ExerciseAliasService;
use App\Services\ExerciseLogsPageService;
use App\Services\MobileEntry\LiftLogService;
use App\Services\ComponentBuilder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LiftLogCreatePageService
{
    public function __construct(
        private ExerciseAliasService $exerciseAliasService,
        private ExerciseLogsPageService $exerciseLogsPageService,
        private LiftLogService $liftLogService
    ) {}

    public function generatePage(Request $request): array
    {
        $parameters = $this->extractParameters($request);
        $exercise = $this->validateAndGetExercise($parameters['exerciseId']);
        $backUrl = $this->determineBackUrl($parameters);
        $displayName = $this->exerciseAliasService->getDisplayName($exercise, Auth::user());
        $activeTab = $this->determineActiveTab();
        
        $tabComponents = [
            'help' => $this->generateHelpComponents($displayName),
            'history' => $this->generateMetricsComponents($exercise, $parameters),
            'log' => $this->generateLogComponents($parameters)
        ];
        
        return $this->buildPageComponents($displayName, $parameters['date'], $backUrl, $activeTab, $tabComponents);
    }

    private function extractParameters(Request $request): array
    {
        $exerciseId = $request->input('exercise_id');
        $date = $request->input('date') 
            ? Carbon::parse($request->input('date'))
            : Carbon::today();
        
        $redirectParams = [];
        if ($request->has('redirect_to')) {
            $redirectParams['redirect_to'] = $request->input('redirect_to');
        }
        if ($request->has('workout_id')) {
            $redirectParams['workout_id'] = $request->input('workout_id');
        }
        
        return [
            'exerciseId' => $exerciseId,
            'date' => $date,
            'redirectParams' => $redirectParams,
            'redirectTo' => $request->input('redirect_to'),
            'workoutId' => $request->input('workout_id')
        ];
    }

    private function validateAndGetExercise(int $exerciseId): Exercise
    {
        $exercise = Exercise::where('id', $exerciseId)
            ->availableToUser(Auth::id())
            ->first();
            
        if (!$exercise) {
            throw new \Exception('Exercise not found or not accessible.');
        }
        
        return $exercise;
    }

    private function determineBackUrl(array $parameters): string
    {
        $redirectTo = $parameters['redirectTo'];
        $date = $parameters['date'];
        $exerciseId = $parameters['exerciseId'];
        $workoutId = $parameters['workoutId'];
        
        if ($redirectTo === 'workouts') {
            return route('workouts.index', $workoutId ? ['workout_id' => $workoutId] : []);
        } elseif ($redirectTo === 'mobile-entry-lifts') {
            return route('mobile-entry.lifts', ['date' => $date->toDateString()]);
        } elseif ($redirectTo === 'exercises-logs') {
            return route('exercises.show-logs', ['exercise' => $exerciseId]);
        } else {
            return route('mobile-entry.lifts', ['date' => $date->toDateString()]);
        }
    }

    private function determineActiveTab(): string
    {
        $errors = session()->get('errors', new \Illuminate\Support\MessageBag());
        if ($errors->any()) {
            return 'log';
        } elseif (session('success')) {
            return 'history';
        }
        
        return 'log';
    }

    private function generateHelpComponents(string $displayName): array
    {
        return [
            ComponentBuilder::markdown('
# Getting Started

Track your ' . strtolower($displayName) . ' progress with this simple tool.

## How to Use

- **My Metrics**: View your progress charts and workout history
- **Log Now**: Record a new workout with weight, reps, and sets')->build(),
        ];
    }

    private function generateMetricsComponents(Exercise $exercise, array $parameters): array
    {
        try {
            $metricsComponents = $this->exerciseLogsPageService->generatePage(
                $exercise,
                Auth::id(),
                $parameters['redirectTo'] === 'mobile-entry-lifts' ? 'mobile-entry-lifts' : null,
                $parameters['redirectTo'] === 'mobile-entry-lifts' ? $parameters['date']->toDateString() : null
            );
            
            // Remove the title component since we'll have our own title
            return array_filter($metricsComponents, function($component) {
                return !isset($component['type']) || $component['type'] !== 'title';
            });
        } catch (\Exception $e) {
            return [
                ComponentBuilder::messages()
                    ->info('No training data yet. Use the "Log Now" tab to record your first workout for this exercise.')
                    ->build()
            ];
        }
    }

    private function generateLogComponents(array $parameters): array
    {
        try {
            $formComponent = $this->liftLogService->generateFormComponent(
                $parameters['exerciseId'],
                Auth::id(),
                $parameters['date'],
                $parameters['redirectParams']
            );
            return [$formComponent];
        } catch (\Exception $e) {
            return [
                ComponentBuilder::messages()
                    ->error('Unable to load the logging form. Please try again.')
                    ->build()
            ];
        }
    }

    private function buildPageComponents(string $displayName, Carbon $date, string $backUrl, string $activeTab, array $tabComponents): array
    {
        $components = [];
        
        // Add title with back button
        $components[] = ComponentBuilder::title($displayName)
            ->subtitle($date->format('l, F j, Y'))
            ->backButton('fa-arrow-left', $backUrl, 'Back')
            ->condensed()
            ->build();
        
        // Add session messages if any
        if ($sessionMessages = ComponentBuilder::messagesFromSession()) {
            $components[] = $sessionMessages;
        }
        
        // Add tabbed interface
        $components[] = ComponentBuilder::tabs('lift-logger-tabs')
            ->tab('help', 'Help', $tabComponents['help'], 'fa-question-circle', $activeTab === 'help', true)
            ->tab('history', 'My Metrics', $tabComponents['history'], 'fa-chart-line', $activeTab === 'history')
            ->tab('log', 'Log Now', $tabComponents['log'], 'fa-plus', $activeTab === 'log')
            ->ariaLabels([
                'section' => 'Lift logging interface with help, metrics and logging views',
                'tabList' => 'Switch between help, metrics and logging views',
                'tabPanel' => 'Content for selected tab'
            ])
            ->build();
        
        return $components;
    }
}
