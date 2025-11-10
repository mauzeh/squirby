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
        // Use the same exercise selection system as mobile lift forms
        // This ensures consistency and respects user's exercise visibility settings
        $selectedDate = Carbon::today(); // Use today's date for exercise selection context
        $exerciseSelectionList = $this->liftLogService->generateItemSelectionList(Auth::id(), $selectedDate);
        
        // Modify the selection list for template context (remove date-specific hidden fields)
        if (isset($exerciseSelectionList['createForm']['hiddenFields']['date'])) {
            unset($exerciseSelectionList['createForm']['hiddenFields']['date']);
        }
        
        // Update create form action for template context
        $exerciseSelectionList['createForm']['action'] = route('exercises.store');
        $exerciseSelectionList['createForm']['hiddenFields']['redirect_to'] = 'workout-template-create';
            
        return view('workout-templates.create', compact('exerciseSelectionList'));
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
        
        // Use the same exercise selection system as mobile lift forms
        $selectedDate = Carbon::today();
        $exerciseSelectionList = $this->liftLogService->generateItemSelectionList(Auth::id(), $selectedDate);
        
        // Modify the selection list for template context
        if (isset($exerciseSelectionList['createForm']['hiddenFields']['date'])) {
            unset($exerciseSelectionList['createForm']['hiddenFields']['date']);
        }
        
        // Update create form action for template context
        $exerciseSelectionList['createForm']['action'] = route('exercises.store');
        $exerciseSelectionList['createForm']['hiddenFields']['redirect_to'] = 'workout-template-edit';
        $exerciseSelectionList['createForm']['hiddenFields']['template_id'] = $workoutTemplate->id;
            
        return view('workout-templates.edit', compact('workoutTemplate', 'exerciseSelectionList'));
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
}
