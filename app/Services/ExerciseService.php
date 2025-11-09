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

        return Exercise::availableToUser()
            ->whereIn('id', $topExercisesIds)
            ->with(['aliases' => function ($query) {
                $query->where('user_id', Auth::id());
            }])
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

        // If not enough top exercises, fill up with other exercises that have logs
        $needed = $limit - $topExercises->count();
        if ($needed > 0) {
            $topExerciseIds = $topExercises->pluck('id')->toArray();
            
            // Get exercises that have logs but weren't in the top exercises
            $otherExercisesWithLogs = Exercise::availableToUser()
                ->whereNotIn('id', $topExerciseIds)
                ->whereHas('liftLogs', function ($query) {
                    $query->where('user_id', Auth::id());
                })
                ->with(['aliases' => function ($query) {
                    $query->where('user_id', Auth::id());
                }])
                ->withCount('liftLogs')
                ->orderBy('lift_logs_count', 'desc')
                ->limit($needed)
                ->get();
                
            return $topExercises->merge($otherExercisesWithLogs)->take($limit);
        }

        return $topExercises;
    }

    /**
     * Get all exercises that have logs for the current user
     */
    public function getExercisesWithLogs()
    {
        return Exercise::availableToUser()
            ->whereHas('liftLogs', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->with(['aliases' => function ($query) {
                $query->where('user_id', Auth::id());
            }])
            ->orderBy('title', 'asc')
            ->get();
    }
}