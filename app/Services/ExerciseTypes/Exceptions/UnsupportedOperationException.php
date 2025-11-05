<?php

namespace App\Services\ExerciseTypes\Exceptions;

class UnsupportedOperationException extends ExerciseTypeException
{
    /**
     * Create exception for unsupported 1RM calculation
     */
    public static function for1RM(string $exerciseType): self
    {
        return new self("1RM calculation not supported for {$exerciseType} exercises");
    }
    
    /**
     * Create exception for unsupported chart type
     */
    public static function forChart(string $exerciseType, string $chartType): self
    {
        return new self("Chart type '{$chartType}' not supported for {$exerciseType} exercises");
    }
    
    /**
     * Create exception for unsupported progression type
     */
    public static function forProgression(string $exerciseType, string $progressionType): self
    {
        return new self("Progression type '{$progressionType}' not supported for {$exerciseType} exercises");
    }
}