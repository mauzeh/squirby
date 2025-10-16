<?php

namespace App\Http\Controllers;

use App\Services\RecommendationEngine;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Models\Program;

class RecommendationController extends Controller
{
    public function __construct(
        private RecommendationEngine $recommendationEngine
    ) {}

    /**
     * Display the recommendations page
     */
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'movement_archetype' => 'nullable|in:push,pull,squat,hinge,carry,core',
            'difficulty_level' => 'nullable|integer|min:1|max:5',
            'count' => 'nullable|integer|min:1|max:20'
        ]);

        $count = $validated['count'] ?? 5;
        $movementArchetype = $validated['movement_archetype'] ?? null;
        $difficultyLevel = $validated['difficulty_level'] ?? null;

        // Get base recommendations
        $recommendations = $this->recommendationEngine->getRecommendations(auth()->id(), $count);

        // Apply filters if provided - integrated filtering logic
        if ($movementArchetype || $difficultyLevel) {
            $recommendations = array_filter($recommendations, function ($recommendation) use ($movementArchetype, $difficultyLevel) {
                $intelligence = $recommendation['intelligence'];

                // Filter by movement archetype
                if ($movementArchetype && $intelligence->movement_archetype !== $movementArchetype) {
                    return false;
                }

                // Filter by difficulty level
                if ($difficultyLevel && $intelligence->difficulty_level !== $difficultyLevel) {
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
            'count',
            'todayProgramExercises' // Changed variable name
        ));
    }


}