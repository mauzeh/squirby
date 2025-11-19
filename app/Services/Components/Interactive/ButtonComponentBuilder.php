<?php

namespace App\Services\Components\Interactive;

/**
 * Button Component Builder
 */
class ButtonComponentBuilder
{
    protected array $data;
    
    public function __construct(string $text)
    {
        $this->data = [
            'text' => $text,
            'ariaLabel' => $text,
            'cssClass' => 'btn-primary',
            'type' => 'button',
            'initialState' => 'visible'
        ];
    }
    
    public function ariaLabel(string $label): self
    {
        $this->data['ariaLabel'] = $label;
        return $this;
    }
    
    public function cssClass(string $class): self
    {
        $this->data['cssClass'] = $class;
        return $this;
    }
    
    public function addClass(string $class): self
    {
        $this->data['cssClass'] .= ' ' . $class;
        return $this;
    }
    
    public function asLink(string $url): self
    {
        $this->data['type'] = 'link';
        $this->data['url'] = $url;
        return $this;
    }
    
    public function initialState(string $state): self
    {
        $this->data['initialState'] = $state;
        return $this;
    }
    
    public function build(): array
    {
        return [
            'type' => 'button',
            'data' => $this->data
        ];
    }
}
