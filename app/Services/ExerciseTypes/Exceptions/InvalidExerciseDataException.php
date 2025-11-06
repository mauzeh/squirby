<?php

namespace App\Services\ExerciseTypes\Exceptions;

/**
 * Exception thrown when invalid data is provided for a specific exercise type
 */
class InvalidExerciseDataException extends ExerciseTypeException
{
    /**
     * Create exception for invalid field data
     */
    public static function forField(string $field, string $exerciseType, ?string $reason = null): self
    {
        $message = "Invalid {$field} for {$exerciseType} exercise";
        if ($reason) {
            $message .= ": {$reason}";
        }
        return new self($message);
    }

    /**
     * Create exception for missing required field
     */
    public static function missingField(string $field, string $exerciseType): self
    {
        return new self("Required field '{$field}' missing for {$exerciseType} exercise");
    }

    /**
     * Create exception for invalid band color
     */
    public static function invalidBandColor(string $color): self
    {
        return new self("Invalid band color '{$color}' for banded exercise");
    }

    /**
     * Create exception for invalid weight value
     */
    public static function invalidWeight(mixed $weight, string $exerciseType): self
    {
        return new self("Invalid weight value '{$weight}' for {$exerciseType} exercise");
    }

    /**
     * Create exception for conflicting exercise data
     */
    public static function conflictingData(string $field1, string $field2, string $exerciseType): self
    {
        return new self("Conflicting data: {$field1} and {$field2} cannot both be set for {$exerciseType} exercise");
    }
}