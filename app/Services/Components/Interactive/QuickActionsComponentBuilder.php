<?php

namespace App\Services\Components\Interactive;

/**
 * Quick Actions Component Builder
 */
class QuickActionsComponentBuilder
{
    protected array $data;
    
    public function __construct(string $title = 'Quick Actions')
    {
        $this->data = [
            'title' => $title,
            'actions' => [],
            'initialState' => 'visible'
        ];
    }
    
    public function title(string $title): self
    {
        $this->data['title'] = $title;
        return $this;
    }
    
    public function formAction(string $icon, string $action, string $method = 'POST', array $params = [], string $text = '', string $cssClass = 'btn-primary', ?string $confirm = null): self
    {
        $this->data['actions'][] = [
            'type' => 'form',
            'icon' => $icon,
            'text' => $text,
            'action' => $action,
            'method' => $method,
            'params' => $params,
            'cssClass' => $cssClass,
            'confirm' => $confirm
        ];
        return $this;
    }
    
    public function linkAction(string $icon, string $url, string $text = '', string $cssClass = 'btn-primary'): self
    {
        $this->data['actions'][] = [
            'type' => 'link',
            'icon' => $icon,
            'text' => $text,
            'url' => $url,
            'cssClass' => $cssClass
        ];
        return $this;
    }
    
    public function disabledAction(string $icon, string $text = '', string $cssClass = 'btn-secondary', string $reason = ''): self
    {
        $this->data['actions'][] = [
            'type' => 'disabled',
            'icon' => $icon,
            'text' => $text,
            'cssClass' => $cssClass,
            'reason' => $reason
        ];
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
            'type' => 'quick_actions',
            'data' => $this->data
        ];
    }
}