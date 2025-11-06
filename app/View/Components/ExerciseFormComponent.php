<?php

namespace App\View\Components;

use App\Models\Exercise;
use App\Services\ExerciseTypes\ExerciseTypeFactory;
use Illuminate\View\Component;

/**
 * Exercise Form Component
 * 
 * A reusable view component for rendering exercise creation and edit forms.
 * Uses the exercise type strategy pattern to determine which form fields
 * should be displayed based on the exercise type.
 * 
 * Features:
 * - Dynamic form field rendering based on exercise type
 * - Strategy-based validation rule integration
 * - Support for both new and existing exercises
 * - Admin-specific features (global exercise creation)
 * 
 * @package App\View\Components
 * @since 1.0.0
 * 
 * @example
 * // In a Blade template
 * <x-exercise-form 
 *     :exercise="$exercise" 
 *     :can-create-global="$canCreateGlobal"
 *     action="{{ route('exercises.store') }}"
 *     method="POST" 
 * />
 */
class ExerciseFormComponent extends Component
{
    public Exercise $exercise;
    public bool $canCreateGlobal;
    public array $formFields;
    public array $validationRules;
    public string $action;
    public string $method;

    /**
     * Create a new component instance.
     */
    public function __construct(
        Exercise $exercise = null,
        bool $canCreateGlobal = false,
        string $action = '',
        string $method = 'POST'
    ) {
        $this->exercise = $exercise ?? new Exercise();
        $this->canCreateGlobal = $canCreateGlobal;
        $this->action = $action;
        $this->method = $method;
        
        // Always show basic fields and exercise type selection
        $this->formFields = ['title', 'description', 'exercise_type'];
        
        // Get validation rules from strategy if exercise exists
        if ($this->exercise->exists) {
            $strategy = $this->exercise->getTypeStrategy();
            $this->validationRules = $strategy->getValidationRules();
        } else {
            $this->validationRules = [];
        }
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render()
    {
        return view('components.exercise-form');
    }

    /**
     * Check if a field should be displayed
     */
    public function shouldShowField(string $field): bool
    {
        return in_array($field, $this->formFields);
    }

    /**
     * Get available exercise types from configuration
     */
    public function getExerciseTypes(): array
    {
        $types = [];
        $typeConfigs = config('exercise_types.types', []);
        
        foreach ($typeConfigs as $key => $config) {
            // Skip deprecated types
            if (isset($config['deprecated']) && $config['deprecated']) {
                continue;
            }
            
            $types[$key] = $this->getExerciseTypeLabel($key);
        }
        
        return $types;
    }

    /**
     * Get human-readable label for exercise type
     */
    private function getExerciseTypeLabel(string $type): string
    {
        $labels = [
            'regular' => 'Regular (Weighted)',
            'bodyweight' => 'Bodyweight',
            'banded_resistance' => 'Banded Resistance',
            'banded_assistance' => 'Banded Assistance',
            'cardio' => 'Cardio'
        ];
        
        return $labels[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }
}