<?php

namespace App\Services\ExerciseTypes;

use App\Models\Exercise;
use InvalidArgumentException;

class ExerciseTypeFactory
{
    private static array $strategies = [];
    
    /**
     * Create an exercise type strategy for the given exercise
     */
    public static function create(Exercise $exercise): ExerciseTypeInterface
    {
        $key = self::generateKey($exercise);
        
        if (config('exercise_types.factory.cache_strategies', true) && isset(self::$strategies[$key])) {
            return self::$strategies[$key];
        }
        
        $strategy = self::createStrategy($exercise);
        
        if (config('exercise_types.factory.cache_strategies', true)) {
            self::$strategies[$key] = $strategy;
        }
        
        return $strategy;
    }
    
    /**
     * Create a strategy instance based on exercise properties
     */
    private static function createStrategy(Exercise $exercise): ExerciseTypeInterface
    {
        try {
            // Determine exercise type based on properties
            $typeName = self::determineExerciseType($exercise);
            
            // Get the class from configuration
            $typeConfig = config("exercise_types.types.{$typeName}");
            
            if (!$typeConfig || !isset($typeConfig['class'])) {
                throw new InvalidArgumentException("No configuration found for exercise type: {$typeName}");
            }
            
            $className = $typeConfig['class'];
            
            if (!class_exists($className)) {
                throw new InvalidArgumentException("Exercise type class does not exist: {$className}");
            }
            
            $strategy = new $className();
            
            if (!$strategy instanceof ExerciseTypeInterface) {
                throw new InvalidArgumentException("Exercise type class must implement ExerciseTypeInterface: {$className}");
            }
            
            return $strategy;
            
        } catch (\Exception $e) {
            // Fallback to default type if strategy creation fails
            $fallbackType = config('exercise_types.factory.fallback_type', 'regular');
            $fallbackConfig = config("exercise_types.types.{$fallbackType}");
            
            if ($fallbackConfig && isset($fallbackConfig['class']) && class_exists($fallbackConfig['class'])) {
                $fallbackStrategy = new $fallbackConfig['class']();
                
                if ($fallbackStrategy instanceof ExerciseTypeInterface) {
                    return $fallbackStrategy;
                }
            }
            
            // Last resort: throw the original exception
            throw $e;
        }
    }
    
    /**
     * Determine the exercise type based on exercise properties
     */
    private static function determineExerciseType(Exercise $exercise): string
    {
        // Check for banded exercise
        if ($exercise->band_type) {
            return 'banded';
        }
        
        // Check for bodyweight exercise
        if ($exercise->is_bodyweight) {
            return 'bodyweight';
        }
        
        // Default to regular exercise
        return 'regular';
    }
    
    /**
     * Generate a cache key for the exercise
     */
    private static function generateKey(Exercise $exercise): string
    {
        return sprintf(
            'exercise_%d_%s_%s',
            $exercise->id,
            $exercise->band_type ?? 'null',
            $exercise->is_bodyweight ? 'bodyweight' : 'regular'
        );
    }
    
    /**
     * Clear the strategy cache
     */
    public static function clearCache(): void
    {
        self::$strategies = [];
    }
    
    /**
     * Get all available exercise types from configuration
     */
    public static function getAvailableTypes(): array
    {
        return array_keys(config('exercise_types.types', []));
    }
    
    /**
     * Check if a given exercise type is supported
     */
    public static function isTypeSupported(string $typeName): bool
    {
        $typeConfig = config("exercise_types.types.{$typeName}");
        return $typeConfig && isset($typeConfig['class']) && class_exists($typeConfig['class']);
    }
}