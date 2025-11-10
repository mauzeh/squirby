<?php

namespace App\Services\MobileEntry;

use App\Models\LiftLog;
use App\Models\Exercise;
use Carbon\Carbon;

class LiftDataCacheService
{
    protected array $lastSessionCache = [];
    protected array $recentExerciseIdsCache = [];

    /**
     * Get last session data for multiple exercises, with caching
     * 
     * @param array $exerciseIds
     * @param Carbon $beforeDate
     * @param int $userId
     * @return array Keyed by exercise_id
     */
    public function getLastSessionData(array $exerciseIds, Carbon $beforeDate, int $userId): array
    {
        $cacheKey = $this->generateCacheKey('last_session', $userId, $beforeDate->toDateString());
        
        if (isset($this->lastSessionCache[$cacheKey])) {
            // Return only the requested exercise IDs from cache
            return array_intersect_key($this->lastSessionCache[$cacheKey], array_flip($exerciseIds));
        }

        // Fetch data for all requested exercises
        $data = $this->fetchBatchLastSessionData($exerciseIds, $beforeDate, $userId);
        
        // Cache the results
        $this->lastSessionCache[$cacheKey] = $data;
        
        return $data;
    }

    /**
     * Get top recent exercise IDs for a user, with caching
     * 
     * @param int $userId
     * @param Carbon $selectedDate
     * @param int $limit
     * @return array
     */
    public function getTopRecentExerciseIds(int $userId, Carbon $selectedDate, int $limit = 5): array
    {
        $cacheKey = $this->generateCacheKey('recent_exercises', $userId, $selectedDate->toDateString(), $limit);
        
        if (isset($this->recentExerciseIdsCache[$cacheKey])) {
            return $this->recentExerciseIdsCache[$cacheKey];
        }

        $data = LiftLog::where('user_id', $userId)
            ->where('logged_at', '<', $selectedDate->toDateString())
            ->where('logged_at', '>=', $selectedDate->copy()->subDays(30))
            ->select('exercise_id')
            ->groupBy('exercise_id')
            ->orderByRaw('MAX(logged_at) DESC')
            ->pluck('exercise_id')
            ->toArray();

        $this->recentExerciseIdsCache[$cacheKey] = $data;
        
        return $data;
    }

    /**
     * Determine item type using cached data
     * 
     * @param Exercise $exercise
     * @param int $userId
     * @param array $recentExerciseIds
     * @return array
     */
    public function determineItemType(Exercise $exercise, int $userId, array $recentExerciseIds): array
    {
        // Check if exercise is in the top recent exercises
        $isTopRecent = in_array($exercise->id, $recentExerciseIds);

        // Check if it's a user's custom exercise
        $isCustom = $exercise->user_id === $userId;

        // Determine type based on priority: recent > custom > regular
        if ($isTopRecent) {
            return $this->getItemTypeConfig('recent');
        } elseif ($isCustom) {
            return $this->getItemTypeConfig('custom');
        } else {
            return $this->getItemTypeConfig('regular');
        }
    }

    /**
     * Get all cached data needed for mobile entry in a single call
     * 
     * @param int $userId
     * @param Carbon $selectedDate
     * @param array $exerciseIds
     * @return array
     */
    public function getAllCachedData(int $userId, Carbon $selectedDate, array $exerciseIds = []): array
    {
        return [
            'lastSessionData' => !empty($exerciseIds) ? $this->getLastSessionData($exerciseIds, $selectedDate, $userId) : [],
            'recentExerciseIds' => $this->getTopRecentExerciseIds($userId, $selectedDate, 5),
        ];
    }

    /**
     * Clear all cached data (useful for testing or when data changes)
     */
    public function clearCache(): void
    {
        $this->lastSessionCache = [];
        $this->recentExerciseIdsCache = [];
    }

    /**
     * Clear cache for a specific user and date
     */
    public function clearCacheForUser(int $userId, Carbon $date = null): void
    {
        $dateString = $date ? $date->toDateString() : '';
        
        foreach ($this->lastSessionCache as $key => $value) {
            if (str_contains($key, "user_{$userId}") && (empty($dateString) || str_contains($key, $dateString))) {
                unset($this->lastSessionCache[$key]);
            }
        }
        
        foreach ($this->recentExerciseIdsCache as $key => $value) {
            if (str_contains($key, "user_{$userId}") && (empty($dateString) || str_contains($key, $dateString))) {
                unset($this->recentExerciseIdsCache[$key]);
            }
        }
    }

    /**
     * Fetch last session data from database
     * 
     * @param array $exerciseIds
     * @param Carbon $beforeDate
     * @param int $userId
     * @return array
     */
    protected function fetchBatchLastSessionData(array $exerciseIds, Carbon $beforeDate, int $userId): array
    {
        if (empty($exerciseIds)) {
            return [];
        }

        // Get the most recent log for each exercise using a subquery approach
        $lastLogs = LiftLog::where('user_id', $userId)
            ->whereIn('exercise_id', $exerciseIds)
            ->where('logged_at', '<', $beforeDate->toDateString())
            ->whereIn('id', function ($query) use ($userId, $exerciseIds, $beforeDate) {
                $query->select(\DB::raw('MAX(id)'))
                    ->from('lift_logs')
                    ->where('user_id', $userId)
                    ->whereIn('exercise_id', $exerciseIds)
                    ->where('logged_at', '<', $beforeDate->toDateString())
                    ->groupBy('exercise_id');
            })
            ->with(['liftSets'])
            ->get();

        $results = [];
        
        foreach ($lastLogs as $lastLog) {
            if ($lastLog->liftSets->isEmpty()) {
                continue;
            }
            
            $firstSet = $lastLog->liftSets->first();
            
            $results[$lastLog->exercise_id] = [
                'weight' => $firstSet->weight,
                'reps' => $firstSet->reps,
                'sets' => $lastLog->liftSets->count(),
                'date' => $lastLog->logged_at->format('M j'),
                'comments' => $lastLog->comments,
                'band_color' => $firstSet->band_color
            ];
        }
        
        return $results;
    }

    /**
     * Generate cache key
     */
    protected function generateCacheKey(string $type, int $userId, string $date, ...$additional): string
    {
        $parts = [$type, "user_{$userId}", "date_{$date}"];
        foreach ($additional as $part) {
            $parts[] = $part;
        }
        return implode('_', $parts);
    }

    /**
     * Get item type configuration
     */
    protected function getItemTypeConfig(string $typeKey): array
    {
        $itemTypes = [
            'recent' => [
                'label' => 'Recent',
                'cssClass' => 'recent',
                'priority' => 1
            ],
            'custom' => [
                'label' => 'My Exercise',
                'cssClass' => 'custom',
                'priority' => 2
            ],
            'regular' => [
                'label' => 'Available',
                'cssClass' => 'regular',
                'priority' => 3
            ]
        ];

        return $itemTypes[$typeKey] ?? $itemTypes['regular'];
    }
}