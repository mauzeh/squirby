<?php

namespace App\Services\Components\Tables;

/**
 * Table Row Builder (nested builder)
 */
class TableRowBuilder
{
    protected TableComponentBuilder $parent;
    protected array $data;
    
    public function __construct(
        TableComponentBuilder $parent,
        int $id,
        string $line1,
        ?string $line2,
        ?string $line3
    ) {
        $this->parent = $parent;
        $this->data = [
            'id' => $id,
            'line1' => $line1,
            'titleClass' => 'cell-title',
            'actions' => [],
            'subItems' => []
        ];
        
        if ($line2 !== null) {
            $this->data['line2'] = $line2;
        }
        
        if ($line3 !== null) {
            $this->data['line3'] = $line3;
        }
    }
    
    public function titleClass(string $class): self
    {
        $this->data['titleClass'] = $class;
        return $this;
    }
    
    public function compact(bool $compact = true): self
    {
        $this->data['compact'] = $compact;
        return $this;
    }
    
    public function checkbox(bool $enabled = true): self
    {
        $this->data['checkbox'] = $enabled;
        return $this;
    }
    
    public function wrapActions(bool $wrap = true): self
    {
        $this->data['wrapActions'] = $wrap;
        return $this;
    }
    
    public function wrapText(bool $wrap = true): self
    {
        $this->data['wrapText'] = $wrap;
        return $this;
    }
    
    public function badge(string $text, string $color = 'neutral', bool $emphasized = false): self
    {
        if (!isset($this->data['badges'])) {
            $this->data['badges'] = [];
        }
        
        $colorClasses = ['success', 'info', 'warning', 'danger', 'neutral', 'dark'];
        
        $badge = [
            'text' => $text,
            'emphasized' => $emphasized
        ];
        
        if (in_array($color, $colorClasses)) {
            $badge['colorClass'] = $color;
        } else {
            $badge['customColor'] = $color;
        }
        
        $this->data['badges'][] = $badge;
        
        return $this;
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
    
    public function subItem(int $id, string $line1, ?string $line2 = null, ?string $line3 = null): TableSubItemBuilder
    {
        return new TableSubItemBuilder($this, $id, $line1, $line2, $line3);
    }
    
    public function addSubItem(array $subItem): self
    {
        $this->data['subItems'][] = $subItem;
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
    
    public function collapsible(bool $collapsible = true): self
    {
        $this->data['collapsible'] = $collapsible;
        return $this;
    }
    
    public function initialState(string $state): self
    {
        $this->data['initialState'] = $state;
        return $this;
    }
    
    public function clickable(string $url): self
    {
        $this->data['clickableUrl'] = $url;
        return $this;
    }
    
    public function add(): TableComponentBuilder
    {
        $this->parent->addRow($this->data);
        return $this->parent;
    }
}
