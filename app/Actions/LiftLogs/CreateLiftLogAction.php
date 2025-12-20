<?php

namespace App\Actions\LiftLogs;

use App\Events\LiftLogged;
use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\User;
use App\Services\ExerciseTypes\ExerciseTypeFactory;
use App\Services\ExerciseTypes\Exceptions\InvalidExerciseDataException;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CreateLiftLogAction
{
    public function execute(Request $request, User $user): array
    {
        $exercise = Exercise::find($request->input('exercise_id'));
        
        // Validate the request
        $this->validateRequest($request, $exercise, $user);
        
        // Process the logged time
        $loggedAt = $this->processLoggedTime($request);
        
        // Create the lift log
        $liftLog = $this->createLiftLog($request, $user, $loggedAt);
        
        // Dispatch event
        LiftLogged::dispatch($liftLog);
        
        // Process and create lift sets
        $this->createLiftSets($request, $liftLog, $exercise);
        
        // Check if this is a PR
        $isPR = $this->checkIfPR($liftLog, $exercise, $user);
        
        return [
            'liftLog' => $liftLog,
            'isPR' => $isPR,
            'successMessage' => $this->generateSuccessMessage(
                $exercise, 
                $request->input('weight'), 
                $request->input('reps'), 
                $request->input('rounds'), 
                $request->input('band_color'), 
                $isPR
            )
        ];
    }
    
    private function validateRequest(Request $request, ?Exercise $exercise, User $user): void
    {
        // Base validation rules
        $rules = [
            'exercise_id' => 'required|exists:exercises,id',
            'comments' => 'nullable|string',
            'date' => 'nullable|date', // Make date optional - will default to today if not provided
            'logged_at' => 'nullable|date_format:H:i',
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
        // If no date provided, default to today (this handles the stale page fix)
        $loggedAtDate = $request->input('date') 
            ? Carbon::parse($request->input('date'))
            : Carbon::today();
        
        // If no time provided (mobile entry), use current time but ensure it stays within the selected date
        if ($request->has('logged_at') && $request->input('logged_at')) {
            $loggedAt = $loggedAtDate->setTimeFromTimeString($request->input('logged_at'));
        } else {
            // Use current time, but if we're logging for a different date, use a safe default time
            $currentTime = now();
            if ($loggedAtDate->toDateString() === $currentTime->toDateString()) {
                // Same date - use current time
                $loggedAt = $loggedAtDate->setTime($currentTime->hour, $currentTime->minute);
            } else {
                // Different date - use a safe default time (12:00 PM) to avoid date boundary issues
                $loggedAt = $loggedAtDate->setTime(12, 0);
            }
        }
        
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
    
    private function createLiftLog(Request $request, User $user, Carbon $loggedAt): LiftLog
    {
        return LiftLog::create([
            'exercise_id' => $request->input('exercise_id'),
            'comments' => $request->input('comments'),
            'logged_at' => $loggedAt,
            'user_id' => $user->id,
            'workout_id' => $request->input('workout_id'),
        ]);
    }
    
    private function createLiftSets(Request $request, LiftLog $liftLog, Exercise $exercise): void
    {
        $reps = $request->input('reps');
        $rounds = $request->input('rounds');

        // Use exercise type strategy to process lift data
        $exerciseTypeStrategy = ExerciseTypeFactory::create($exercise);
        
        try {
            $liftData = $exerciseTypeStrategy->processLiftData([
                'weight' => $request->input('weight'),
                'band_color' => $request->input('band_color'),
                'reps' => $reps,
                'notes' => $request->input('comments'),
            ]);
        } catch (InvalidExerciseDataException $e) {
            // Delete the created lift log since data processing failed
            $liftLog->delete();
            throw $e;
        }

        for ($i = 0; $i < $rounds; $i++) {
            $liftLog->liftSets()->create([
                'weight' => $liftData['weight'] ?? 0,
                'reps' => $liftData['reps'],
                'notes' => $liftData['notes'],
                'band_color' => $liftData['band_color'],
            ]);
        }
    }
    
    private function checkIfPR(LiftLog $liftLog, Exercise $exercise, User $user): bool
    {
        $strategy = $exercise->getTypeStrategy();
        
        // Only exercises that support 1RM calculation can have PRs
        if (!$strategy->canCalculate1RM()) {
            return false;
        }
        
        // Get all previous lift logs for this exercise (before this one)
        $previousLogs = LiftLog::where('exercise_id', $exercise->id)
            ->where('user_id', $user->id)
            ->where('logged_at', '<', $liftLog->logged_at)
            ->with('liftSets')
            ->orderBy('logged_at', 'asc')
            ->get();
        
        // Calculate the best estimated 1RM from the current log
        $currentBest1RM = 0;
        $isRepSpecificPR = false;
        $tolerance = 0.1;
        
        foreach ($liftLog->liftSets as $set) {
            if ($set->weight > 0 && $set->reps > 0) {
                try {
                    $estimated1RM = $strategy->calculate1RM($set->weight, $set->reps, $liftLog);
                    if ($estimated1RM > $currentBest1RM) {
                        $currentBest1RM = $estimated1RM;
                    }
                    
                    // For low-rep sets (1-5 reps), check if this is a rep-specific PR
                    if ($set->reps <= 5) {
                        $maxWeightForReps = 0;
                        
                        // Find the max weight previously lifted for this rep count
                        foreach ($previousLogs as $prevLog) {
                            foreach ($prevLog->liftSets as $prevSet) {
                                if ($prevSet->reps == $set->reps && $prevSet->weight > $maxWeightForReps) {
                                    $maxWeightForReps = $prevSet->weight;
                                }
                            }
                        }
                        
                        // Check if current weight beats previous max for this rep count
                        if ($set->weight > $maxWeightForReps + $tolerance) {
                            $isRepSpecificPR = true;
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
        
        // If this is the first log, it's a PR
        if ($previousLogs->isEmpty()) {
            return $currentBest1RM > 0;
        }
        
        // Find the best estimated 1RM from all previous logs
        $previousBest1RM = 0;
        foreach ($previousLogs as $log) {
            foreach ($log->liftSets as $set) {
                if ($set->weight > 0 && $set->reps > 0) {
                    try {
                        $estimated1RM = $strategy->calculate1RM($set->weight, $set->reps, $log);
                        if ($estimated1RM > $previousBest1RM) {
                            $previousBest1RM = $estimated1RM;
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }
        }
        
        // This is a PR if EITHER:
        // 1. It's a rep-specific PR (for 1-5 reps)
        // 2. OR it beats the overall estimated 1RM
        $beats1RM = $currentBest1RM > $previousBest1RM + $tolerance;
        
        return $isRepSpecificPR || $beats1RM;
    }
    
    private function generateSuccessMessage(Exercise $exercise, $weight, int $reps, int $rounds, ?string $bandColor = null, bool $isPR = false): string
    {
        // Get display name (alias if exists, otherwise title)
        $aliasService = app(\App\Services\ExerciseAliasService::class);
        $exerciseTitle = $aliasService->getDisplayName($exercise, auth()->user());
        
        // Use strategy pattern to format workout description
        $strategy = $exercise->getTypeStrategy();
        $workoutDescription = $strategy->formatSuccessMessageDescription($weight, $reps, $rounds, $bandColor);
        
        // Get celebratory messages from config and replace placeholders
        $celebrationTemplates = config('mobile_entry_messages.success.lift_logged');
        $randomTemplate = $celebrationTemplates[array_rand($celebrationTemplates)];
        
        // Replace placeholders in the template
        $message = str_replace([':exercise', ':details'], [$exerciseTitle, $workoutDescription], $randomTemplate);
        
        // Add PR indicator if this is a personal record
        if ($isPR) {
            $message .= ' 🎉 NEW PR!';
        }
        
        return $message;
    }
}