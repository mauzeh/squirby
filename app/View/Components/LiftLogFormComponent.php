<?php

namespace App\View\Components;

use App\Models\Exercise;
use App\Models\LiftLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\Component;

/**
 * Lift Log Form Component
 * 
 * A reusable view component for rendering lift log creation and edit forms.
 * Uses the exercise type strategy pattern to determine which form fields
 * should be displayed based on the selected exercise type.
 * 
 * Features:
 * - Dynamic form field rendering based on exercise type
 * - Strategy-based validation rule integration
 * - Support for both new and existing lift logs
 * - Exercise-specific input handling (weight vs band color)
 * - Band color selection for banded exercises
 * 
 * @package App\View\Components
 * @since 1.0.0
 * 
 * @example
 * // In a Blade template
 * <x-lift-log-form 
 *     :lift-log="$liftLog" 
 *     :exercises="$exercises"
 *     action="{{ route('lift-logs.store') }}"
 *     method="POST" 
 * />
 */
class LiftLogFormComponent extends Component
{
    public LiftLog $liftLog;
    public Collection $exercises;
    public array $formFields;
    public array $validationRules;
    public string $action;
    public string $method;

    /**
     * Create a new component instance.
     */
    public function __construct(
        LiftLog $liftLog = null,
        Collection $exercises = null,
        string $action = '',
        string $method = 'POST'
    ) {
        $this->liftLog = $liftLog ?? new LiftLog();
        $this->exercises = $exercises ?? collect();
        $this->action = $action;
        $this->method = $method;
        
        // Get form fields and validation rules from strategy
        if ($this->liftLog->exists && $this->liftLog->exercise) {
            $strategy = $this->liftLog->exercise->getTypeStrategy();
            $this->formFields = $strategy->getFormFields();
            $this->validationRules = $strategy->getValidationRules();
        } else {
            // For new lift logs, show all possible fields
            $this->formFields = ['exercise_id', 'weight', 'band_color', 'reps', 'rounds', 'comments', 'date', 'logged_at'];
            $this->validationRules = [];
        }
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render()
    {
        return view('components.lift-log-form');
    }

    /**
     * Check if a field should be displayed
     */
    public function shouldShowField(string $field): bool
    {
        // Always show these essential fields regardless of exercise type
        $alwaysShowFields = ['comments', 'date', 'logged_at', 'rounds'];
        
        if (in_array($field, $alwaysShowFields)) {
            return true;
        }
        
        // For new lift logs, show all fields including exercise_id dropdown
        if (!$this->liftLog->exists) {
            return true;
        }
        
        // For existing lift logs, show fields defined by the exercise type strategy
        // Note: exercise_id is handled separately as a hidden field for existing lift logs
        return in_array($field, $this->formFields);
    }

    /**
     * Check if exercise_id should be shown as a dropdown (for new lift logs)
     */
    public function shouldShowExerciseDropdown(): bool
    {
        return !$this->liftLog->exists;
    }

    /**
     * Check if exercise_id should be included as a hidden field (for existing lift logs)
     */
    public function shouldIncludeHiddenExerciseId(): bool
    {
        return $this->liftLog->exists;
    }

    /**
     * Get available band colors
     */
    public function getBandColors(): array
    {
        $colors = config('bands.colors', []);
        $options = ['' => 'Select Band'];
        
        foreach ($colors as $color => $data) {
            $options[$color] = ucfirst($color);
        }
        
        return $options;
    }

    /**
     * Check if the current exercise is banded
     */
    public function isCurrentExerciseBanded(): bool
    {
        return $this->liftLog->exists && 
               $this->liftLog->exercise && 
               $this->liftLog->exercise->isBanded();
    }

    /**
     * Get the current band color for the lift log
     */
    public function getCurrentBandColor(): ?string
    {
        if (!$this->liftLog->exists || !$this->liftLog->liftSets->count()) {
            return null;
        }
        
        return $this->liftLog->liftSets->first()->band_color;
    }
}