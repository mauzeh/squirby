<?php

namespace App\View\Components\MobileEntry;

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

/**
 * Mobile Entry Message System Component
 * 
 * Displays error, success, and validation messages with auto-hide functionality.
 * Consolidates identical message markup from lift-logs and food-logs templates.
 */
class MessageSystem extends BaseComponent
{
    public MessageBag|ViewErrorBag|null $errors;
    public ?string $success;
    public bool $showValidation;

    /**
     * Create a new component instance.
     */
    public function __construct(
        MessageBag|ViewErrorBag|null $errors = null,
        ?string $success = null,
        bool $showValidation = true
    ) {
        $this->errors = $errors;
        $this->success = $success;
        $this->showValidation = $showValidation;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render()
    {
        return view('components.mobile-entry.message-system');
    }
}