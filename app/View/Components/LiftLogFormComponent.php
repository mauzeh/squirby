<?php

namespace App\View\Components;

use App\Models\Exercise;
use App\Models\LiftLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\Component;

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
        return in_array($field, $this->formFields) || !$this->liftLog->exists;
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
               !empty($this->liftLog->exercise->band_type);
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