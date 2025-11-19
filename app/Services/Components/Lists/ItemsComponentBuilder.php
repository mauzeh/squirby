<?php

namespace App\Services\Components\Lists;

/**
 * Items Component Builder
 */
class ItemsComponentBuilder
{
    protected array $data = [
        'items' => [],
        'emptyMessage' => '',
        'confirmMessages' => [
            'deleteItem' => 'Are you sure you want to delete this item?',
            'removeForm' => 'Are you sure you want to remove this item?'
        ],
        'ariaLabels' => [
            'section' => 'Items',
            'editItem' => 'Edit item',
            'deleteItem' => 'Delete item'
        ]
    ];
    
    public function item(int $id, string $title, $value, string $editAction, string $deleteAction): ItemBuilder
    {
        return new ItemBuilder($this, $id, $title, $value, $editAction, $deleteAction);
    }
    
    public function addItem(array $item): self
    {
        $this->data['items'][] = $item;
        return $this;
    }
    
    public function emptyMessage(string $message): self
    {
        $this->data['emptyMessage'] = $message;
        return $this;
    }
    
    public function confirmMessage(string $key, string $message): self
    {
        $this->data['confirmMessages'][$key] = $message;
        return $this;
    }
    
    public function build(): array
    {
        return [
            'type' => 'items',
            'data' => $this->data
        ];
    }
}
