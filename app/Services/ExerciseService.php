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
}
