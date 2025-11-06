<?php

namespace App\Services\ExerciseTypes;

use App\Models\LiftLog;
use App\Models\User;
use App\Services\ExerciseTypes\Exceptions\UnsupportedOperationException;

/**
 * Base Exercise Type Strategy
 * 
 * Abstract base class that provides common functionality for all exercise type strategies.
 * Implements default behavior for most ExerciseTypeInterface methods and loads
 * configuration from the config/exercise_types.php file.
 * 
 * Concrete exercise type classes should extend this class and implement the
 * abstract methods: getTypeName(), processLiftData(), and formatWeightDisplay().
 * 
 * @package App\Services\ExerciseTypes
 * @since 1.0.0
 * 
 * @example
 * // Creating a new exercise type
 * class CustomExerciseType extends BaseExerciseType
 * {
 *     public function getTypeName(): string { return 'custom'; }
 *     public function processLiftData(array $data): array { return $data; }
 *     public function formatWeightDisplay(LiftLog $liftLog): string { return '...'; }
 * }
 */
abstract class BaseExerciseType implements ExerciseTypeInterface
{
    /**
     * Configuration array loaded from config/exercise_types.php
     * 
     * @var array
     */
    protected array $config;
    
    /**
     * Initialize the exercise type strategy with configuration
     * 
     * Loads the configuration for this exercise type from the config file
     * based on the type name returned by getTypeName().
     */
    public function __construct()
    {
        $this->config = config('exercise_types.types.' . $this->getTypeName()) ?? [];
    }
    
    /**
     * Get validation rules for lift data based on exercise type
     */
    public function getValidationRules(?User $user = null): array
    {
        return $this->config['validation'] ?? [];
    }
    
    /**
     * Get form fields that should be displayed for this exercise type
     */
    public function getFormFields(): array
    {
        return $this->config['form_fields'] ?? [];
    }
    
    /**
     * Process exercise data according to exercise type rules
     * Default implementation returns data unchanged
     */
    public function processExerciseData(array $data): array
    {
        return $data;
    }
    
    /**
     * Check if this exercise type supports 1RM calculation
     */
    public function canCalculate1RM(): bool
    {
        return $this->config['supports_1rm'] ?? false;
    }
    
    /**
     * Get the chart type appropriate for this exercise type
     */
    public function getChartType(): string
    {
        return $this->config['chart_type'] ?? 'default';
    }
    
    /**
     * Get supported progression types for this exercise type
     */
    public function getSupportedProgressionTypes(): array
    {
        return $this->config['progression_types'] ?? ['linear'];
    }
    
    /**
     * Format 1RM display for lift logs
     * Default implementation throws exception if 1RM not supported
     */
    public function format1RMDisplay(LiftLog $liftLog): string
    {
        if (!$this->canCalculate1RM()) {
            throw UnsupportedOperationException::for1RM($this->getTypeName());
        }
        
        $oneRepMax = $liftLog->one_rep_max;
        return $oneRepMax > 0 ? number_format($oneRepMax, 1) . ' lbs' : '';
    }
    
    /**
     * Format progression suggestion for lift logs
     * Default implementation returns null (no suggestion)
     */
    public function formatProgressionSuggestion(LiftLog $liftLog): ?string
    {
        return null;
    }
    
    /**
     * Get the configuration for this exercise type
     */
    public function getTypeConfig(): array
    {
        return $this->config;
    }
    
    /**
     * Get the type name identifier
     * Must be implemented by concrete classes
     */
    abstract public function getTypeName(): string;
    
    /**
     * Process lift data according to exercise type rules
     * Must be implemented by concrete classes
     */
    abstract public function processLiftData(array $data): array;
    
    /**
     * Format weight display for lift logs
     * Must be implemented by concrete classes
     */
    abstract public function formatWeightDisplay(LiftLog $liftLog): string;
}