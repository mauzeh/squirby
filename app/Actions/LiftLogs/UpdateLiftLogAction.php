<?php

namespace App\Actions\LiftLogs;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\User;
use App\Services\ExerciseTypes\ExerciseTypeFactory;
use App\Services\ExerciseTypes\Exceptions\InvalidExerciseDataException;
use Carbon\Carbon;
use Illuminate\Http\Request;

class UpdateLiftLogAction
{
    public function execute(Request $request, LiftLog $liftLog, User $user): LiftLog
    {
        // Authorize the user can update this lift log
        if ($liftLog->user_id !== $user->id) {
            abort(403, 'Unauthorized action.');
        }

        $exercise = Exercise::find($request->input('exercise_id'));
        
        // Validate the request
        $this->validateRequest($request, $exercise, $user);
        
        // Process the logged time
        $loggedAt = $this->processLoggedTime($request);
        
        // Update the lift log
        $liftLog->update([
            'exercise_id' => $request->input('exercise_id'),
            'comments' => $request->input('comments'),
            'logged_at' => $loggedAt,
        ]);

        // Delete existing lift sets and create new ones
        $this->updateLiftSets($request, $liftLog, $exercise);
        
        return $liftLog->fresh();
    }
    
    private function validateRequest(Request $request, ?Exercise $exercise, User $user): void
    {
        // Base validation rules
        $rules = [
            'exercise_id' => 'required|exists:exercises,id',
            'comments' => 'nullable|string',
            'date' => 'required|date',
            'logged_at' => 'required|date_format:H:i',
            'reps' => 'required|integer|min:1',
            'rounds' => 'required|integer|min:1',
        ];

        // Use exercise type strategy for validation rules
        if ($exercise) {
            $exerciseTypeStrategy = ExerciseTypeFactory::create($exercise);
            $typeSpecificRules = $exerciseTypeStrategy->getValidationRules($user);
            $rules = array_merge($rules, $typeSpecificRules);
        }

        $request->validate($rules);
    }
    
    private function processLoggedTime(Request $request): Carbon
    {
        $loggedAtDate = Carbon::parse($request->input('date'));
        $loggedAt = $loggedAtDate->setTimeFromTimeString($request->input('logged_at'));
        
        // Round time to nearest 15-minute interval, but ensure we don't cross date boundaries
        $minutes = $loggedAt->minute;
        $remainder = $minutes % 15;
        if ($remainder !== 0) {
            $newLoggedAt = $loggedAt->copy()->addMinutes(15 - $remainder);
            // Only apply rounding if it doesn't change the date
            if ($newLoggedAt->toDateString() === $loggedAtDate->toDateString()) {
                $loggedAt = $newLoggedAt;
            } else {
                // If rounding would cross date boundary, round down instead
                $loggedAt = $loggedAt->subMinutes($remainder);
            }
        }
        
        return $loggedAt;
    }
    
    private function updateLiftSets(Request $request, LiftLog $liftLog, Exercise $exercise): void
    {
        // Delete existing lift sets
        $liftLog->liftSets()->delete();

        // Create new lift sets
        $reps = $request->input('reps');
        $rounds = $request->input('rounds');

        // Use exercise type strategy to process lift data
        $exerciseTypeStrategy = ExerciseTypeFactory::create($exercise);
        
        $liftData = $exerciseTypeStrategy->processLiftData([
            'weight' => $request->input('weight'),
            'band_color' => $request->input('band_color'),
            'reps' => $reps,
            'notes' => $request->input('comments'),
        ]);

        for ($i = 0; $i < $rounds; $i++) {
            $liftLog->liftSets()->create([
                'weight' => $liftData['weight'] ?? 0,
                'reps' => $liftData['reps'],
                'notes' => $liftData['notes'],
                'band_color' => $liftData['band_color'],
            ]);
        }
    }
}