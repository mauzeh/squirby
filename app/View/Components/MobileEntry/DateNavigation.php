<?php

namespace App\View\Components\MobileEntry;

use Carbon\Carbon;

/**
 * Mobile Entry Date Navigation Component
 * 
 * Provides prev/today/next navigation with Carbon date handling.
 * Consolidates identical date navigation markup from lift-logs and food-logs templates.
 */
class DateNavigation extends BaseComponent
{
    public Carbon $selectedDate;
    public string $routeName;
    public array $routeParams;

    /**
     * Create a new component instance.
     */
    public function __construct(
        Carbon $selectedDate,
        string $routeName,
        array $routeParams = []
    ) {
        $this->validateRequiredParameters(['selectedDate', 'routeName'], [
            'selectedDate' => $selectedDate,
            'routeName' => $routeName
        ]);

        $this->selectedDate = $selectedDate;
        $this->routeName = $routeName;
        $this->routeParams = $routeParams;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render()
    {
        return view('components.mobile-entry.date-navigation');
    }
}