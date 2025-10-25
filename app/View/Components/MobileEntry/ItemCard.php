<?php

namespace App\View\Components\MobileEntry;

/**
 * Mobile Entry Item Card Component
 * 
 * Unified card component with configurable actions and content slots.
 * Supports both lift program cards and food log cards with flexible content.
 */
class ItemCard extends BaseComponent
{
    public string $title;
    public ?string $deleteRoute;
    public string $deleteConfirmText;
    public array $hiddenFields;
    public ?string $moveActions;
    public string $cardClass;
    public bool $showActions;

    /**
     * Create a new component instance.
     */
    public function __construct(
        string $title,
        ?string $deleteRoute = null,
        string $deleteConfirmText = 'Are you sure?',
        array $hiddenFields = [],
        ?string $moveActions = null,
        string $cardClass = '',
        bool $showActions = true
    ) {
        $this->validateRequiredParameters(['title'], [
            'title' => $title
        ]);

        $this->title = $this->sanitizeAttribute($title);
        $this->deleteRoute = $deleteRoute;
        $this->deleteConfirmText = $this->sanitizeAttribute($deleteConfirmText);
        $this->hiddenFields = $hiddenFields;
        $this->moveActions = $moveActions;
        $this->cardClass = $this->sanitizeAttribute($cardClass);
        $this->showActions = $showActions;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render()
    {
        return view('components.mobile-entry.item-card');
    }
}