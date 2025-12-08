<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\ExerciseIntelligence;
use App\Models\Workout;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class WorkoutNameGenerator
{
    /**
     * Generate an intelligent workout label based on all exercises in the workout
     */
    public function generateFromWorkout(Workout $workout): string
    {
        $exercises = $workout->exercises()->with('exercise.intelligence')->get();
        
        if ($exercises->count() === 0) {
            return 'Empty Workout';
        }
        
        // Analyze movement patterns and categories
        $archetypes = $this->analyzeArchetypes($exercises);
        $categories = $this->analyzeCategories($exercises);
        
        // If we have intelligence data, always use intelligent labels
        if (!empty($archetypes)) {
            $label = $this->determineLabel($archetypes, $categories, $exercises);
            return $label . ' • ' . $exercises->count() . ($exercises->count() === 1 ? ' exercise' : ' exercises');
        }
        
        // Fallback: For 1-2 exercises without intelligence, just list them
        if ($exercises->count() <= 2) {
            $names = $exercises->pluck('exercise.title')->filter()->toArray();
            if (count($names) === 0) {
                return 'Workout • ' . $exercises->count() . ' exercises';
            }
            return implode(' & ', $names);
        }
        
        // For 3+ exercises without intelligence, use fallback label
        $label = $this->fallbackLabel($exercises);
        return $label . ' • ' . $exercises->count() . ' exercises';
    }
    
    /**
     * Analyze movement archetypes from exercises
     */
    private function analyzeArchetypes(Collection $exercises): array
    {
        $archetypes = [];
        
        foreach ($exercises as $workoutExercise) {
            $intelligence = $workoutExercise->exercise->intelligence ?? null;
            if ($intelligence && $intelligence->movement_archetype) {
                $archetype = $intelligence->movement_archetype;
                $archetypes[$archetype] = ($archetypes[$archetype] ?? 0) + 1;
            }
        }
        
        return $archetypes;
    }
    
    /**
     * Analyze categories from exercises
     */
    private function analyzeCategories(Collection $exercises): array
    {
        $categories = [];
        
        foreach ($exercises as $workoutExercise) {
            $intelligence = $workoutExercise->exercise->intelligence ?? null;
            if ($intelligence && $intelligence->category) {
                $category = $intelligence->category;
                $categories[$category] = ($categories[$category] ?? 0) + 1;
            }
        }
        
        return $categories;
    }
    
    /**
     * Determine the best label based on archetype and category analysis
     */
    private function determineLabel(array $archetypes, array $categories, Collection $exercises): string
    {
        $totalCount = $exercises->count();
        
        // If no intelligence data, use fallback
        if (empty($archetypes)) {
            return $this->fallbackLabel($exercises);
        }
        
        // Count exercises with intelligence data for percentage calculations
        $intelligenceCount = array_sum($archetypes);
        
        // Filter out core movements unless the workout is ONLY core
        $coreCount = $archetypes['core'] ?? 0;
        $nonCoreArchetypes = array_filter($archetypes, fn($key) => $key !== 'core', ARRAY_FILTER_USE_KEY);
        
        // If only core exercises, use core label
        if (empty($nonCoreArchetypes) && $coreCount > 0) {
            return $this->formatArchetypeLabel('core', $categories, $totalCount);
        }
        
        // Use non-core archetypes for analysis
        $archetypesForAnalysis = !empty($nonCoreArchetypes) ? $nonCoreArchetypes : $archetypes;
        
        // Single archetype dominance (80%+) - excluding core
        if (count($archetypesForAnalysis) === 1) {
            $archetype = array_key_first($archetypesForAnalysis);
            return $this->formatArchetypeLabel($archetype, $categories, $totalCount);
        }
        
        // For percentage calculations, use non-core count
        $nonCoreCount = array_sum($nonCoreArchetypes);
        $referenceCount = $nonCoreCount > 0 ? $nonCoreCount : $intelligenceCount;
        
        // Check for leg day first (squat + hinge dominant) - more specific
        $squatCount = $archetypesForAnalysis['squat'] ?? 0;
        $hingeCount = $archetypesForAnalysis['hinge'] ?? 0;
        
        if ($squatCount + $hingeCount >= $referenceCount * 0.7) {
            return $this->formatCategoryLabel('Leg Day', $categories, $totalCount);
        }
        
        // Check for upper body (push + pull)
        $pushCount = $archetypesForAnalysis['push'] ?? 0;
        $pullCount = $archetypesForAnalysis['pull'] ?? 0;
        $upperBodyCount = $pushCount + $pullCount;
        
        if ($upperBodyCount >= $referenceCount * 0.8) {
            return $this->formatCategoryLabel('Upper Body', $categories, $totalCount);
        }
        
        // Check for lower body (any lower body movements)
        $lowerBodyCount = $squatCount + $hingeCount;
        
        if ($lowerBodyCount >= $referenceCount * 0.8) {
            return $this->formatCategoryLabel('Lower Body', $categories, $totalCount);
        }
        
        // Mixed workout - full body
        return $this->formatCategoryLabel('Full Body', $categories, $totalCount);
    }
    
    /**
     * Format label for single archetype workouts
     */
    private function formatArchetypeLabel(string $archetype, array $categories, int $totalCount): string
    {
        $label = match($archetype) {
            'push' => 'Push Day',
            'pull' => 'Pull Day',
            'squat' => 'Leg Day',
            'hinge' => 'Hinge Day',
            'carry' => 'Carry',
            'core' => 'Core',
            default => ucfirst($archetype) . ' Day',
        };
        
        return $this->appendCategory($label, $categories, $totalCount);
    }
    
    /**
     * Format label for body region workouts
     */
    private function formatCategoryLabel(string $baseLabel, array $categories, int $totalCount): string
    {
        return $this->appendCategory($baseLabel, $categories, $totalCount);
    }
    
    /**
     * Append category if it's dominant and not "strength"
     */
    private function appendCategory(string $label, array $categories, int $totalCount): string
    {
        if (empty($categories)) {
            return $label;
        }
        
        // Find dominant category
        arsort($categories);
        $dominantCategory = array_key_first($categories);
        $dominantCount = $categories[$dominantCategory];
        
        // Only append if it's 70%+ dominant and not "strength" (default)
        if ($dominantCount >= $totalCount * 0.7 && $dominantCategory !== 'strength') {
            return $label . ' ' . ucfirst($dominantCategory);
        }
        
        return $label;
    }
    
    /**
     * Fallback label when no intelligence data is available
     */
    private function fallbackLabel(Collection $exercises): string
    {
        // Try to infer from exercise names
        $names = $exercises->pluck('exercise.title')->map(fn($n) => strtolower($n))->toArray();
        $allNames = implode(' ', $names);
        
        // Simple keyword matching
        if (str_contains($allNames, 'squat') || str_contains($allNames, 'leg')) {
            return 'Leg Day';
        }
        
        if (str_contains($allNames, 'bench') || str_contains($allNames, 'press')) {
            return 'Upper Body';
        }
        
        if (str_contains($allNames, 'deadlift') || str_contains($allNames, 'hinge')) {
            return 'Lower Body';
        }
        
        return 'Mixed Workout';
    }
    
    /**
     * Generate an intelligent workout name based on the first exercise (legacy method)
     */
    public function generate(Exercise $exercise): string
    {
        // Try to get exercise intelligence data
        $intelligence = ExerciseIntelligence::where('exercise_id', $exercise->id)->first();
        
        if (!$intelligence) {
            // Fallback to date-based name if no intelligence data
            return 'New Workout - ' . Carbon::now()->format('M j, Y');
        }

        // Combine movement archetype and category for descriptive names
        // e.g., "Hinge Day (Strength)", "Push Day (Cardio)"
        if ($intelligence->movement_archetype && $intelligence->category) {
            return ucfirst($intelligence->movement_archetype) . ' Day (' . ucfirst($intelligence->category) . ')';
        }
        
        // Fallback to just archetype or category if only one is available
        if ($intelligence->movement_archetype) {
            return ucfirst($intelligence->movement_archetype) . ' Day';
        }
        
        if ($intelligence->category) {
            return ucfirst($intelligence->category) . ' Workout';
        }

        // Fallback to date-based name
        return 'New Workout - ' . Carbon::now()->format('M j, Y');
    }
}
