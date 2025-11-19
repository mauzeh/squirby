<?php

namespace App\Services\Components\Interactive;

/**
 * Bulk Action Form Component Builder
 * For forms that submit multiple selected items (bulk delete, bulk update, etc.)
 */
class BulkActionFormComponentBuilder
{
    protected array $data;
    
    public function __construct(string $formId, string $action, string $buttonText)
    {
        $this->data = [
            'formId' => $formId,
            'action' => $action,
            'buttonText' => $buttonText,
            'method' => 'POST',
            'buttonClass' => 'btn-danger',
            'icon' => 'fa-trash',
            'inputName' => 'selected_ids',
            'checkboxSelector' => '.template-checkbox',
            'confirmMessage' => null,
            'emptyMessage' => 'Please select at least one item.',
            'ariaLabel' => null
        ];
    }
    
    public function method(string $method): self
    {
        $this->data['method'] = $method;
        return $this;
    }
    
    public function buttonClass(string $class): self
    {
        $this->data['buttonClass'] = $class;
        return $this;
    }
    
    public function icon(string $icon): self
    {
        $this->data['icon'] = $icon;
        return $this;
    }
    
    public function inputName(string $name): self
    {
        $this->data['inputName'] = $name;
        return $this;
    }
    
    public function checkboxSelector(string $selector): self
    {
        $this->data['checkboxSelector'] = $selector;
        return $this;
    }
    
    public function confirmMessage(?string $message): self
    {
        $this->data['confirmMessage'] = $message;
        return $this;
    }
    
    public function emptyMessage(string $message): self
    {
        $this->data['emptyMessage'] = $message;
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
            'type' => 'bulk_action_form',
            'data' => $this->data,
            'requiresScript' => 'table-bulk-selection'
        ];
    }
}
