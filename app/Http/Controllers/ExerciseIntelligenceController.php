<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\ExerciseIntelligence;
use App\Http\Requests\StoreExerciseIntelligenceRequest;
use App\Http\Requests\UpdateExerciseIntelligenceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ExerciseIntelligenceController extends Controller
{
    use AuthorizesRequests;



    /**
     * Display a listing of exercises with their intelligence data.
     */
    public function index(): View
    {
        $exercises = Exercise::global()
            ->with('intelligence')
            ->orderBy('title', 'asc')
            ->get();

        return view('exercise-intelligence.index', compact('exercises'));
    }

    /**
     * Show the form for creating intelligence data for an exercise.
     */
    public function create(Exercise $exercise): View
    {
        // Ensure the exercise is global
        if (!$exercise->isGlobal()) {
            abort(403, 'Intelligence data can only be added to global exercises.');
        }

        // Check if intelligence already exists
        if ($exercise->hasIntelligence()) {
            return redirect()->route('exercise-intelligence.edit', $exercise->intelligence)
                ->with('info', 'Intelligence data already exists for this exercise. You can edit it here.');
        }

        return view('exercise-intelligence.create', compact('exercise'));
    }

    /**
     * Store newly created intelligence data.
     */
    public function store(StoreExerciseIntelligenceRequest $request, Exercise $exercise): RedirectResponse
    {
        // Ensure the exercise is global
        if (!$exercise->isGlobal()) {
            abort(403, 'Intelligence data can only be added to global exercises.');
        }

        // Check if intelligence already exists
        if ($exercise->hasIntelligence()) {
            return redirect()->route('exercise-intelligence.edit', $exercise->intelligence)
                ->with('error', 'Intelligence data already exists for this exercise.');
        }

        $validated = $request->validated();

        $intelligence = new ExerciseIntelligence($validated);
        $intelligence->exercise_id = $exercise->id;
        $intelligence->save();

        return redirect()->route('exercise-intelligence.index')
            ->with('success', "Intelligence data created successfully for '{$exercise->title}'.");
    }

    /**
     * Show the form for editing intelligence data.
     */
    public function edit(ExerciseIntelligence $intelligence): View
    {
        $intelligence->load('exercise');
        
        return view('exercise-intelligence.edit', compact('intelligence'));
    }

    /**
     * Update the specified intelligence data.
     */
    public function update(UpdateExerciseIntelligenceRequest $request, ExerciseIntelligence $intelligence): RedirectResponse
    {
        $validated = $request->validated();

        $intelligence->update($validated);

        return redirect()->route('exercise-intelligence.index')
            ->with('success', "Intelligence data updated successfully for '{$intelligence->exercise->title}'.");
    }

    /**
     * Remove the specified intelligence data.
     */
    public function destroy(ExerciseIntelligence $intelligence): RedirectResponse
    {
        $exerciseTitle = $intelligence->exercise->title;
        $intelligence->delete();

        return redirect()->route('exercise-intelligence.index')
            ->with('success', "Intelligence data removed successfully for '{$exerciseTitle}'.");
    }

    /**
     * API: Get intelligence data for a specific exercise.
     */
    public function show(Exercise $exercise): JsonResponse
    {
        $intelligence = $exercise->intelligence;
        
        if (!$intelligence) {
            return response()->json([
                'message' => 'No intelligence data found for this exercise.'
            ], 404);
        }

        return response()->json([
            'data' => $intelligence,
            'exercise' => [
                'id' => $exercise->id,
                'title' => $exercise->title,
                'is_global' => $exercise->isGlobal()
            ]
        ]);
    }

    /**
     * API: Validate muscle data structure.
     */
    public function validateMuscleData(Request $request): JsonResponse
    {
        $request->validate([
            'muscle_data' => 'required|array',
            'muscle_data.muscles' => 'required|array|min:1',
            'muscle_data.muscles.*.name' => 'required|string',
            'muscle_data.muscles.*.role' => 'required|in:primary_mover,synergist,stabilizer',
            'muscle_data.muscles.*.contraction_type' => 'required|in:isotonic,isometric'
        ]);

        return response()->json([
            'valid' => true,
            'message' => 'Muscle data structure is valid.'
        ]);
    }

    /**
     * API: Get list of available muscles.
     */
    public function getMusclesList(): JsonResponse
    {
        $muscles = [
            'upper_body' => [
                'chest' => ['pectoralis_major', 'pectoralis_minor'],
                'back' => ['latissimus_dorsi', 'rhomboids', 'middle_trapezius', 'lower_trapezius', 'upper_trapezius'],
                'shoulders' => ['anterior_deltoid', 'medial_deltoid', 'posterior_deltoid'],
                'arms' => ['biceps_brachii', 'triceps_brachii', 'brachialis', 'brachioradialis']
            ],
            'lower_body' => [
                'quadriceps' => ['rectus_femoris', 'vastus_lateralis', 'vastus_medialis', 'vastus_intermedius'],
                'hamstrings' => ['biceps_femoris', 'semitendinosus', 'semimembranosus'],
                'glutes' => ['gluteus_maximus', 'gluteus_medius', 'gluteus_minimus'],
                'calves' => ['gastrocnemius', 'soleus']
            ],
            'core' => [
                'abdominals' => ['rectus_abdominis', 'external_obliques', 'internal_obliques', 'transverse_abdominis'],
                'lower_back' => ['erector_spinae', 'multifidus']
            ]
        ];

        return response()->json(['muscles' => $muscles]);
    }

    /**
     * API: Get list of available movement archetypes.
     */
    public function getArchetypesList(): JsonResponse
    {
        $archetypes = [
            'push' => 'Pushing movements (bench press, overhead press, push-ups)',
            'pull' => 'Pulling movements (rows, pull-ups, deadlifts)',
            'squat' => 'Knee-dominant lower body movements (squats, lunges)',
            'hinge' => 'Hip-dominant movements (deadlifts, hip thrusts, good mornings)',
            'carry' => 'Loaded carries and holds (farmer\'s walks, suitcase carries)',
            'core' => 'Core-specific movements (planks, crunches, Russian twists)'
        ];

        return response()->json(['archetypes' => $archetypes]);
    }
}