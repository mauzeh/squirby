<?php

namespace App\View\Components;

use App\Models\Exercise;
use App\Services\ExerciseTypes\ExerciseTypeFactory;
use Illuminate\View\Component;

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
        
        // Get form fields and validation rules from strategy
        if ($this->exercise->exists) {
            $strategy = $this->exercise->getTypeStrategy();
            $this->formFields = $strategy->getFormFields();
            $this->validationRules = $strategy->getValidationRules();
        } else {
            // For new exercises, show all possible fields
            $this->formFields = ['title', 'description', 'band_type', 'is_bodyweight'];
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
        return in_array($field, $this->formFields) || !$this->exercise->exists;
    }

    /**
     * Get available band types
     */
    public function getBandTypes(): array
    {
        return [
            '' => 'None',
            'resistance' => 'Resistance',
            'assistance' => 'Assistance'
        ];
    }
}