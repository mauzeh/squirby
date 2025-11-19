<?php

namespace App\Services\Components\Lists;

/**
 * Item Builder (nested builder)
 */
class ItemBuilder
{
    protected ItemsComponentBuilder $parent;
    protected array $data;
    
    public function __construct(ItemsComponentBuilder $parent, int $id, string $title, $value, string $editAction, string $deleteAction)
    {
        $this->parent = $parent;
        $this->data = [
            'id' => $id,
            'title' => $title,
            'value' => $value,
            'editAction' => $editAction,
            'deleteAction' => $deleteAction
        ];
    }
    
    public function message(string $type, string $text, ?string $prefix = null): self
    {
        $message = ['type' => $type, 'text' => $text];
        
        if ($prefix) {
            $message['prefix'] = $prefix;
        }
        
        $this->data['message'] = $message;
        return $this;
    }
    
    public function freeformText(string $text): self
    {
        $this->data['freeformText'] = $text;
        return $this;
    }
    
    public function deleteParams(array $params): self
    {
        $this->data['deleteParams'] = $params;
        return $this;
    }
    
    public function add(): ItemsComponentBuilder
    {
        $this->parent->addItem($this->data);
        return $this->parent;
    }
}
