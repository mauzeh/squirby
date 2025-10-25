<?php

namespace App\View\Components\MobileEntry;

use Illuminate\View\Component;

/**
 * Base component class for mobile-entry interface components
 * 
 * Provides consistent parameter handling and validation for all mobile-entry components
 */
abstract class BaseComponent extends Component
{
    /**
     * Validate required parameters for the component
     * 
     * @param array $required Array of required parameter names
     * @param array $provided Array of provided parameters
     * @throws \InvalidArgumentException if required parameters are missing
     */
    protected function validateRequiredParameters(array $required, array $provided): void
    {
        $missing = array_diff($required, array_keys($provided));
        
        if (!empty($missing)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Missing required parameters for %s: %s',
                    static::class,
                    implode(', ', $missing)
                )
            );
        }
    }

    /**
     * Sanitize HTML attributes to prevent XSS
     * 
     * @param mixed $value
     * @return string
     */
    protected function sanitizeAttribute($value): string
    {
        if (is_array($value) || is_object($value)) {
            return '';
        }
        
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Generate HTML attributes string from array
     * 
     * @param array $attributes
     * @return string
     */
    protected function attributesToString(array $attributes): string
    {
        $html = [];
        
        foreach ($attributes as $key => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            
            if ($value === true) {
                $html[] = $this->sanitizeAttribute($key);
            } else {
                $html[] = sprintf(
                    '%s="%s"',
                    $this->sanitizeAttribute($key),
                    $this->sanitizeAttribute($value)
                );
            }
        }
        
        return implode(' ', $html);
    }

    /**
     * Merge default attributes with provided attributes
     * 
     * @param array $defaults
     * @param array $provided
     * @return array
     */
    protected function mergeAttributes(array $defaults, array $provided): array
    {
        return array_merge($defaults, $provided);
    }
}