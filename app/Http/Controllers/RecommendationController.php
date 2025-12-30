<?php

namespace App\Http\Controllers;

use App\Services\RecommendationEngine;
use App\Services\ActivityAnalysisService;
use App\Services\ExerciseAliasService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecommendationController extends Controller
{
    public function __construct(
        private RecommendationEngine $recommendationEngine,
        private ActivityAnalysisService $activityAnalysisService,
        private ExerciseAliasService $exerciseAliasService
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

        // Get user activity analysis for use in filters and data augmentation
        $userActivity = $this->activityAnalysisService->analyzeLiftLogs(auth()->id());

        // Get all recommendations (no limit) - automatically respects user's global exercise preference
        $recommendations = $this->recommendationEngine->getRecommendations(auth()->id(), 50);

        // Augment recommendations with last performed date and apply exercise aliases
        foreach ($recommendations as &$recommendation) {
            $recommendation['days_since_performed'] = $userActivity->getDaysSinceExercisePerformed($recommendation['exercise']->id);
            
            // Apply exercise alias to display name
            $displayName = $this->exerciseAliasService->getDisplayName($recommendation['exercise'], auth()->user());
            $recommendation['exercise']->title = $displayName;
        }
        unset($recommendation); // Unset reference

        // Get recent exercise IDs for the "Logged Only" filter
        $recentExerciseIds = [];
        if ($showLoggedOnly) {
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

        // Note: MobileLiftForm is deprecated - exercises are now logged directly via lift-logs/create
        // No need to track which exercises are "in today's program" anymore
        $todayProgramExercises = collect(); // Empty collection for backward compatibility

        return view('recommendations.index', compact(
            'recommendations',
            'movementArchetypes',
            'difficultyLevels',
            'movementArchetype',
            'difficultyLevel',
            'showLoggedOnly',
            'todayProgramExercises'
        ));
    }
}
