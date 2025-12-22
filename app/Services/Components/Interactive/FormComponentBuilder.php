<?php

namespace App\Services\Components\Interactive;

/**
 * Form Component Builder
 */
class FormComponentBuilder
{
    protected array $data;
    
    protected ?string $currentSection = null;
    
    public function __construct(string $id, string $title)
    {
        $this->data = [
            'id' => $id,
            'type' => 'secondary',
            'title' => $title,
            'itemName' => $title,
            'formAction' => '#',
            'deleteAction' => null,
            'confirmMessage' => null,
            'messages' => [],
            'sections' => [],
            'numericFields' => [],
            'hiddenFields' => [],
            'buttons' => [
                'decrement' => '-',
                'increment' => '+',
                'submit' => 'Submit'
            ],
            'ariaLabels' => [
                'section' => 'Form',
                'deleteForm' => 'Remove this form'
            ]
        ];
    }
    
    public function section(string $title, bool $collapsible = false, string $initialState = 'expanded'): self
    {
        $this->currentSection = $title;
        $this->data['sections'][] = [
            'title' => $title,
            'collapsible' => $collapsible,
            'initialState' => $initialState,
            'fields' => [],
            'messages' => []
        ];
        return $this;
    }
    
    public function type(string $type): self
    {
        $this->data['type'] = $type;
        return $this;
    }
    
    public function formAction(string $action): self
    {
        $this->data['formAction'] = $action;
        return $this;
    }
    
    public function deleteAction(string $action): self
    {
        $this->data['deleteAction'] = $action;
        return $this;
    }
    
    public function message(string $type, string $text, ?string $prefix = null): self
    {
        $message = ['type' => $type, 'text' => $text];
        
        if ($prefix) {
            $message['prefix'] = $prefix;
        }
        
        if ($this->currentSection !== null && !empty($this->data['sections'])) {
            $lastSectionIndex = count($this->data['sections']) - 1;
            $this->data['sections'][$lastSectionIndex]['fields'][] = [
                'type' => 'message',
                'messageType' => $type,
                'text' => $text,
                'prefix' => $prefix
            ];
        } else {
            $this->data['messages'][] = $message;
        }
        
        return $this;
    }
    
    public function numericField(string $name, string $label, $defaultValue, float $increment = 1, float $min = 0, ?float $max = null, $step = null): self
    {
        if ($step === null) {
            $step = $increment;
        }

        $field = [
            'id' => $this->data['id'] . '-' . $name,
            'name' => $name,
            'label' => $label,
            'defaultValue' => $defaultValue,
            'increment' => $increment,
            'min' => $min,
            'max' => $max,
            'step' => $step,
            'ariaLabels' => [
                'decrease' => 'Decrease ' . strtolower($label),
                'increase' => 'Increase ' . strtolower($label)
            ]
        ];
        
        if ($this->currentSection !== null && !empty($this->data['sections'])) {
            $lastSectionIndex = count($this->data['sections']) - 1;
            $this->data['sections'][$lastSectionIndex]['fields'][] = $field;
        } else {
            $this->data['numericFields'][] = $field;
        }
        
        return $this;
    }
    
    public function selectField(string $name, string $label, array $options, $defaultValue): self
    {
        $field = [
            'id' => $this->data['id'] . '-' . $name,
            'name' => $name,
            'label' => $label,
            'type' => 'select',
            'defaultValue' => $defaultValue,
            'options' => $options,
            'ariaLabels' => [
                'field' => 'Select ' . strtolower($label)
            ]
        ];
        
        if ($this->currentSection !== null && !empty($this->data['sections'])) {
            $lastSectionIndex = count($this->data['sections']) - 1;
            $this->data['sections'][$lastSectionIndex]['fields'][] = $field;
        } else {
            $this->data['numericFields'][] = $field;
        }
        
        return $this;
    }
    
    public function textField(string $name, string $label, string $defaultValue = '', string $placeholder = ''): self
    {
        $field = [
            'id' => $this->data['id'] . '-' . $name,
            'name' => $name,
            'label' => $label,
            'type' => 'text',
            'defaultValue' => $defaultValue,
            'placeholder' => $placeholder,
            'ariaLabels' => [
                'field' => $label
            ]
        ];
        
        if ($this->currentSection !== null && !empty($this->data['sections'])) {
            $lastSectionIndex = count($this->data['sections']) - 1;
            $this->data['sections'][$lastSectionIndex]['fields'][] = $field;
        } else {
            $this->data['numericFields'][] = $field;
        }
        
        return $this;
    }
    
    public function passwordField(string $name, string $label, string $placeholder = ''): self
    {
        $field = [
            'id' => $this->data['id'] . '-' . $name,
            'name' => $name,
            'label' => $label,
            'type' => 'password',
            'placeholder' => $placeholder,
            'ariaLabels' => [
                'field' => $label
            ]
        ];
        
        if ($this->currentSection !== null && !empty($this->data['sections'])) {
            $lastSectionIndex = count($this->data['sections']) - 1;
            $this->data['sections'][$lastSectionIndex]['fields'][] = $field;
        } else {
            $this->data['numericFields'][] = $field;
        }
        
        return $this;
    }
    
    public function textareaField(string $name, string $label, string $defaultValue = '', string $placeholder = '', ?string $cssClass = null): self
    {
        $field = [
            'id' => $this->data['id'] . '-' . $name,
            'name' => $name,
            'label' => $label,
            'type' => 'textarea',
            'defaultValue' => $defaultValue,
            'placeholder' => $placeholder,
            'cssClass' => $cssClass,
            'ariaLabels' => [
                'field' => $label
            ]
        ];
        
        if ($this->currentSection !== null && !empty($this->data['sections'])) {
            $lastSectionIndex = count($this->data['sections']) - 1;
            $this->data['sections'][$lastSectionIndex]['fields'][] = $field;
        } else {
            $this->data['numericFields'][] = $field;
        }
        
        return $this;
    }
    
    public function dateField(string $name, string $label, string $defaultValue = '', string $placeholder = ''): self
    {
        $field = [
            'id' => $this->data['id'] . '-' . $name,
            'name' => $name,
            'label' => $label,
            'type' => 'date',
            'defaultValue' => $defaultValue,
            'placeholder' => $placeholder,
            'ariaLabels' => [
                'field' => $label
            ]
        ];
        
        if ($this->currentSection !== null && !empty($this->data['sections'])) {
            $lastSectionIndex = count($this->data['sections']) - 1;
            $this->data['sections'][$lastSectionIndex]['fields'][] = $field;
        } else {
            $this->data['numericFields'][] = $field;
        }
        
        return $this;
    }
    
    public function checkboxField(string $name, string $label, bool $defaultValue, ?string $description = null): self
    {
        $field = [
            'id' => $this->data['id'] . '-' . $name,
            'name' => $name,
            'label' => $label,
            'type' => 'checkbox',
            'defaultValue' => $defaultValue,
            'description' => $description,
            'ariaLabels' => [
                'field' => $label
            ]
        ];
        
        if ($this->currentSection !== null && !empty($this->data['sections'])) {
            $lastSectionIndex = count($this->data['sections']) - 1;
            $this->data['sections'][$lastSectionIndex]['fields'][] = $field;
        } else {
            $this->data['numericFields'][] = $field;
        }
        
        return $this;
    }
    
    public function checkboxArrayField(string $name, string $label, $value, bool $defaultValue, ?string $description = null): self
    {
        $field = [
            'id' => $this->data['id'] . '-' . str_replace(['[', ']'], '', $name) . '-' . $value,
            'name' => $name,
            'label' => $label,
            'type' => 'checkbox_array',
            'value' => $value,
            'defaultValue' => $defaultValue,
            'description' => $description,
            'ariaLabels' => [
                'field' => $label
            ]
        ];
        
        if ($this->currentSection !== null && !empty($this->data['sections'])) {
            $lastSectionIndex = count($this->data['sections']) - 1;
            $this->data['sections'][$lastSectionIndex]['fields'][] = $field;
        } else {
            $this->data['numericFields'][] = $field;
        }
        
        return $this;
    }
    
    public function hiddenField(string $name, $value): self
    {
        $this->data['hiddenFields'][$name] = $value;
        return $this;
    }
    
    public function submitButton(string $text): self
    {
        $this->data['buttons']['submit'] = $text;
        return $this;
    }
    
    public function submitButtonClass(string $class): self
    {
        $this->data['submitButtonClass'] = $class;
        return $this;
    }
    
    public function hideSubmitButton(): self
    {
        $this->data['hideSubmitButton'] = true;
        return $this;
    }
    
    public function confirmMessage(string $message): self
    {
        $this->data['confirmMessage'] = $message;
        return $this;
    }
    
    public function cssClass(string $class): self
    {
        $this->data['cssClass'] = $class;
        return $this;
    }
    
    public function initialState(string $state): self
    {
        $this->data['initialState'] = $state;
        return $this;
    }
    
    public function build(): array
    {
        $component = [
            'type' => 'form',
            'data' => $this->data
        ];

        $hasCollapsibleSections = false;
        if (!empty($this->data['sections'])) {
            foreach ($this->data['sections'] as $section) {
                if ($section['collapsible']) {
                    $hasCollapsibleSections = true;
                    break;
                }
            }
        }

        if ($hasCollapsibleSections || (isset($this->data['cssClass']) && strpos($this->data['cssClass'], 'collapsible-form') !== false)) {
            $component['requiresScript'] = 'mobile-entry/collapsible-form';
        }
        
        return $component;
    }
}
