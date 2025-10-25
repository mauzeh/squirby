<?php

namespace App\View\Components\MobileEntry;

/**
 * Mobile Entry Empty State Component
 * 
 * Consistent messaging for when no items are available.
 * Consolidates empty state patterns from lift-logs and food-logs templates.
 */
class EmptyState extends BaseComponent
{
    public string $message;
    public string $class;
    public ?string $actionText;
    public ?string $actionUrl;
    public ?string $actionId;

    /**
     * Create a new component instance.
     */
    public function __construct(
        string $message,
        string $class = '',
        ?string $actionText = null,
        ?string $actionUrl = null,
        ?string $actionId = null
    ) {
        $this->validateRequiredParameters(['message'], [
            'message' => $message
        ]);

        $this->message = $this->sanitizeAttribute($message);
        $this->class = $this->sanitizeAttribute($class);
        $this->actionText = $actionText ? $this->sanitizeAttribute($actionText) : null;
        $this->actionUrl = $actionUrl;
        $this->actionId = $actionId ? $this->sanitizeAttribute($actionId) : null;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render()
    {
        return view('components.mobile-entry.empty-state');
    }
}