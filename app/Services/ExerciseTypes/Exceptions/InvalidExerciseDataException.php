<?php

namespace App\Services\ExerciseTypes\Exceptions;

class InvalidExerciseDataException extends ExerciseTypeException
{
    /**
     * Create exception for invalid field data
     */
    public static function forField(string $field, string $exerciseType, $value = null): self
    {
        $message = "Invalid {$field} for {$exerciseType} exercise";
        
        if ($value !== null) {
            $message .= ": " . json_encode($value);
        }
        
        return new self($message);
    }
    
    /**
     * Create exception for missing required field
     */
    public static function missingField(string $field, string $exerciseType): self
    {
        return new self("Missing required field '{$field}' for {$exerciseType} exercise");
    }
    
    /**
     * Create exception for invalid exercise type configuration
     */
    public static function invalidConfiguration(string $exerciseType, string $reason): self
    {
        return new self("Invalid configuration for {$exerciseType} exercise: {$reason}");
    }
}