<?php

namespace App\Services\MobileEntry;

use App\Models\Exercise;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Handles exercise creation from mobile entry interface
 */
class ExerciseCreationService
{
    /**
     * Create a new exercise or find existing one
     * 
     * @param int $userId
     * @param string $exerciseName
     * @param string|null $date
     * @return array Result with routeParams, messageType, and message
     */
    public function createOrFindExercise(int $userId, string $exerciseName, ?string $date): array
    {
        $selectedDate = $date ? Carbon::parse($date) : Carbon::today();
        
        // Check if exercise already exists
        $existingExercise = Exercise::where('title', $exerciseName)
            ->availableToUser($userId)
            ->first();
        
        if ($existingExercise) {
            return [
                'routeParams' => $this->buildRouteParams($existingExercise->id, $selectedDate),
                'messageType' => 'info',
                'message' => null
            ];
        }
        
        // Create new exercise
        $exercise = Exercise::create([
            'title' => $exerciseName,
            'user_id' => $userId,
            'exercise_type' => 'regular',
            'canonical_name' => $this->generateUniqueCanonicalName($exerciseName, $userId)
        ]);
        
        return [
            'routeParams' => $this->buildRouteParams($exercise->id, $selectedDate),
            'messageType' => 'success',
            'message' => "Exercise \"{$exercise->title}\" created! Now log your first set."
        ];
    }
    
    /**
     * Build route parameters for lift log creation
     */
    private function buildRouteParams(int $exerciseId, Carbon $selectedDate): array
    {
        $params = [
            'exercise_id' => $exerciseId,
            'redirect_to' => 'mobile-entry-lifts'
        ];
        
        if (!$selectedDate->isToday()) {
            $params['date'] = $selectedDate->toDateString();
        }
        
        return $params;
    }
    
    /**
     * Generate a unique canonical name for an exercise
     */
    private function generateUniqueCanonicalName(string $title, int $userId): string
    {
        $baseCanonicalName = Str::slug($title, '_');
        $canonicalName = $baseCanonicalName;
        $counter = 1;
        
        while ($this->canonicalNameExists($canonicalName, $userId)) {
            $canonicalName = $baseCanonicalName . '_' . $counter;
            $counter++;
        }
        
        return $canonicalName;
    }
    
    /**
     * Check if a canonical name already exists for the user
     */
    private function canonicalNameExists(string $canonicalName, int $userId): bool
    {
        return Exercise::where('canonical_name', $canonicalName)
            ->availableToUser($userId)
            ->exists();
    }
}
