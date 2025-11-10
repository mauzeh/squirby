<?php

namespace App\Http\Controllers;

use App\Models\WorkoutTemplate;
use App\Models\Exercise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Services\MobileEntry\LiftLogService;
use App\Services\ExerciseAliasService;
use Carbon\Carbon;

class WorkoutTemplateController extends Controller
{
    use AuthorizesRequests;

    protected LiftLogService $liftLogService;
    protected ExerciseAliasService $aliasService;

    public function __construct(LiftLogService $liftLogService, ExerciseAliasService $aliasService)
    {
        $this->liftLogService = $liftLogService;
        $this->aliasService = $aliasService;
    }

    /**
     * Display a listing of the user's templates
     */
    public function index()
    {
        $templates = WorkoutTemplate::forUser(Auth::id())
            ->withCount('exercises')
            ->orderBy('name')
            ->get();
            
        return view('workout-templates.index', compact('templates'));
    }
    
    /**
     * Show the form for creating a new template
     */
    public function create()
    {
        // Clear any existing template exercises from session if starting fresh
        if (!session()->has('_old_input')) {
            session()->forget('template_exercises');
        }
        
        return view('workout-templates.create');
    }
    
    /**
     * Store a newly created template
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'exercises' => 'required|array|min:1',
            'exercises.*' => 'required|exists:exercises,id'
        ], [
            'name.required' => 'Template name is required',
            'exercises.required' => 'Please add at least one exercise',
            'exercises.min' => 'Please add at least one exercise',
            'exercises.*.exists' => 'One or more exercises are invalid'
        ]);
        
        // Verify user has access to all selected exercises
        $exerciseIds = $validated['exercises'];
        $accessibleExercises = Exercise::availableToUser()
            ->whereIn('id', $exerciseIds)
            ->pluck('id')
            ->toArray();
            
        if (count($accessibleExercises) !== count($exerciseIds)) {
            return back()
                ->withErrors(['exercises' => 'You do not have access to one or more selected exercises'])
                ->withInput();
        }
        
        DB::transaction(function () use ($validated) {
            $template = WorkoutTemplate::create([
                'user_id' => Auth::id(),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null
            ]);
            
            // Attach exercises with order
            foreach ($validated['exercises'] as $order => $exerciseId) {
                $template->exercises()->attach($exerciseId, [
                    'order' => $order + 1
                ]);
            }
        });
        
        // Clear session data
        session()->forget('template_exercises');
        session()->forget('template_form_data');
        
        return redirect()->route('workout-templates.index')
            ->with('success', 'Template created successfully');
    }

    
    /**
     * Show the form for editing the specified template
     */
    public function edit(WorkoutTemplate $workoutTemplate)
    {
        $this->authorize('update', $workoutTemplate);
        
        $workoutTemplate->load(['exercises.aliases' => function ($query) {
            $query->where('user_id', Auth::id());
        }]);
        
        // Apply aliases to template exercises for display
        $user = Auth::user();
        foreach ($workoutTemplate->exercises as $exercise) {
            $displayName = $this->aliasService->getDisplayName($exercise, $user);
            $exercise->title = $displayName;
        }
        
        // Initialize session with current exercises if not already set
        if (!session()->has('_old_input')) {
            session()->forget('template_exercises');
        }
            
        return view('workout-templates.edit', compact('workoutTemplate'));
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
            'exercises' => 'required|array|min:1',
            'exercises.*' => 'required|exists:exercises,id'
        ], [
            'name.required' => 'Template name is required',
            'exercises.required' => 'Please add at least one exercise',
            'exercises.min' => 'Please add at least one exercise',
            'exercises.*.exists' => 'One or more exercises are invalid'
        ]);
        
        // Verify user has access to all selected exercises
        $exerciseIds = $validated['exercises'];
        $accessibleExercises = Exercise::availableToUser()
            ->whereIn('id', $exerciseIds)
            ->pluck('id')
            ->toArray();
            
        if (count($accessibleExercises) !== count($exerciseIds)) {
            return back()
                ->withErrors(['exercises' => 'You do not have access to one or more selected exercises'])
                ->withInput();
        }
        
        DB::transaction(function () use ($workoutTemplate, $validated) {
            $workoutTemplate->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null
            ]);
            
            // Detach all existing exercises
            $workoutTemplate->exercises()->detach();
            
            // Attach new exercises with order
            foreach ($validated['exercises'] as $order => $exerciseId) {
                $workoutTemplate->exercises()->attach($exerciseId, [
                    'order' => $order + 1
                ]);
            }
        });
        
        // Clear session data
        session()->forget('template_exercises');
        session()->forget('template_form_data');
        
        return redirect()->route('workout-templates.index')
            ->with('success', 'Template updated successfully');
    }
    
    /**
     * Remove the specified template
     */
    public function destroy(WorkoutTemplate $workoutTemplate)
    {
        $this->authorize('delete', $workoutTemplate);
        
        $workoutTemplate->delete();
        
        return redirect()->route('workout-templates.index')
            ->with('success', 'Template deleted successfully');
    }
    
    /**
     * Show exercise selection page
     */
    public function showExerciseSelection(Request $request)
    {
        $selectedDate = Carbon::today();
        $exerciseSelectionList = $this->liftLogService->generateItemSelectionList(Auth::id(), $selectedDate);
        
        // Modify the selection list for template context
        if (isset($exerciseSelectionList['createForm']['hiddenFields']['date'])) {
            unset($exerciseSelectionList['createForm']['hiddenFields']['date']);
        }
        
        // Update create form action for template context
        $exerciseSelectionList['createForm']['action'] = route('exercises.store');
        $exerciseSelectionList['createForm']['hiddenFields']['redirect_to'] = 'workout-template-' . $request->input('return_to', 'create');
        
        if ($request->input('return_to') === 'edit' && $request->has('template_id')) {
            $exerciseSelectionList['createForm']['hiddenFields']['template_id'] = $request->input('template_id');
        }
        
        // Get form data from session
        $formData = session('template_form_data', [
            'name' => '',
            'description' => '',
            'exercises' => []
        ]);
        
        $currentExercises = $formData['exercises'] ?? [];
        
        // Determine return URL
        $returnTo = $request->input('return_to', 'create');
        $returnUrl = $returnTo === 'edit' && $request->has('template_id')
            ? route('workout-templates.edit', $request->input('template_id'))
            : route('workout-templates.create');
        
        return view('workout-templates.select-exercise', [
            'exerciseSelectionList' => $exerciseSelectionList,
            'currentExercises' => $currentExercises,
            'returnUrl' => $returnUrl,
            'returnTo' => $returnTo,
            'templateId' => $request->input('template_id'),
            'templateName' => $formData['name'] ?? '',
            'templateDescription' => $formData['description'] ?? ''
        ]);
    }
    
    /**
     * Add exercise to template (server-side)
     */
    public function addExercise(Request $request)
    {
        $exerciseId = $request->input('exercise_id');
        
        // Get form data from session
        $formData = session('template_form_data', [
            'name' => '',
            'description' => '',
            'exercises' => []
        ]);
        
        $currentExercises = $formData['exercises'] ?? [];
        
        // Check if exercise already exists
        if (!in_array($exerciseId, $currentExercises)) {
            $currentExercises[] = $exerciseId;
        }
        
        // Update session with new exercise list
        $formData['exercises'] = $currentExercises;
        session(['template_form_data' => $formData]);
        
        // Also store in template_exercises for compatibility
        session(['template_exercises' => $currentExercises]);
        
        // Flash form data for old() helper
        session()->flash('_old_input', [
            'name' => $formData['name'],
            'description' => $formData['description']
        ]);
        
        // Redirect back to form
        $returnTo = $request->input('return_to', 'create');
        if ($returnTo === 'edit' && $request->has('template_id')) {
            return redirect()->route('workout-templates.edit', $request->input('template_id'));
        }
        
        return redirect()->route('workout-templates.create');
    }
    
    /**
     * Remove exercise from template (server-side)
     */
    public function removeExercise(Request $request)
    {
        $currentExercises = json_decode($request->input('exercises', '[]'), true);
        $removeIndex = $request->input('remove_index');
        
        // Remove exercise at index
        if (isset($currentExercises[$removeIndex])) {
            array_splice($currentExercises, $removeIndex, 1);
        }
        
        // Update session
        $formData = session('template_form_data', [
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'exercises' => []
        ]);
        
        $formData['exercises'] = $currentExercises;
        $formData['name'] = $request->input('name');
        $formData['description'] = $request->input('description');
        
        session(['template_form_data' => $formData]);
        session(['template_exercises' => $currentExercises]);
        
        // Flash form data
        session()->flash('_old_input', [
            'name' => $request->input('name'),
            'description' => $request->input('description')
        ]);
        
        // Redirect back to form
        $returnTo = $request->input('return_to', 'create');
        if ($returnTo === 'edit' && $request->has('template_id')) {
            return redirect()->route('workout-templates.edit', $request->input('template_id'));
        }
        
        return redirect()->route('workout-templates.create');
    }
    
    /**
     * Reorder exercises in template (server-side)
     */
    public function reorder(Request $request)
    {
        $currentExercises = json_decode($request->input('exercises', '[]'), true);
        $moveIndex = $request->input('move_index');
        $direction = $request->input('direction');
        
        // Perform the move
        if ($direction === 'up' && $moveIndex > 0) {
            $temp = $currentExercises[$moveIndex];
            $currentExercises[$moveIndex] = $currentExercises[$moveIndex - 1];
            $currentExercises[$moveIndex - 1] = $temp;
        } elseif ($direction === 'down' && $moveIndex < count($currentExercises) - 1) {
            $temp = $currentExercises[$moveIndex];
            $currentExercises[$moveIndex] = $currentExercises[$moveIndex + 1];
            $currentExercises[$moveIndex + 1] = $temp;
        }
        
        // Update session
        $formData = session('template_form_data', [
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'exercises' => []
        ]);
        
        $formData['exercises'] = $currentExercises;
        $formData['name'] = $request->input('name');
        $formData['description'] = $request->input('description');
        
        session(['template_form_data' => $formData]);
        session(['template_exercises' => $currentExercises]);
        
        // Flash form data
        session()->flash('_old_input', [
            'name' => $request->input('name'),
            'description' => $request->input('description')
        ]);
        
        // Redirect back to form
        $returnTo = $request->input('return_to', 'create');
        if ($returnTo === 'edit' && $request->has('template_id')) {
            return redirect()->route('workout-templates.edit', $request->input('template_id'));
        }
        
        return redirect()->route('workout-templates.create');
    }
}
