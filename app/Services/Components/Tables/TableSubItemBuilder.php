<?php

namespace App\Services\Components\Tables;

/**
 * Table Sub-Item Builder
 */
class TableSubItemBuilder
{
    protected $parent;
    protected array $data;
    
    public function __construct(
        $parent,
        int $id,
        string $line1,
        ?string $line2,
        ?string $line3
    ) {
        $this->parent = $parent;
        $this->data = [
            'id' => $id,
            'line1' => $line1,
            'actions' => []
        ];
        
        if ($line2 !== null) {
            $this->data['line2'] = $line2;
        }
        
        if ($line3 !== null) {
            $this->data['line3'] = $line3;
        }
    }
    
    public function linkAction(string $icon, string $url, ?string $ariaLabel = null, ?string $cssClass = null): self
    {
        $this->data['actions'][] = [
            'type' => 'link',
            'icon' => $icon,
            'url' => $url,
            'ariaLabel' => $ariaLabel,
            'cssClass' => $cssClass
        ];
        return $this;
    }
    
    public function formAction(string $icon, string $url, string $method = 'POST', array $params = [], ?string $ariaLabel = null, ?string $cssClass = null, bool $requiresConfirm = false): self
    {
        $this->data['actions'][] = [
            'type' => 'form',
            'icon' => $icon,
            'url' => $url,
            'method' => $method,
            'params' => $params,
            'ariaLabel' => $ariaLabel,
            'cssClass' => $cssClass,
            'requiresConfirm' => $requiresConfirm
        ];
        return $this;
    }
    
    public function message(string $type, string $text, ?string $prefix = null): self
    {
        if (!isset($this->data['messages'])) {
            $this->data['messages'] = [];
        }
        
        $message = [
            'type' => $type,
            'text' => $text
        ];
        
        if ($prefix !== null) {
            $message['prefix'] = $prefix;
        }
        
        $this->data['messages'][] = $message;
        return $this;
    }
    
    public function compact(bool $compact = true): self
    {
        $this->data['compact'] = $compact;
        return $this;
    }
    
    public function add()
    {
        $this->parent->addSubItem($this->data);
        return $this->parent;
    }
}
