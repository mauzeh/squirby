<?php

namespace App\Services\Components\Interactive;

/**
 * Code Editor Component Builder
 * 
 * Creates a CodeMirror-based code editor for syntax highlighting and better editing UX
 */
class CodeEditorComponentBuilder
{
    protected array $data;
    
    public function __construct(string $id, string $label)
    {
        $this->data = [
            'id' => $id,
            'label' => $label,
            'name' => 'content',
            'value' => '',
            'placeholder' => '',
            'mode' => 'text',
            'theme' => 'dark',
            'height' => '400px',
            'lineNumbers' => true,
            'lineWrapping' => true,
            'readOnly' => false,
            'autofocus' => false,
            'ariaLabel' => $label,
        ];
    }
    
    /**
     * Set the form field name
     */
    public function name(string $name): self
    {
        $this->data['name'] = $name;
        return $this;
    }
    
    /**
     * Set the editor content value
     */
    public function value(string $value): self
    {
        $this->data['value'] = $value;
        return $this;
    }
    
    /**
     * Set the placeholder text
     */
    public function placeholder(string $placeholder): self
    {
        $this->data['placeholder'] = $placeholder;
        return $this;
    }
    
    /**
     * Set the syntax mode (e.g., 'wod-syntax', 'javascript', 'markdown')
     */
    public function mode(string $mode): self
    {
        $this->data['mode'] = $mode;
        return $this;
    }
    
    /**
     * Set the editor theme
     */
    public function theme(string $theme): self
    {
        $this->data['theme'] = $theme;
        return $this;
    }
    
    /**
     * Set the editor height
     */
    public function height(string $height): self
    {
        $this->data['height'] = $height;
        return $this;
    }
    
    /**
     * Enable or disable line numbers
     */
    public function lineNumbers(bool $enabled): self
    {
        $this->data['lineNumbers'] = $enabled;
        return $this;
    }
    
    /**
     * Enable or disable line wrapping
     */
    public function lineWrapping(bool $enabled): self
    {
        $this->data['lineWrapping'] = $enabled;
        return $this;
    }
    
    /**
     * Set read-only mode
     */
    public function readOnly(bool $readOnly): self
    {
        $this->data['readOnly'] = $readOnly;
        return $this;
    }
    
    /**
     * Enable autofocus
     */
    public function autofocus(bool $autofocus): self
    {
        $this->data['autofocus'] = $autofocus;
        return $this;
    }
    
    /**
     * Set ARIA label for accessibility
     */
    public function ariaLabel(string $label): self
    {
        $this->data['ariaLabel'] = $label;
        return $this;
    }
    
    /**
     * Build the component
     */
    public function build(): array
    {
        return [
            'type' => 'code-editor',
            'data' => $this->data,
            'requiresScript' => 'mobile-entry/components/code-editor'
        ];
    }
}
