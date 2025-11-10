<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\ExerciseIntelligence;
use App\Models\LiftLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RecommendationEngine
{
    public function __construct(
        private ActivityAnalysisService $activityAnalysisService
    ) {}

    /**
     * Get exercise recommendations for a user
     * 
     * @param int $userId The user to generate recommendations for
     * @param int $count Number of recommendations to return (default: 5)
     * @param bool $showGlobalExercises Whether to include global exercises (optional override)
     * @return array Array of recommended exercises with scores and reasoning
     */
    public function getRecommendations(int $userId, int $count = 5, bool $showGlobalExercises = null): array
    {
        // Analyze user's recent activity
        $userActivity = $this->analyzeUserActivity($userId);
        
        // Get exercises the user has performed in the last 31 days (lookback window)
        // Only recommend exercises the user is familiar with
        $performedExerciseIds = LiftLog::where('user_id', $userId)
            ->where('logged_at', '>=', Carbon::now()->subDays(31))
            ->pluck('exercise_id')
            ->unique()
            ->toArray();

        // If user hasn't performed any exercises, return empty array
        if (empty($performedExerciseIds)) {
            return [];
        }
        
        // Get exercises with intelligence data using the availableToUser scope
        // This automatically respects the user's global exercise preference
        // AND includes global exercises the user has lift logs for
        $exercises = Exercise::availableToUser($userId, $showGlobalExercises)
            ->withIntelligence()
            ->with('intelligence')
            ->whereIn('id', $performedExerciseIds) // Only exercises user has performed
            ->get();
        
        if ($exercises->isEmpty()) {
            return [];
        }
        
        // Find underworked muscles to prioritize
        $underworkedMuscles = $this->findUnderworkedMuscles($userActivity);
        
        \Log::info('Before Recovery Filter', [
            'total_exercises' => $exercises->count(),
            'exercise_ids' => $exercises->pluck('id')->toArray(),
        ]);
        
        // Filter exercises by recovery periods
        $availableExercises = $this->filterByRecovery($exercises, $userActivity);
        
        \Log::info('After Recovery Filter', [
            'available_exercises' => $availableExercises->count(),
            'available_exercise_ids' => $availableExercises->pluck('id')->toArray(),
            'filtered_out_count' => $exercises->count() - $availableExercises->count(),
        ]);
        
        // Score and rank exercises
        $scoredExercises = $this->scoreExercises($availableExercises, $userActivity, $underworkedMuscles);
        
        // Return top recommendations
        return array_slice($scoredExercises, 0, $count);
    }

    /**
     * Analyze user's activity using the ActivityAnalysisService
     */
    private function analyzeUserActivity(int $userId): UserActivityAnalysis
    {
        return $this->activityAnalysisService->analyzeLiftLogs($userId);
    }

    /**
     * Identify muscles that need attention based on workload analysis
     * Returns array of muscle names that are underworked
     */
    private function findUnderworkedMuscles(UserActivityAnalysis $analysis): array
    {
        $underworkedMuscles = [];
        $workloadThreshold = 0.3; // Muscles with workload below this are considered underworked
        
        // Define all trackable muscles
        $allMuscles = [
            // Upper Body
            'pectoralis_major', 'pectoralis_minor',
            'latissimus_dorsi', 'rhomboids', 'middle_trapezius', 'lower_trapezius', 'upper_trapezius',
            'anterior_deltoid', 'medial_deltoid', 'posterior_deltoid',
            'biceps_brachii', 'triceps_brachii', 'brachialis', 'brachioradialis',
            
            // Lower Body
            'rectus_femoris', 'vastus_lateralis', 'vastus_medialis', 'vastus_intermedius',
            'biceps_femoris', 'semitendinosus', 'semimembranosus',
            'gluteus_maximus', 'gluteus_medius', 'gluteus_minimus',
            'gastrocnemius', 'soleus',
            
            // Core
            'rectus_abdominis', 'external_obliques', 'internal_obliques', 'transverse_abdominis',
            'erector_spinae', 'multifidus'
        ];
        
        foreach ($allMuscles as $muscle) {
            $workloadScore = $analysis->getMuscleWorkloadScore($muscle);
            if ($workloadScore < $workloadThreshold) {
                $underworkedMuscles[] = $muscle;
            }
        }
        
        return $underworkedMuscles;
    }

    /**
     * Filter exercises based on recovery periods
     * Allows exercises where the majority of primary movers are recovered
     */
    private function filterByRecovery(Collection $exercises, UserActivityAnalysis $analysis): Collection
    {
        $filteredOut = [];
        
        $result = $exercises->filter(function ($exercise) use ($analysis, &$filteredOut) {
            $intelligence = $exercise->intelligence;
            
            if (!$intelligence || !isset($intelligence->muscle_data['muscles'])) {
                return true; // Include exercises without intelligence data
            }
            
            // Get all primary mover muscles
            $primaryMovers = array_filter($intelligence->muscle_data['muscles'], function($muscle) {
                return $muscle['role'] === 'primary_mover';
            });
            
            if (empty($primaryMovers)) {
                return true; // No primary movers defined, include the exercise
            }
            
            $recoveryHours = $intelligence->recovery_hours;
            $recoveryDays = $recoveryHours / 24;
            
            $totalPrimaryMovers = count($primaryMovers);
            $recoveredCount = 0;
            $inRecoveryMuscles = [];
            
            // Check recovery status for each primary mover
            foreach ($primaryMovers as $muscle) {
                $muscleName = $muscle['name'];
                $daysSinceLastWorkout = $analysis->getDaysSinceLastWorkout($muscleName);
                
                // If muscle was never worked or is past recovery period, it's recovered
                if ($daysSinceLastWorkout === null || $daysSinceLastWorkout >= $recoveryDays) {
                    $recoveredCount++;
                } else {
                    $inRecoveryMuscles[] = [
                        'muscle' => $muscleName,
                        'days_since_workout' => $daysSinceLastWorkout,
                        'recovery_days_needed' => $recoveryDays,
                    ];
                }
            }
            
            // Allow exercise if majority of primary movers are recovered
            $majorityRecovered = $recoveredCount > ($totalPrimaryMovers / 2);
            
            if (!$majorityRecovered) {
                $filteredOut[] = [
                    'exercise_id' => $exercise->id,
                    'exercise_title' => $exercise->title,
                    'total_primary_movers' => $totalPrimaryMovers,
                    'recovered_count' => $recoveredCount,
                    'in_recovery_muscles' => $inRecoveryMuscles,
                ];
                return false;
            }
            
            return true;
        });
        
        if (!empty($filteredOut)) {
            \Log::info('Recovery Filter Details', [
                'analysis_date' => $analysis->analysisDate->toIso8601String(),
                'filtered_out' => array_slice($filteredOut, 0, 5), // Log first 5 for brevity
            ]);
        }
        
        return $result;
    }

    /**
     * Score exercises based on user needs and return sorted array
     * Higher scores indicate better recommendations
     */
    private function scoreExercises(Collection $exercises, UserActivityAnalysis $analysis, array $underworkedMuscles): array
    {
        $scoredExercises = [];
        
        foreach ($exercises as $exercise) {
            $intelligence = $exercise->intelligence;
            
            if (!$intelligence) {
                continue;
            }
            
            $score = $this->calculateExerciseScore($exercise, $intelligence, $analysis, $underworkedMuscles);
            $reasoning = $this->generateRecommendationReasoning($exercise, $intelligence, $analysis, $underworkedMuscles);
            
            $scoredExercises[] = [
                'exercise' => $exercise,
                'intelligence' => $intelligence,
                'score' => $score,
                'reasoning' => $reasoning
            ];
        }
        
        // Sort by score (highest first)
        usort($scoredExercises, fn($a, $b) => $b['score'] <=> $a['score']);
        
        return $scoredExercises;
    }

    /**
     * Calculate a score for an exercise based on user needs
     */
    private function calculateExerciseScore(Exercise $exercise, ExerciseIntelligence $intelligence, UserActivityAnalysis $analysis, array $underworkedMuscles): float
    {
        $score = 0.0;
        
        // Base score for having intelligence data
        $baseScore = 1.0;
        $score += $baseScore;
        
        // Muscle balance scoring - prioritize exercises targeting underworked muscles
        $muscleBalanceScore = $this->calculateMuscleBalanceScore($intelligence, $underworkedMuscles);
        $weightedMuscleScore = $muscleBalanceScore * 3.0; // Weight muscle balance heavily
        $score += $weightedMuscleScore;
        
        // Movement archetype diversity scoring
        $archetypeScore = $this->calculateArchetypeDiversityScore($intelligence, $analysis);
        $weightedArchetypeScore = $archetypeScore * 2.0;
        $score += $weightedArchetypeScore;
        
        // Difficulty progression scoring
        $difficultyScore = $this->calculateDifficultyProgressionScore($intelligence, $analysis);
        $weightedDifficultyScore = $difficultyScore * 1.5;
        $score += $weightedDifficultyScore;
        
        // Penalize recently performed exercises
        $recentExercisePenalty = 1.0;
        if ($analysis->wasExerciseRecentlyPerformed($exercise->id)) {
            $recentExercisePenalty = 0.5; // Reduce score by half for recently performed exercises
            $score *= $recentExercisePenalty;
        }
        
        return $score;
    }

    /**
     * Get detailed scoring breakdown for an exercise (useful for debugging/transparency)
     */
    private function getExerciseScoreBreakdown(Exercise $exercise, ExerciseIntelligence $intelligence, UserActivityAnalysis $analysis, array $underworkedMuscles): array
    {
        $muscleBalanceScore = $this->calculateMuscleBalanceScore($intelligence, $underworkedMuscles);
        $archetypeScore = $this->calculateArchetypeDiversityScore($intelligence, $analysis);
        $difficultyScore = $this->calculateDifficultyProgressionScore($intelligence, $analysis);
        $wasRecentlyPerformed = $analysis->wasExerciseRecentlyPerformed($exercise->id);
        
        return [
            'base_score' => 1.0,
            'muscle_balance_score' => $muscleBalanceScore,
            'weighted_muscle_score' => $muscleBalanceScore * 3.0,
            'archetype_score' => $archetypeScore,
            'weighted_archetype_score' => $archetypeScore * 2.0,
            'difficulty_score' => $difficultyScore,
            'weighted_difficulty_score' => $difficultyScore * 1.5,
            'recently_performed' => $wasRecentlyPerformed,
            'recent_exercise_penalty' => $wasRecentlyPerformed ? 0.5 : 1.0,
        ];
    }

    /**
     * Calculate muscle balance score based on underworked muscles
     */
    private function calculateMuscleBalanceScore(ExerciseIntelligence $intelligence, array $underworkedMuscles): float
    {
        if (empty($underworkedMuscles) || !isset($intelligence->muscle_data['muscles'])) {
            return 0.0;
        }
        
        $score = 0.0;
        $primaryMoverScore = 0.0;
        $totalMuscles = count($intelligence->muscle_data['muscles']);
        
        foreach ($intelligence->muscle_data['muscles'] as $muscle) {
            $muscleName = $muscle['name'];
            
            if (in_array($muscleName, $underworkedMuscles)) {
                // Weight by muscle role
                $roleWeight = match($muscle['role']) {
                    'primary_mover' => 1.0,
                    'synergist' => 0.7,
                    'stabilizer' => 0.4,
                    default => 0.5
                };
                
                $score += $roleWeight;
                
                // Track primary mover score separately for bonus
                if ($muscle['role'] === 'primary_mover') {
                    $primaryMoverScore += 1.0;
                }
            }
        }
        
        // Bonus for exercises that target underworked primary movers
        if ($primaryMoverScore > 0) {
            $score += $primaryMoverScore * 0.5; // 50% bonus for primary mover targeting
        }
        
        // Bonus for compound exercises (exercises targeting multiple underworked muscles)
        $underworkedMuscleCount = 0;
        foreach ($intelligence->muscle_data['muscles'] as $muscle) {
            if (in_array($muscle['name'], $underworkedMuscles)) {
                $underworkedMuscleCount++;
            }
        }
        
        if ($underworkedMuscleCount >= 3) {
            $score += 0.5; // Compound exercise bonus
        }
        
        // Normalize by total muscle count to avoid bias toward exercises with many muscles
        return $totalMuscles > 0 ? $score / $totalMuscles : 0.0;
    }

    /**
     * Calculate archetype diversity score to encourage varied movement patterns
     */
    private function calculateArchetypeDiversityScore(ExerciseIntelligence $intelligence, UserActivityAnalysis $analysis): float
    {
        $archetype = $intelligence->movement_archetype;
        $frequency = $analysis->getArchetypeFrequency($archetype);
        
        // Calculate total archetype usage to understand balance
        $totalArchetypeUsage = array_sum($analysis->movementArchetypes);
        
        if ($totalArchetypeUsage === 0) {
            return 1.0; // No recent activity, all archetypes are equally good
        }
        
        // Calculate relative frequency (percentage of total usage)
        $relativeFrequency = $frequency / $totalArchetypeUsage;
        
        // Ideal distribution would be roughly equal across archetypes
        // With 6 archetypes, ideal would be ~16.7% each
        $idealFrequency = 1.0 / 6.0; // ~0.167
        
        // Score based on how far we are from ideal distribution
        // Lower usage = higher score (encourage balance)
        if ($relativeFrequency <= $idealFrequency) {
            // Under-represented archetype gets full score
            return 1.0;
        } else {
            // Over-represented archetype gets reduced score
            $overUsage = $relativeFrequency - $idealFrequency;
            return max(0.0, 1.0 - ($overUsage * 3.0)); // Penalty for overuse
        }
    }

    /**
     * Calculate difficulty progression score based on user's recent exercise difficulty
     */
    private function calculateDifficultyProgressionScore(ExerciseIntelligence $intelligence, UserActivityAnalysis $analysis): float
    {
        // Calculate average difficulty of recent exercises
        $recentDifficulties = $this->getRecentExerciseDifficulties($analysis);
        
        if (empty($recentDifficulties)) {
            // No recent exercises, prefer moderate difficulty
            $targetDifficulty = 3;
        } else {
            $avgDifficulty = array_sum($recentDifficulties) / count($recentDifficulties);
            
            // Suggest slightly progressive difficulty (but cap at 5)
            $targetDifficulty = min(5, $avgDifficulty + 0.5);
        }
        
        $exerciseDifficulty = $intelligence->difficulty_level;
        $difficultyDifference = abs($exerciseDifficulty - $targetDifficulty);
        
        // Score inversely related to difficulty difference
        // Perfect match = 1.0, each level of difference reduces score by 0.2
        return max(0.0, 1.0 - ($difficultyDifference * 0.2));
    }

    /**
     * Get difficulty levels of recent exercises
     */
    private function getRecentExerciseDifficulties(UserActivityAnalysis $analysis): array
    {
        $difficulties = [];
        
        // Get exercises from recent activity and their difficulty levels
        // Use whereIn to fetch all exercises in a single query
        if (empty($analysis->recentExercises)) {
            return $difficulties;
        }
        
        $exercises = Exercise::with('intelligence')
            ->whereIn('id', $analysis->recentExercises)
            ->get();
        
        foreach ($exercises as $exercise) {
            if ($exercise->intelligence) {
                $difficulties[] = $exercise->intelligence->difficulty_level;
            }
        }
        
        return $difficulties;
    }

    /**
     * Generate human-readable reasoning for why an exercise was recommended
     */
    private function generateRecommendationReasoning(Exercise $exercise, ExerciseIntelligence $intelligence, UserActivityAnalysis $analysis, array $underworkedMuscles): array
    {
        $reasons = [];
        
        // Check for underworked muscles
        $targetedUnderworkedMuscles = [];
        if (isset($intelligence->muscle_data['muscles'])) {
            foreach ($intelligence->muscle_data['muscles'] as $muscle) {
                if (in_array($muscle['name'], $underworkedMuscles) && $muscle['role'] === 'primary_mover') {
                    $targetedUnderworkedMuscles[] = str_replace('_', ' ', $muscle['name']);
                }
            }
        }
        
        if (!empty($targetedUnderworkedMuscles)) {
            $reasons[] = 'Targets underworked muscles: ' . implode(', ', $targetedUnderworkedMuscles);
        }
        
        // Check for movement archetype diversity
        $archetype = $intelligence->movement_archetype;
        $frequency = $analysis->getArchetypeFrequency($archetype);
        
        if ($frequency === 0) {
            $reasons[] = 'Introduces new movement pattern: ' . $archetype;
        } elseif ($frequency < 3) {
            $reasons[] = 'Adds variety to ' . $archetype . ' movements';
        }
        
        // Add difficulty information
        $reasons[] = 'Difficulty level: ' . $intelligence->difficulty_level . '/5';
        
        // Add primary muscle information
        if ($intelligence->primary_mover) {
            $reasons[] = 'Primary focus: ' . str_replace('_', ' ', $intelligence->primary_mover);
        }
        
        return $reasons;
    }
}