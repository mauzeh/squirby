<?php

namespace App\Http\Controllers;

use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateExercise;
use App\Services\ComponentBuilder as C;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkoutTemplateController extends Controller
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;

    /**
     * Display a listing of the user's workout templates
     */
    public function index(Request $request)
    {
        $expandedTemplateId = $request->query('id');
        
        $templates = WorkoutTemplate::where('user_id', Auth::id())
            ->with(['exercises.exercise.aliases'])
            ->orderBy('name')
            ->get();

        // Apply aliases to all exercises
        $user = Auth::user();
        $aliasService = app(\App\Services\ExerciseAliasService::class);
        
        foreach ($templates as $template) {
            foreach ($template->exercises as $templateExercise) {
                if ($templateExercise->exercise) {
                    $displayName = $aliasService->getDisplayName($templateExercise->exercise, $user);
                    $templateExercise->exercise->title = $displayName;
                }
            }
        }

        $components = [];

        // Title
        $components[] = C::title('Workout Templates')
            ->subtitle('Save and reuse your favorite workouts')
            ->build();

        // Create button
        $components[] = C::button('Create New Template')
            ->asLink(route('workout-templates.create'))
            ->build();

        // Table of templates with exercises as sub-items
        if ($templates->isNotEmpty()) {
            $tableBuilder = C::table();

            foreach ($templates as $template) {
                $line1 = $template->name;
                $exerciseCount = $template->exercises->count();
                
                // Build exercise list with titles
                if ($exerciseCount > 0) {
                    $exerciseTitles = $template->exercises->pluck('exercise.title')->toArray();
                    $exerciseList = implode(', ', $exerciseTitles);
                    $line2 = $exerciseCount . ' ' . ($exerciseCount === 1 ? 'exercise' : 'exercises') . ': ' . $exerciseList;
                } else {
                    $line2 = 'No exercises';
                }
                
                $line3 = $template->description ?: null;

                $rowBuilder = $tableBuilder->row(
                    $template->id,
                    $line1,
                    $line2,
                    $line3
                )
                ->linkAction('fa-edit', route('workout-templates.edit', $template->id), 'Edit template')
                ->formAction('fa-trash', route('workout-templates.destroy', $template->id), 'DELETE', [], 'Delete', 'btn-danger', true);

                // Add exercises as sub-items with log now button
                if ($template->exercises->isNotEmpty()) {
                    foreach ($template->exercises as $index => $exercise) {
                        $exerciseLine1 = $exercise->exercise->title;
                        $exerciseLine2 = 'Order: ' . $exercise->order;
                        
                        // Build URL to mobile entry with this exercise pre-added
                        $logUrl = route('mobile-entry.lifts', [
                            'date' => Carbon::today()->toDateString(),
                            'exercise_id' => $exercise->exercise_id
                        ]);
                        
                        $rowBuilder->subItem(
                            $exercise->id,
                            $exerciseLine1,
                            $exerciseLine2,
                            null
                        )
                        ->linkAction('fa-play', $logUrl, 'Log now', 'btn-log-now')
                        ->add();
                    }
                }

                // Expand this template if it matches the ID parameter
                if ($expandedTemplateId && $template->id == $expandedTemplateId) {
                    $rowBuilder->initialState('expanded');
                }
                
                $rowBuilder->add();
            }

            $components[] = $tableBuilder
                ->confirmMessage('deleteItem', 'Are you sure you want to delete this template or remove this exercise?')
                ->build();
        } else {
            $components[] = C::messages()
                ->info('No templates yet. Create your first template to get started!')
                ->build();
        }

        $data = ['components' => $components];
        return view('mobile-entry.flexible', compact('data'));
    }

    /**
     * Show the form for creating a new template
     */
    public function create()
    {
        $components = [];

        // Title
        $components[] = C::title('Create Template')->build();

        // Form
        $components[] = C::form('create-template', 'Template Details')
            ->type('primary')
            ->formAction(route('workout-templates.store'))
            ->textField('name', 'Template Name:', '', 'e.g., Push Day')
            ->textField('description', 'Description:', '', 'Optional')
            ->submitButton('Create Template')
            ->build();

        $data = ['components' => $components];
        return view('mobile-entry.flexible', compact('data'));
    }

    /**
     * Store a newly created template
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $template = WorkoutTemplate::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_public' => false,
        ]);

        return redirect()
            ->route('workout-templates.edit', $template->id)
            ->with('success', 'Template created! Now add exercises.');
    }

    /**
     * Show the form for editing the specified template
     */
    public function edit(Request $request, WorkoutTemplate $workoutTemplate)
    {
        $this->authorize('update', $workoutTemplate);

        $workoutTemplate->load('exercises.exercise.aliases');
        
        // Apply aliases to exercises
        $user = Auth::user();
        $aliasService = app(\App\Services\ExerciseAliasService::class);
        foreach ($workoutTemplate->exercises as $templateExercise) {
            if ($templateExercise->exercise) {
                $displayName = $aliasService->getDisplayName($templateExercise->exercise, $user);
                $templateExercise->exercise->title = $displayName;
            }
        }

        // Check if we should expand the list (from "Add exercises" button)
        $shouldExpandList = $request->query('expand') === 'true';

        $components = [];

        // Title with back button
        $components[] = C::title($workoutTemplate->name)
            ->subtitle('Edit template')
            ->backButton('fa-arrow-left', route('workout-templates.index'), 'Back to templates')
            ->build();

        // Messages
        if (session('success')) {
            $components[] = C::messages()
                ->success(session('success'))
                ->build();
        }

        // Add Exercise button - hidden if list should be expanded
        $buttonBuilder = C::button('Add Exercise')
            ->ariaLabel('Add exercise to template')
            ->addClass('btn-add-item');
        
        if ($shouldExpandList) {
            $buttonBuilder->initialState('hidden');
        }
        
        $components[] = $buttonBuilder->build();

        // Exercise selection list - expanded if coming from "Add exercises" button
        $itemSelectionList = $this->generateExerciseSelectionList(Auth::id(), $workoutTemplate);
        
        $itemListBuilder = C::itemList()
            ->filterPlaceholder($itemSelectionList['filterPlaceholder'])
            ->noResultsMessage($itemSelectionList['noResultsMessage']);
        
        if ($shouldExpandList) {
            $itemListBuilder->initialState('expanded');
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
                $itemSelectionList['createForm']['hiddenFields']
            );
        }

        $components[] = $itemListBuilder->build();

        // Table of exercises
        if ($workoutTemplate->exercises->isNotEmpty()) {
            $tableBuilder = C::table();
            
            $exerciseCount = $workoutTemplate->exercises->count();

            foreach ($workoutTemplate->exercises as $index => $exercise) {
                $line1 = $exercise->exercise->title;
                $line2 = 'Priority: ' . $exercise->order;
                $line3 = null;
                
                $isFirst = $index === 0;
                $isLast = $index === $exerciseCount - 1;
                
                $rowBuilder = $tableBuilder->row($exercise->id, $line1, $line2, $line3);
                
                // Add move up button (disabled if first)
                if (!$isFirst) {
                    $rowBuilder->linkAction(
                        'fa-arrow-up',
                        route('workout-templates.move-exercise', [$workoutTemplate->id, $exercise->id, 'direction' => 'up']),
                        'Move up'
                    );
                } else {
                    $rowBuilder->linkAction(
                        'fa-arrow-up',
                        '#',
                        'Move up',
                        'btn-disabled'
                    );
                }
                
                // Add move down button (disabled if last)
                if (!$isLast) {
                    $rowBuilder->linkAction(
                        'fa-arrow-down',
                        route('workout-templates.move-exercise', [$workoutTemplate->id, $exercise->id, 'direction' => 'down']),
                        'Move down'
                    );
                } else {
                    $rowBuilder->linkAction(
                        'fa-arrow-down',
                        '#',
                        'Move down',
                        'btn-disabled'
                    );
                }
                
                // Add delete button
                $rowBuilder->formAction(
                    'fa-trash',
                    route('workout-templates.remove-exercise', [$workoutTemplate->id, $exercise->id]),
                    'DELETE',
                    [],
                    'Remove exercise',
                    'btn-danger',
                    true
                );
                
                $rowBuilder->add();
            }

            $components[] = $tableBuilder
                ->confirmMessage('deleteItem', 'Are you sure you want to remove this exercise from the template?')
                ->build();
        } else {
            $components[] = C::messages()
                ->info('No exercises yet. Add your first exercise above.')
                ->build();
        }

        // Template details form at bottom
        $components[] = C::form('edit-template-details', 'Template Details')
            ->type('info')
            ->formAction(route('workout-templates.update', $workoutTemplate->id))
            ->textField('name', 'Template Name:', $workoutTemplate->name, 'e.g., Push Day')
            ->textField('description', 'Description:', $workoutTemplate->description ?? '', 'Optional')
            ->hiddenField('_method', 'PUT')
            ->submitButton('Update Details')
            ->build();

        $data = ['components' => $components];
        return view('mobile-entry.flexible', compact('data'));
    }

    /**
     * Update the specified template
     */
    public function update(Request $request, WorkoutTemplate $workoutTemplate)
    {
        $this->authorize('update', $workoutTemplate);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $workoutTemplate->update($validated);

        return redirect()
            ->route('workout-templates.edit', $workoutTemplate->id)
            ->with('success', 'Template updated!');
    }

    /**
     * Remove the specified template
     */
    public function destroy(WorkoutTemplate $workoutTemplate)
    {
        $this->authorize('delete', $workoutTemplate);

        $workoutTemplate->delete();

        return redirect()
            ->route('workout-templates.index')
            ->with('success', 'Template deleted!');
    }

    /**
     * Add an exercise to a template
     */
    public function addExercise(Request $request, WorkoutTemplate $workoutTemplate)
    {
        $this->authorize('update', $workoutTemplate);

        $exerciseId = $request->input('exercise');
        
        if (!$exerciseId) {
            return redirect()
                ->route('workout-templates.edit', $workoutTemplate->id)
                ->with('error', 'No exercise specified.');
        }

        $exercise = \App\Models\Exercise::where('id', $exerciseId)
            ->availableToUser(Auth::id())
            ->first();

        if (!$exercise) {
            return redirect()
                ->route('workout-templates.edit', $workoutTemplate->id)
                ->with('error', 'Exercise not found.');
        }

        // Check if exercise already exists in template
        $exists = WorkoutTemplateExercise::where('workout_template_id', $workoutTemplate->id)
            ->where('exercise_id', $exercise->id)
            ->exists();

        if ($exists) {
            return redirect()
                ->route('workout-templates.edit', $workoutTemplate->id)
                ->with('warning', 'Exercise already in template.');
        }

        // Get next order (priority)
        $maxOrder = $workoutTemplate->exercises()->max('order') ?? 0;

        WorkoutTemplateExercise::create([
            'workout_template_id' => $workoutTemplate->id,
            'exercise_id' => $exercise->id,
            'order' => $maxOrder + 1,
        ]);

        return redirect()
            ->route('workout-templates.edit', $workoutTemplate->id)
            ->with('success', 'Exercise added!');
    }

    /**
     * Create a new exercise and add it to the template
     */
    public function createExercise(Request $request, WorkoutTemplate $workoutTemplate)
    {
        $this->authorize('update', $workoutTemplate);

        $validated = $request->validate([
            'exercise_name' => 'required|string|max:255',
        ]);

        // Find or create exercise
        $exercise = \App\Models\Exercise::firstOrCreate(
            ['title' => $validated['exercise_name']],
            ['user_id' => Auth::id()]
        );

        // Check if exercise already exists in template
        $exists = WorkoutTemplateExercise::where('workout_template_id', $workoutTemplate->id)
            ->where('exercise_id', $exercise->id)
            ->exists();

        if ($exists) {
            return redirect()
                ->route('workout-templates.edit', $workoutTemplate->id)
                ->with('warning', 'Exercise already in template.');
        }

        // Get next order (priority)
        $maxOrder = $workoutTemplate->exercises()->max('order') ?? 0;

        WorkoutTemplateExercise::create([
            'workout_template_id' => $workoutTemplate->id,
            'exercise_id' => $exercise->id,
            'order' => $maxOrder + 1,
        ]);

        return redirect()
            ->route('workout-templates.edit', $workoutTemplate->id)
            ->with('success', 'Exercise created and added!');
    }

    /**
     * Move an exercise up or down in the template
     */
    public function moveExercise(Request $request, WorkoutTemplate $workoutTemplate, WorkoutTemplateExercise $exercise)
    {
        $this->authorize('update', $workoutTemplate);

        if ($exercise->workout_template_id !== $workoutTemplate->id) {
            abort(404);
        }

        $direction = $request->input('direction');
        
        if ($direction === 'up') {
            // Find the exercise above this one
            $swapWith = WorkoutTemplateExercise::where('workout_template_id', $workoutTemplate->id)
                ->where('order', '<', $exercise->order)
                ->orderBy('order', 'desc')
                ->first();
        } else {
            // Find the exercise below this one
            $swapWith = WorkoutTemplateExercise::where('workout_template_id', $workoutTemplate->id)
                ->where('order', '>', $exercise->order)
                ->orderBy('order', 'asc')
                ->first();
        }

        if ($swapWith) {
            // Swap the order values
            $tempOrder = $exercise->order;
            $exercise->order = $swapWith->order;
            $swapWith->order = $tempOrder;
            
            $exercise->save();
            $swapWith->save();
        }

        return redirect()
            ->route('workout-templates.edit', $workoutTemplate->id)
            ->with('success', 'Exercise order updated!');
    }

    /**
     * Remove an exercise from a template
     */
    public function removeExercise(WorkoutTemplate $workoutTemplate, WorkoutTemplateExercise $exercise)
    {
        $this->authorize('update', $workoutTemplate);

        if ($exercise->workout_template_id !== $workoutTemplate->id) {
            abort(404);
        }

        $exercise->delete();

        return redirect()
            ->route('workout-templates.edit', $workoutTemplate->id)
            ->with('success', 'Exercise removed!');
    }

    /**
     * Generate exercise selection list (similar to mobile entry)
     */
    private function generateExerciseSelectionList($userId, WorkoutTemplate $workoutTemplate)
    {
        // Get user's accessible exercises with aliases
        $exercises = \App\Models\Exercise::availableToUser($userId)
            ->with(['aliases' => function ($query) use ($userId) {
                $query->where('user_id', $userId);
            }])
            ->orderBy('title', 'asc')
            ->get();

        // Apply aliases to exercises
        $user = \App\Models\User::find($userId);
        $aliasService = app(\App\Services\ExerciseAliasService::class);
        $exercises = $aliasService->applyAliasesToExercises($exercises, $user);

        // Get exercises already in this template (to exclude from selection list)
        $templateExerciseIds = $workoutTemplate->exercises()->pluck('exercise_id')->toArray();

        // Get recent exercises (last 7 days) for the "Recent" category
        $recentExerciseIds = \App\Models\LiftLog::where('user_id', $userId)
            ->where('logged_at', '>=', \Carbon\Carbon::now()->subDays(7))
            ->pluck('exercise_id')
            ->unique()
            ->toArray();

        // Get last performed dates for all exercises
        $lastPerformedDates = \App\Models\LiftLog::where('user_id', $userId)
            ->whereIn('exercise_id', $exercises->pluck('id'))
            ->select('exercise_id', \DB::raw('MAX(logged_at) as last_logged_at'))
            ->groupBy('exercise_id')
            ->pluck('last_logged_at', 'exercise_id');

        // Get top 10 recommended exercises
        $recommendationEngine = app(\App\Services\RecommendationEngine::class);
        $recommendations = $recommendationEngine->getRecommendations($userId, 10);
        
        $recommendationMap = [];
        foreach ($recommendations as $index => $recommendation) {
            $exerciseId = $recommendation['exercise']->id;
            $recommendationMap[$exerciseId] = $index + 1;
        }

        $items = [];
        
        foreach ($exercises as $exercise) {
            // Skip exercises already in template
            if (in_array($exercise->id, $templateExerciseIds)) {
                continue;
            }
            
            // Calculate "X ago" label
            $lastPerformedLabel = '';
            if (isset($lastPerformedDates[$exercise->id])) {
                $lastPerformed = \Carbon\Carbon::parse($lastPerformedDates[$exercise->id]);
                $lastPerformedLabel = $lastPerformed->diffForHumans(['short' => true]);
            }
            
            // Categorize exercises
            if (isset($recommendationMap[$exercise->id])) {
                $rank = $recommendationMap[$exercise->id];
                $itemType = [
                    'label' => '<i class="fas fa-star"></i> Recommended',
                    'cssClass' => 'in-program',
                    'priority' => 1,
                    'subPriority' => $rank
                ];
            } elseif (in_array($exercise->id, $recentExerciseIds)) {
                $itemType = [
                    'label' => 'Recent',
                    'cssClass' => 'recent',
                    'priority' => 2,
                    'subPriority' => 0
                ];
            } else {
                $itemType = [
                    'label' => $lastPerformedLabel,
                    'cssClass' => 'regular',
                    'priority' => 3,
                    'subPriority' => 0
                ];
            }

            $items[] = [
                'id' => 'exercise-' . $exercise->id,
                'name' => $exercise->title,
                'type' => $itemType,
                'href' => route('workout-templates.add-exercise', [
                    $workoutTemplate->id,
                    'exercise' => $exercise->id
                ])
            ];
        }

        // Sort items
        usort($items, function ($a, $b) {
            $priorityComparison = $a['type']['priority'] <=> $b['type']['priority'];
            if ($priorityComparison !== 0) {
                return $priorityComparison;
            }
            
            $subPriorityA = $a['type']['subPriority'] ?? 0;
            $subPriorityB = $b['type']['subPriority'] ?? 0;
            $subPriorityComparison = $subPriorityA <=> $subPriorityB;
            if ($subPriorityComparison !== 0) {
                return $subPriorityComparison;
            }
            
            return strcmp($a['name'], $b['name']);
        });

        return [
            'noResultsMessage' => 'No exercises found.',
            'createForm' => [
                'action' => route('workout-templates.create-exercise', $workoutTemplate->id),
                'inputName' => 'exercise_name',
                'hiddenFields' => []
            ],
            'items' => $items,
            'filterPlaceholder' => 'Search exercises...'
        ];
    }

    /**
     * Apply a template to a specific date
     */
    public function apply(Request $request, WorkoutTemplate $workoutTemplate)
    {
        $this->authorize('view', $workoutTemplate);

        $date = $request->input('date') 
            ? Carbon::parse($request->input('date'))
            : Carbon::today();
        
        $workoutTemplate->applyToDate($date, Auth::user());

        return redirect()
            ->route('mobile-entry.lifts', ['date' => $date->toDateString()])
            ->with('success', 'Template "' . $workoutTemplate->name . '" applied!');
    }

    /**
     * Show templates for applying to a specific date
     */
    public function browse(Request $request)
    {
        $date = $request->input('date', Carbon::today()->toDateString());
        
        $templates = WorkoutTemplate::where('user_id', Auth::id())
            ->withCount('exercises')
            ->orderBy('name')
            ->get();

        $components = [];

        // Title
        $components[] = C::title('Apply Template')
            ->subtitle('Choose a template for ' . Carbon::parse($date)->format('M j, Y'))
            ->build();

        // Table of templates with apply links
        if ($templates->isNotEmpty()) {
            $tableBuilder = C::table();

            foreach ($templates as $template) {
                $line1 = $template->name;
                $line2 = $template->exercises_count . ' exercises';
                $line3 = $template->description ? substr($template->description, 0, 50) : null;

                // Use custom "Apply" button
                $applyUrl = route('workout-templates.apply', $template->id) . '?date=' . $date;
                
                $tableBuilder->row(
                    $template->id,
                    $line1,
                    $line2,
                    $line3
                )
                ->linkAction('fa-check', $applyUrl, 'Apply template', 'btn-log-now')
                ->add();
            }

            $components[] = $tableBuilder->build();
        } else {
            $components[] = C::messages()
                ->info('No templates yet. Create your first template!')
                ->build();
        }

        $data = ['components' => $components];
        return view('mobile-entry.flexible', compact('data'));
    }
}
