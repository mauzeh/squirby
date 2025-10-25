<?php

namespace App\View\Components\MobileEntry;

/**
 * Mobile Entry Number Input Component
 * 
 * Number input with increment/decrement functionality and configurable step values.
 * Consolidates form input patterns from lift-logs and food-logs templates.
 */
class NumberInput extends BaseComponent
{
    public string $name;
    public string $id;
    public $value;
    public string $label;
    public ?string $unit;
    public $step;
    public $min;
    public $max;
    public bool $required;
    public string $inputClass;
    public string $groupClass;

    /**
     * Create a new component instance.
     */
    public function __construct(
        string $name,
        string $id,
        $value = 1,
        string $label = '',
        ?string $unit = null,
        $step = 1,
        $min = 0,
        $max = null,
        bool $required = false,
        string $inputClass = '',
        string $groupClass = ''
    ) {
        $this->validateRequiredParameters(['name', 'id'], [
            'name' => $name,
            'id' => $id
        ]);

        $this->name = $this->sanitizeAttribute($name);
        $this->id = $this->sanitizeAttribute($id);
        $this->value = is_numeric($value) ? $value : 1;
        $this->label = $this->sanitizeAttribute($label);
        $this->unit = $unit ? $this->sanitizeAttribute($unit) : null;
        $this->step = is_numeric($step) ? $step : 1;
        $this->min = is_numeric($min) ? $min : 0;
        $this->max = is_numeric($max) ? $max : null;
        $this->required = $required;
        $this->inputClass = $this->sanitizeAttribute($inputClass);
        $this->groupClass = $this->sanitizeAttribute($groupClass);
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render()
    {
        return view('components.mobile-entry.number-input');
    }
}