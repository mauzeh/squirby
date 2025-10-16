<?php

namespace App\Http\Controllers;

use App\Services\RecommendationEngine;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

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

        // Apply filters if provided
        if ($movementArchetype || $difficultyLevel) {
            $recommendations = $this->filterRecommendations($recommendations, $movementArchetype, $difficultyLevel);
        }

        // Get available filter options for the UI
        $movementArchetypes = ['push', 'pull', 'squat', 'hinge', 'carry', 'core'];
        $difficultyLevels = [1, 2, 3, 4, 5];

        return view('recommendations.index', compact(
            'recommendations',
            'movementArchetypes',
            'difficultyLevels',
            'movementArchetype',
            'difficultyLevel',
            'count'
        ));
    }

    /**
     * API endpoint for AJAX-based recommendation requests
     */
    public function api(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'movement_archetype' => 'nullable|in:push,pull,squat,hinge,carry,core',
            'difficulty_level' => 'nullable|integer|min:1|max:5',
            'count' => 'nullable|integer|min:1|max:20'
        ]);

        $count = $validated['count'] ?? 5;
        $movementArchetype = $validated['movement_archetype'] ?? null;
        $difficultyLevel = $validated['difficulty_level'] ?? null;

        try {
            // Get base recommendations
            $recommendations = $this->recommendationEngine->getRecommendations(auth()->id(), $count);

            // Apply filters if provided
            if ($movementArchetype || $difficultyLevel) {
                $recommendations = $this->filterRecommendations($recommendations, $movementArchetype, $difficultyLevel);
            }

            // Format for API response
            $formattedRecommendations = array_map(function ($recommendation) {
                return [
                    'exercise' => [
                        'id' => $recommendation['exercise']->id,
                        'title' => $recommendation['exercise']->title,
                        'description' => $recommendation['exercise']->description,
                        'is_bodyweight' => $recommendation['exercise']->is_bodyweight,
                        'band_type' => $recommendation['exercise']->band_type,
                    ],
                    'intelligence' => [
                        'movement_archetype' => $recommendation['intelligence']->movement_archetype,
                        'category' => $recommendation['intelligence']->category,
                        'difficulty_level' => $recommendation['intelligence']->difficulty_level,
                        'primary_mover' => $recommendation['intelligence']->primary_mover,
                        'largest_muscle' => $recommendation['intelligence']->largest_muscle,
                        'recovery_hours' => $recommendation['intelligence']->recovery_hours,
                        'muscle_data' => $recommendation['intelligence']->muscle_data,
                    ],
                    'score' => round($recommendation['score'], 2),
                    'reasoning' => $recommendation['reasoning'],
                ];
            }, $recommendations);

            return response()->json([
                'success' => true,
                'recommendations' => $formattedRecommendations,
                'count' => count($formattedRecommendations),
                'filters' => [
                    'movement_archetype' => $movementArchetype,
                    'difficulty_level' => $difficultyLevel,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate recommendations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Filter recommendations based on movement archetype and difficulty level
     */
    private function filterRecommendations(array $recommendations, ?string $movementArchetype, ?int $difficultyLevel): array
    {
        return array_filter($recommendations, function ($recommendation) use ($movementArchetype, $difficultyLevel) {
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
}