<?php

namespace App\Services\Components\Navigation;

/**
 * Navigation Component Builder
 */
class NavigationComponentBuilder
{
    protected array $data = [
        'prevButton' => null,
        'centerButton' => null,
        'nextButton' => null,
        'ariaLabels' => [
            'navigation' => 'Navigation',
            'previous' => 'Previous',
            'center' => 'Current',
            'next' => 'Next'
        ]
    ];
    
    public function prev(string $text, string $href, bool $enabled = true, ?string $ariaLabel = null): self
    {
        $this->data['prevButton'] = [
            'text' => $text,
            'href' => $href,
            'enabled' => $enabled
        ];
        
        if ($ariaLabel) {
            $this->data['ariaLabels']['previous'] = $ariaLabel;
        }
        
        return $this;
    }
    
    public function center(string $text, ?string $href = null, bool $enabled = true, ?string $ariaLabel = null): self
    {
        $this->data['centerButton'] = [
            'text' => $text,
            'href' => $href,
            'enabled' => $enabled
        ];
        
        if ($ariaLabel) {
            $this->data['ariaLabels']['center'] = $ariaLabel;
        }
        
        return $this;
    }
    
    public function next(string $text, string $href, bool $enabled = true, ?string $ariaLabel = null): self
    {
        $this->data['nextButton'] = [
            'text' => $text,
            'href' => $href,
            'enabled' => $enabled
        ];
        
        if ($ariaLabel) {
            $this->data['ariaLabels']['next'] = $ariaLabel;
        }
        
        return $this;
    }
    
    public function ariaLabel(string $label): self
    {
        $this->data['ariaLabels']['navigation'] = $label;
        return $this;
    }
    
    public function build(): array
    {
        return [
            'type' => 'navigation',
            'data' => $this->data
        ];
    }
}
