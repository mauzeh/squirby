<?php

namespace App\Services\Components\Lists;

/**
 * Item List Component Builder
 */
class ItemListComponentBuilder
{
    protected array $data = [
        'items' => [],
        'filterPlaceholder' => 'Filter items...',
        'noResultsMessage' => 'No items found.',
        'createForm' => null,
        'initialState' => 'collapsed',
        'showCancelButton' => true,
        'restrictHeight' => true,
        'showFilter' => true,
        'ariaLabels' => [
            'section' => 'Item selection list',
            'selectItem' => 'Select this item'
        ]
    ];
    
    public function item(string $id, string $name, string $href, string $typeLabel, string $typeCssClass, int $priority = 3): self
    {
        $this->data['items'][] = [
            'id' => $id,
            'name' => $name,
            'href' => $href,
            'type' => [
                'label' => $typeLabel,
                'cssClass' => $typeCssClass,
                'priority' => $priority
            ]
        ];
        
        return $this;
    }
    
    public function filterPlaceholder(string $placeholder): self
    {
        $this->data['filterPlaceholder'] = $placeholder;
        return $this;
    }
    
    public function noResultsMessage(string $message): self
    {
        $this->data['noResultsMessage'] = $message;
        return $this;
    }
    
    public function createForm(string $action, string $inputName, array $hiddenFields = [], string $buttonTextTemplate = 'Create "{term}"', string $method = 'POST'): self
    {
        $this->data['createForm'] = [
            'action' => $action,
            'method' => $method,
            'inputName' => $inputName,
            'submitText' => '+',
            'buttonTextTemplate' => $buttonTextTemplate,
            'ariaLabel' => 'Create new item',
            'hiddenFields' => $hiddenFields
        ];
        
        return $this;
    }
    
    public function initialState(string $state): self
    {
        $this->data['initialState'] = $state;
        return $this;
    }
    
    public function showCancelButton(bool $show = true): self
    {
        $this->data['showCancelButton'] = $show;
        return $this;
    }
    
    public function restrictHeight(bool $restrict = true): self
    {
        $this->data['restrictHeight'] = $restrict;
        return $this;
    }
    public function showFilter(bool $show = true): self
    {
        $this->data['showFilter'] = $show;
        return $this;
    }

    
    public function build(): array
    {
        return [
            'type' => 'item-list',
            'data' => $this->data
        ];
    }
}
