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
     * Get form field definitions for mobile entry forms
     * Default implementation builds definitions from configuration
     */
    public function getFormFieldDefinitions(array $defaults = [], ?User $user = null): array
    {
        $formFields = $this->getFormFields();
        $labels = $this->getFieldLabels();
        $increments = $this->getFieldIncrements();
        $definitions = [];
        
        foreach ($formFields as $fieldName) {
            $definition = [
                'name' => $fieldName,
                'label' => $labels[$fieldName] ?? ucfirst($fieldName) . ':',
                'type' => $this->getFieldType($fieldName),
                'defaultValue' => $defaults[$fieldName] ?? $this->getDefaultValue($fieldName),
            ];
            
            // Add numeric field properties
            if ($definition['type'] === 'numeric') {
                $definition['increment'] = $increments[$fieldName] ?? 1;
                $definition['min'] = $this->getFieldMin($fieldName);
                $definition['max'] = $this->getFieldMax($fieldName);
            }
            
            // Add select field properties
            if ($definition['type'] === 'select') {
                $definition['options'] = $this->getFieldOptions($fieldName);
            }
            
            $definitions[] = $definition;
        }
        
        return $definitions;
    }
    
    /**
     * Get field labels for this exercise type
     * Default implementation uses standard labels
     */
    public function getFieldLabels(): array
    {
        return $this->config['field_labels'] ?? [
            'weight' => 'Weight (lbs):',
            'reps' => 'Reps:',
            'sets' => 'Sets:',
            'band_color' => 'Band Color:',
        ];
    }
    
    /**
     * Get increment values for numeric fields
     * Default implementation uses standard increments
     */
    public function getFieldIncrements(): array
    {
        return $this->config['field_increments'] ?? [
            'weight' => 5,
            'reps' => 1,
            'sets' => 1,
        ];
    }
    
    /**
     * Format logged item display message for mobile entry
     * Default implementation combines weight display with reps/sets
     */
    public function formatLoggedItemDisplay(LiftLog $liftLog): string
    {
        $weightText = $this->formatWeightDisplay($liftLog);
        $setCount = $liftLog->liftSets->count();
        $firstSet = $liftLog->liftSets->first();
        
        if (!$firstSet) {
            return $weightText;
        }
        
        $repsSetsText = $setCount . ' x ' . $firstSet->reps;
        
        // For bodyweight with no additional weight, just show reps/sets
        if ($this->getTypeName() === 'bodyweight' && $firstSet->weight == 0) {
            return $repsSetsText;
        }
        
        return $weightText . ' × ' . $repsSetsText;
    }
    
    /**
     * Format form message display for mobile entry
     * Default implementation combines weight display with reps/sets using standard terminology
     */
    public function formatFormMessageDisplay(array $lastSession): string
    {
        // Create a mock lift log for formatting
        $mockLiftLog = new \App\Models\LiftLog();
        
        // Create a mock exercise to avoid null pointer errors
        $mockExercise = new \App\Models\Exercise();
        $mockExercise->exercise_type = $this->getTypeName();
        $mockLiftLog->setRelation('exercise', $mockExercise);
        
        $mockLiftLog->setRelation('liftSets', collect([
            (object)[
                'weight' => $lastSession['weight'] ?? 0,
                'reps' => $lastSession['reps'] ?? 0,
                'band_color' => $lastSession['band_color'] ?? null
            ]
        ]));
        
        $resistanceText = $this->formatWeightDisplay($mockLiftLog);
        
        return $resistanceText . ' × ' . $lastSession['reps'] . ' reps × ' . $lastSession['sets'] . ' sets';
    }
    
    /**
     * Format table cell display for workouts table
     * Returns array with primary and secondary text for table cell display
     */
    public function formatTableCellDisplay(LiftLog $liftLog): array
    {
        $weightText = $this->formatWeightDisplay($liftLog);
        $repsText = $liftLog->display_reps . ' x ' . $liftLog->display_rounds;
        
        return [
            'primary' => $weightText,
            'secondary' => $repsText
        ];
    }
    
    /**
     * Format 1RM table cell display
     * Default implementation shows 1RM if supported, otherwise returns N/A
     */
    public function format1RMTableCellDisplay(LiftLog $liftLog): string
    {
        if (!$this->canCalculate1RM()) {
            return 'N/A (' . ucfirst($this->getTypeName()) . ')';
        }
        
        return round($liftLog->one_rep_max) . ' lbs';
    }
    
    /**
     * Get exercise type display name and icon
     * Default implementation returns generic weighted exercise info
     */
    public function getTypeDisplayInfo(): array
    {
        return [
            'icon' => 'fas fa-dumbbell',
            'name' => 'Weighted'
        ];
    }
    
    /**
     * Get chart title for exercise logs page
     * Default implementation returns 1RM Progress if supported, otherwise Volume Progress
     */
    public function getChartTitle(): string
    {
        return $this->canCalculate1RM() ? '1RM Progress' : 'Volume Progress';
    }
    
    /**
     * Format mobile summary display for exercise summary component
     * Default implementation provides standard weight and reps/sets formatting
     */
    public function formatMobileSummaryDisplay(LiftLog $liftLog): array
    {
        $weight = $this->formatWeightDisplay($liftLog);
        $repsSets = $liftLog->display_rounds . ' x ' . $liftLog->display_reps;
        
        // For bodyweight exercises, don't show weight if it's zero
        $showWeight = true;
        if ($this->getTypeName() === 'bodyweight' && $liftLog->display_weight == 0) {
            $showWeight = false;
        }
        
        return [
            'weight' => $weight,
            'repsSets' => $repsSets,
            'showWeight' => $showWeight
        ];
    }
    
    /**
     * Format success message description for lift log creation
     * Default implementation for regular weighted exercises
     */
    public function formatSuccessMessageDescription(?float $weight, int $reps, int $rounds, ?string $bandColor = null): string
    {
        return $weight . ' lbs × ' . $reps . ' reps × ' . $rounds . ' sets';
    }
    
    /**
     * Get field type for a given field name
     * Protected helper method for form field generation
     */
    protected function getFieldType(string $fieldName): string
    {
        $fieldTypes = $this->config['field_types'] ?? [];
        
        if (isset($fieldTypes[$fieldName])) {
            return $fieldTypes[$fieldName];
        }
        
        // Default field types based on field name
        switch ($fieldName) {
            case 'band_color':
                return 'select';
            case 'weight':
            case 'reps':
            case 'sets':
                return 'numeric';
            default:
                return 'text';
        }
    }
    
    /**
     * Get default value for a field
     * Protected helper method for form field generation
     */
    protected function getDefaultValue(string $fieldName)
    {
        $defaults = $this->config['field_defaults'] ?? [];
        
        if (isset($defaults[$fieldName])) {
            return $defaults[$fieldName];
        }
        
        // Standard defaults
        switch ($fieldName) {
            case 'weight':
                return 0;
            case 'reps':
                return 5;
            case 'sets':
                return 3;
            case 'band_color':
                return 'red';
            default:
                return '';
        }
    }
    
    /**
     * Get minimum value for a numeric field
     * Protected helper method for form field generation
     */
    protected function getFieldMin(string $fieldName): int
    {
        $mins = $this->config['field_mins'] ?? [];
        
        if (isset($mins[$fieldName])) {
            return $mins[$fieldName];
        }
        
        // Standard minimums
        switch ($fieldName) {
            case 'weight':
                return 0;
            case 'reps':
            case 'sets':
                return 1;
            default:
                return 0;
        }
    }
    
    /**
     * Get maximum value for a numeric field
     * Protected helper method for form field generation
     */
    protected function getFieldMax(string $fieldName): int
    {
        $maxes = $this->config['field_maxes'] ?? [];
        
        if (isset($maxes[$fieldName])) {
            return $maxes[$fieldName];
        }
        
        // Standard maximums
        switch ($fieldName) {
            case 'weight':
                return 600;
            case 'reps':
                return 100;
            case 'sets':
                return 20;
            default:
                return 1000;
        }
    }
    
    /**
     * Get options for a select field
     * Protected helper method for form field generation
     */
    protected function getFieldOptions(string $fieldName): array
    {
        if ($fieldName === 'band_color') {
            $bandColors = config('bands.colors', []);
            return array_map(function($color) {
                return ['value' => $color, 'label' => ucfirst($color)];
            }, array_keys($bandColors));
        }
        
        return [];
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