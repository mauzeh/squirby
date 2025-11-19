<?php

namespace App\Services\Components\Display;

/**
 * Summary Component Builder
 */
class SummaryComponentBuilder
{
    protected array $data = [
        'items' => [],
        'ariaLabel' => 'Summary'
    ];
    
    public function item(string $key, $value, ?string $label = null): self
    {
        $this->data['items'][] = [
            'key' => $key,
            'value' => $value,
            'label' => $label ?? ucfirst(str_replace('_', ' ', $key))
        ];
        
        return $this;
    }
    
    public function ariaLabel(string $label): self
    {
        $this->data['ariaLabel'] = $label;
        return $this;
    }
    
    public function build(): array
    {
        return [
            'type' => 'summary',
            'data' => $this->data
        ];
    }
}
