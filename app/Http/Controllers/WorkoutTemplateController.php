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
    public function index()
    {
        $templates = WorkoutTemplate::where('user_id', Auth::id())
            ->withCount('exercises')
            ->orderBy('name')
            ->get();

        $components = [];

        // Title
        $components[] = C::title('Workout Templates')
            ->subtitle('Save and reuse your favorite workouts')
            ->build();

        // Create button
        $components[] = C::button('Create New Template')
            ->asLink(route('workout-templates.create'))
            ->build();

        // Table of templates
        if ($templates->isNotEmpty()) {
            $tableBuilder = C::table();

            foreach ($templates as $template) {
                $line1 = $template->name;
                $line2 = $template->exercises_count . ' exercises';
                $line3 = $template->description ? substr($template->description, 0, 50) : null;

                $tableBuilder->row(
                    $template->id,
                    $line1,
                    $line2,
                    $line3,
                    route('workout-templates.edit', $template->id),
                    route('workout-templates.destroy', $template->id)
                )->add();
            }

            $components[] = $tableBuilder->build();
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
    public function edit(WorkoutTemplate $workoutTemplate)
    {
        $this->authorize('update', $workoutTemplate);

        $workoutTemplate->load('exercises.exercise');

        $components = [];

        // Title
        $components[] = C::title($workoutTemplate->name)
            ->subtitle('Edit template')
            ->build();

        // Messages
        if (session('success')) {
            $components[] = C::messages()
                ->success(session('success'))
                ->build();
        }

        // Add Exercise button
        $components[] = C::button('Add Exercise')
            ->ariaLabel('Add exercise to template')
            ->addClass('btn-add-item')
            ->build();

        // Exercise selection list
        $exercises = \App\Models\Exercise::where('user_id', Auth::id())
            ->orderBy('title')
            ->get();

        $itemListBuilder = C::itemList()
            ->filterPlaceholder('Search exercises...')
            ->noResultsMessage('No exercises found.');

        foreach ($exercises as $exercise) {
            $itemListBuilder->item(
                $exercise->id,
                $exercise->title,
                route('workout-templates.add-exercise', [$workoutTemplate->id, 'exercise' => $exercise->id]),
                'Exercise',
                'type-exercise',
                1
            );
        }

        // Create form for new exercises
        $itemListBuilder->createForm(
            route('workout-templates.create-exercise', $workoutTemplate->id),
            'exercise_name',
            []
        );

        $components[] = $itemListBuilder->build();

        // Table of exercises
        if ($workoutTemplate->exercises->isNotEmpty()) {
            $tableBuilder = C::table();

            foreach ($workoutTemplate->exercises as $exercise) {
                $line1 = $exercise->exercise->title;
                $line2 = 'Priority: ' . $exercise->order;
                $line3 = null;

                $tableBuilder->row(
                    $exercise->id,
                    $line1,
                    $line2,
                    $line3,
                    '', // No edit for now
                    route('workout-templates.remove-exercise', [$workoutTemplate->id, $exercise->id])
                )->add();
            }

            $components[] = $tableBuilder->build();
        } else {
            $components[] = C::messages()
                ->info('No exercises yet. Add your first exercise above.')
                ->build();
        }

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
            ->where('user_id', Auth::id())
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

                // Use edit button as "Apply" button
                $applyUrl = route('workout-templates.apply', $template->id) . '?date=' . $date;
                
                $tableBuilder->row(
                    $template->id,
                    $line1,
                    $line2,
                    $line3,
                    $applyUrl, // Apply button (shows as Edit)
                    ''  // No delete
                )->add();
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
