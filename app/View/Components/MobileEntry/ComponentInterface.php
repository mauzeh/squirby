<?php

namespace App\View\Components\MobileEntry;

/**
 * Interface for mobile-entry components
 * 
 * Defines the contract that all mobile-entry components must implement
 */
interface ComponentInterface
{
    /**
     * Get the view / contents that represent the component
     * 
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render();

    /**
     * Get the component's required parameters
     * 
     * @return array
     */
    public function getRequiredParameters(): array;

    /**
     * Get the component's optional parameters with defaults
     * 
     * @return array
     */
    public function getOptionalParameters(): array;

    /**
     * Validate the component's parameters
     * 
     * @param array $parameters
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function validateParameters(array $parameters): bool;
}