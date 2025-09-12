<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\LiftLog;
use Illuminate\Support\Facades\Auth;

class ExerciseService
{
    public function getTopExercises(int $limit = 5)
    {
        $topExercisesIds = LiftLog::select('exercise_id')
            ->where('user_id', Auth::id())
            ->groupBy('exercise_id')
            ->orderByRaw('COUNT(*) DESC')
            ->limit($limit)
            ->pluck('exercise_id');

        return Exercise::whereIn('id', $topExercisesIds)
            ->withCount('liftLogs')
            ->orderBy('lift_logs_count', 'desc')
            ->get();
    }

    public function getDisplayExercises(int $limit = 5)
    {
        $topExercises = $this->getTopExercises($limit); // Get top N exercises based on logs

        // If we have enough top exercises, just return them
        if ($topExercises->count() >= $limit) {
            return $topExercises;
        }

        // If not enough top exercises, fill up with recently created ones
        $needed = $limit - $topExercises->count();
        if ($needed > 0) {
            $topExerciseIds = $topExercises->pluck('id')->toArray();
            $recentExercises = Exercise::where('user_id', Auth::id())
                                        ->whereNotIn('id', $topExerciseIds) // Exclude already selected top exercises
                                        ->orderBy('created_at', 'desc')
                                        ->limit($needed)
                                        ->get();
            // Combine top exercises with recent ones, ensuring uniqueness (though `whereNotIn` should handle this)
            // and maintaining the order of top exercises first.
            return $topExercises->merge($recentExercises)->take($limit);
        }

        return $topExercises; // Should not be reached if $needed > 0
    }
}