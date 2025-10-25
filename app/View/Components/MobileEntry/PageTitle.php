<?php

namespace App\View\Components\MobileEntry;

use Carbon\Carbon;

/**
 * Mobile Entry Page Title Component
 * 
 * Displays conditional date title with Today/Yesterday/Tomorrow/formatted date handling.
 * Consolidates identical page title logic from lift-logs and food-logs templates.
 */
class PageTitle extends BaseComponent
{
    public Carbon $selectedDate;
    public string $tag;
    public string $class;

    /**
     * Create a new component instance.
     */
    public function __construct(
        Carbon $selectedDate,
        string $tag = 'h1',
        string $class = ''
    ) {
        $this->validateRequiredParameters(['selectedDate'], [
            'selectedDate' => $selectedDate
        ]);

        $this->selectedDate = $selectedDate;
        $this->tag = $this->sanitizeAttribute($tag);
        $this->class = $this->sanitizeAttribute($class);
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render()
    {
        return view('components.mobile-entry.page-title');
    }
}