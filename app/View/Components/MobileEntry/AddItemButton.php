<?php

namespace App\View\Components\MobileEntry;

/**
 * Mobile Entry Add Item Button Component
 * 
 * Standardized button for adding exercises, food items, etc. with configurable behavior.
 * Consolidates add button patterns from lift-logs and food-logs templates.
 */
class AddItemButton extends BaseComponent
{
    public string $id;
    public string $label;
    public ?string $targetContainer;
    public string $buttonClass;
    public string $containerClass;
    public bool $hideOnClick;

    /**
     * Create a new component instance.
     */
    public function __construct(
        string $id,
        string $label,
        ?string $targetContainer = null,
        string $buttonClass = '',
        string $containerClass = '',
        bool $hideOnClick = true
    ) {
        $this->validateRequiredParameters(['id', 'label'], [
            'id' => $id,
            'label' => $label
        ]);

        $this->id = $this->sanitizeAttribute($id);
        $this->label = $this->sanitizeAttribute($label);
        $this->targetContainer = $targetContainer ? $this->sanitizeAttribute($targetContainer) : null;
        $this->buttonClass = $this->sanitizeAttribute($buttonClass);
        $this->containerClass = $this->sanitizeAttribute($containerClass);
        $this->hideOnClick = $hideOnClick;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render()
    {
        return view('components.mobile-entry.add-item-button');
    }
}