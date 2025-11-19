<?php

namespace App\Services\Components\Interactive;

/**
 * Select All Control Component Builder
 * For bulk selection "Select All" checkbox
 */
class SelectAllControlComponentBuilder
{
    protected array $data;
    
    public function __construct(string $checkboxId, string $label)
    {
        $this->data = [
            'checkboxId' => $checkboxId,
            'label' => $label,
            'checkboxSelector' => '.template-checkbox'
        ];
    }
    
    public function checkboxSelector(string $selector): self
    {
        $this->data['checkboxSelector'] = $selector;
        return $this;
    }
    
    public function build(): array
    {
        return [
            'type' => 'select_all_control',
            'data' => $this->data,
            'requiresScript' => 'table-bulk-selection'
        ];
    }
}
