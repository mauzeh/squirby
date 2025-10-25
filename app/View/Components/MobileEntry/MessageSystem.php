<?php

namespace App\View\Components\MobileEntry;

use Illuminate\Support\MessageBag;

/**
 * Mobile Entry Message System Component
 * 
 * Displays error, success, and validation messages with auto-hide functionality.
 * Consolidates identical message markup from lift-logs and food-logs templates.
 */
class MessageSystem extends BaseComponent
{
    public ?MessageBag $errors;
    public ?string $success;
    public bool $showValidation;

    /**
     * Create a new component instance.
     */
    public function __construct(
        ?MessageBag $errors = null,
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