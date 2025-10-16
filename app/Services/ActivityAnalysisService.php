<?php

namespace App\Services;

use App\Models\LiftLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ActivityAnalysisService
{
    /**
     * Analyze a user's lift logs from the last 31 days
     */
    public function analyzeLiftLogs(int $userId): UserActivityAnalysis
    {
        $analysisDate = Carbon::now();
        $startDate = $analysisDate->copy()->subDays(31);
        
        // Get user's lift logs from the last 31 days with exercise intelligence
        $liftLogs = LiftLog::where('user_id', $userId)
            ->where('logged_at', '>=', $startDate)
            ->with(['exercise.intelligence', 'liftSets'])
            ->get();
        
        $muscleWorkload = $this->calculateMuscleWorkload($liftLogs);
        $movementArchetypes = $this->identifyMovementPatterns($liftLogs);
        $recentExercises = $this->findRecentExercises($liftLogs);
        
        // Get muscle last worked dates for more accurate day calculations
        $muscleLastWorked = $this->getMuscleLastWorkedDates($liftLogs);
        
        return new UserActivityAnalysis(
            muscleWorkload: $muscleWorkload,
            movementArchetypes: $movementArchetypes,
            recentExercises: $recentExercises,
            analysisDate: $analysisDate,
            muscleLastWorked: $muscleLastWorked
        );
    }

    /**
     * Calculate muscle workload based on exercise intelligence data
     * Returns array with muscle names as keys and workload scores (0.0-1.0) as values
     */
    public function calculateMuscleWorkload(Collection $liftLogs): array
    {
        $muscleWorkload = [];
        
        foreach ($liftLogs as $liftLog) {
            $intelligence = $liftLog->exercise->intelligence;
            
            if (!$intelligence || !isset($intelligence->muscle_data['muscles'])) {
                continue;
            }
            
            $daysSinceLog = $liftLog->logged_at->diffInDays(Carbon::now());
            $totalSets = $liftLog->liftSets->count();
            
            // Calculate base intensity based on sets and recency
            $baseIntensity = min(1.0, $totalSets / 5.0); // Normalize to max 5 sets
            $recencyFactor = max(0.1, 1.0 - ($daysSinceLog / 31.0)); // Decay over 31 days
            
            foreach ($intelligence->muscle_data['muscles'] as $muscle) {
                $muscleName = $muscle['name'];
                
                // Weight the intensity based on muscle role
                $roleMultiplier = match($muscle['role']) {
                    'primary_mover' => 1.0,
                    'synergist' => 0.7,
                    'stabilizer' => 0.4,
                    default => 0.5
                };
                
                $muscleIntensity = $baseIntensity * $recencyFactor * $roleMultiplier;
                
                // Accumulate workload (but cap at 1.0)
                $muscleWorkload[$muscleName] = min(1.0, 
                    ($muscleWorkload[$muscleName] ?? 0.0) + $muscleIntensity
                );
            }
        }
        
        return $muscleWorkload;
    }

    /**
     * Identify movement pattern frequency from lift logs
     * Returns array with archetype names as keys and frequency counts as values
     */
    public function identifyMovementPatterns(Collection $liftLogs): array
    {
        $archetypeFrequency = [];
        
        foreach ($liftLogs as $liftLog) {
            $intelligence = $liftLog->exercise->intelligence;
            
            if (!$intelligence) {
                continue;
            }
            
            $archetype = $intelligence->movement_archetype;
            $archetypeFrequency[$archetype] = ($archetypeFrequency[$archetype] ?? 0) + 1;
        }
        
        return $archetypeFrequency;
    }

    /**
     * Find recently performed exercises (exercise IDs)
     * Returns array of exercise IDs that were performed in the analysis period
     */
    public function findRecentExercises(Collection $liftLogs): array
    {
        return $liftLogs->pluck('exercise_id')->unique()->values()->toArray();
    }

    /**
     * Get the last worked date for each muscle
     * Returns array with muscle names as keys and Carbon dates as values
     */
    private function getMuscleLastWorkedDates(Collection $liftLogs): array
    {
        $muscleLastWorked = [];
        
        foreach ($liftLogs as $liftLog) {
            $intelligence = $liftLog->exercise->intelligence;
            
            if (!$intelligence || !isset($intelligence->muscle_data['muscles'])) {
                continue;
            }
            
            foreach ($intelligence->muscle_data['muscles'] as $muscle) {
                $muscleName = $muscle['name'];
                
                // Track the most recent workout for this muscle
                if (!isset($muscleLastWorked[$muscleName]) || 
                    $liftLog->logged_at->gt($muscleLastWorked[$muscleName])) {
                    $muscleLastWorked[$muscleName] = $liftLog->logged_at;
                }
            }
        }
        
        return $muscleLastWorked;
    }
}