<?php

namespace App\Http\Controllers;

use App\Services\RecommendationEngine;
use App\Services\ActivityAnalysisService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Models\Program;

class RecommendationController extends Controller
{
    public function __construct(
        private RecommendationEngine $recommendationEngine,
        private ActivityAnalysisService $activityAnalysisService
    ) {}

    /**
     * Display the recommendations page with proper URL parameter handling and state management
     */
    public function index(Request $request): View
    {
        // Validate and sanitize URL parameters for filter state management
        $validated = $request->validate([
            'movement_archetype' => 'nullable|in:push,pull,squat,hinge,carry,core',
            'difficulty_level' => 'nullable|integer|min:1|max:5',
            'show_logged_only' => 'nullable|boolean',
        ]);

        // Extract filter values from validated request for state management
        $movementArchetype = $validated['movement_archetype'] ?? null;
        $difficultyLevel = isset($validated['difficulty_level']) ? (int)$validated['difficulty_level'] : null;
        $showLoggedOnly = $validated['show_logged_only'] ?? true;

        // Get all recommendations (no limit) - automatically respects user's global exercise preference
        $recommendations = $this->recommendationEngine->getRecommendations(auth()->id(), 50);

        // Get recent exercise IDs for the "Logged Only" filter
        $recentExerciseIds = [];
        if ($showLoggedOnly) {
            $userActivity = $this->activityAnalysisService->analyzeLiftLogs(auth()->id());
            $recentExerciseIds = $userActivity->recentExercises;
        }

        // Apply filters if provided - integrated filtering logic
        if ($movementArchetype || $difficultyLevel || $showLoggedOnly) {
            $recommendations = array_filter($recommendations, function ($recommendation) use ($movementArchetype, $difficultyLevel, $showLoggedOnly, $recentExerciseIds) {
                $intelligence = $recommendation['intelligence'];

                // Filter by movement archetype
                if ($movementArchetype && $intelligence->movement_archetype !== $movementArchetype) {
                    return false;
                }

                // Filter by difficulty level
                if ($difficultyLevel && $intelligence->difficulty_level !== $difficultyLevel) {
                    return false;
                }

                // Filter by recently logged exercises
                if ($showLoggedOnly && !in_array($recommendation['exercise']->id, $recentExerciseIds)) {
                    return false;
                }

                return true;
            });
        }

        // Get available filter options for the UI
        $movementArchetypes = ['push', 'pull', 'squat', 'hinge', 'carry', 'core'];
        $difficultyLevels = [1, 2, 3, 4, 5];

        // Get exercises already in today's program, mapped by exercise_id to program_id
        $todayProgramExercises = \App\Models\Program::where('user_id', auth()->id())
            ->whereDate('date', \Carbon\Carbon::today())
            ->get()
            ->keyBy('exercise_id')
            ->map(fn($program) => $program->id); // Map exercise_id to program_id

        return view('recommendations.index', compact(
            'recommendations',
            'movementArchetypes',
            'difficultyLevels',
            'movementArchetype',
            'difficultyLevel',
            'showLoggedOnly',
            'todayProgramExercises' // Changed variable name
        ));
    }


}