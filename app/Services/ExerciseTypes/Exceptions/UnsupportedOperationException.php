<?php

namespace App\Services\ExerciseTypes\Exceptions;

/**
 * Exception thrown when an operation is not supported by a specific exercise type
 */
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
     * Create exception for unsupported chart generation
     */
    public static function forChart(string $exerciseType, string $chartType): self
    {
        return new self("Chart type '{$chartType}' not supported for {$exerciseType} exercises");
    }

    /**
     * Create exception for unsupported progression calculation
     */
    public static function forProgression(string $exerciseType): self
    {
        return new self("Progression calculation not supported for {$exerciseType} exercises");
    }

    /**
     * Create exception for generic unsupported operation
     */
    public static function forOperation(string $operation, string $exerciseType): self
    {
        return new self("Operation '{$operation}' not supported for {$exerciseType} exercises");
    }
}