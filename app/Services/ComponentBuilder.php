<?php

namespace App\Services;

/**
 * Component Builder
 * 
 * Fluent interface for building UI components for the flexible mobile entry view.
 * Each component type has its own builder class for type-safe configuration.
 */
class ComponentBuilder
{
    /**
     * Create a navigation component
     */
    public static function navigation(): NavigationComponentBuilder
    {
        return new NavigationComponentBuilder();
    }
    
    /**
     * Create a title component
     */
    public static function title(string $main, ?string $subtitle = null): TitleComponentBuilder
    {
        return new TitleComponentBuilder($main, $subtitle);
    }
    
    /**
     * Create a messages component
     */
    public static function messages(): MessagesComponentBuilder
    {
        return new MessagesComponentBuilder();
    }
    
    /**
     * Create a summary component
     */
    public static function summary(): SummaryComponentBuilder
    {
        return new SummaryComponentBuilder();
    }
    
    /**
     * Create a button component
     */
    public static function button(string $text): ButtonComponentBuilder
    {
        return new ButtonComponentBuilder($text);
    }
    
    /**
     * Create an item list component
     */
    public static function itemList(): ItemListComponentBuilder
    {
        return new ItemListComponentBuilder();
    }
    
    /**
     * Create a form component
     */
    public static function form(string $id, string $title): FormComponentBuilder
    {
        return new FormComponentBuilder($id, $title);
    }
    
    /**
     * Create a logged items component
     */
    public static function loggedItems(): LoggedItemsComponentBuilder
    {
        return new LoggedItemsComponentBuilder();
    }
}

/**
 * Navigation Component Builder
 */
class NavigationComponentBuilder
{
    protected array $data = [
        'prevButton' => null,
        'centerButton' => null,
        'nextButton' => null,
        'ariaLabels' => [
            'navigation' => 'Navigation',
            'previous' => 'Previous',
            'center' => 'Current',
            'next' => 'Next'
        ]
    ];
    
    public function prev(string $text, string $href, bool $enabled = true, ?string $ariaLabel = null): self
    {
        $this->data['prevButton'] = [
            'text' => $text,
            'href' => $href,
            'enabled' => $enabled
        ];
        
        if ($ariaLabel) {
            $this->data['ariaLabels']['previous'] = $ariaLabel;
        }
        
        return $this;
    }
    
    public function center(string $text, ?string $href = null, bool $enabled = true, ?string $ariaLabel = null): self
    {
        $this->data['centerButton'] = [
            'text' => $text,
            'href' => $href,
            'enabled' => $enabled
        ];
        
        if ($ariaLabel) {
            $this->data['ariaLabels']['center'] = $ariaLabel;
        }
        
        return $this;
    }
    
    public function next(string $text, string $href, bool $enabled = true, ?string $ariaLabel = null): self
    {
        $this->data['nextButton'] = [
            'text' => $text,
            'href' => $href,
            'enabled' => $enabled
        ];
        
        if ($ariaLabel) {
            $this->data['ariaLabels']['next'] = $ariaLabel;
        }
        
        return $this;
    }
    
    public function ariaLabel(string $label): self
    {
        $this->data['ariaLabels']['navigation'] = $label;
        return $this;
    }
    
    public function build(): array
    {
        return [
            'type' => 'navigation',
            'data' => $this->data
        ];
    }
}

/**
 * Title Component Builder
 */
class TitleComponentBuilder
{
    protected array $data;
    
    public function __construct(string $main, ?string $subtitle = null)
    {
        $this->data = [
            'main' => $main,
            'subtitle' => $subtitle
        ];
    }
    
    public function subtitle(string $subtitle): self
    {
        $this->data['subtitle'] = $subtitle;
        return $this;
    }
    
    public function build(): array
    {
        return [
            'type' => 'title',
            'data' => $this->data
        ];
    }
}

/**
 * Messages Component Builder
 */
class MessagesComponentBuilder
{
    protected array $data = [
        'messages' => []
    ];
    
    public function add(string $type, string $text, ?string $prefix = null): self
    {
        $message = ['type' => $type, 'text' => $text];
        
        if ($prefix) {
            $message['prefix'] = $prefix;
        }
        
        $this->data['messages'][] = $message;
        return $this;
    }
    
    public function success(string $text, ?string $prefix = null): self
    {
        return $this->add('success', $text, $prefix);
    }
    
    public function error(string $text, ?string $prefix = null): self
    {
        return $this->add('error', $text, $prefix);
    }
    
    public function warning(string $text, ?string $prefix = null): self
    {
        return $this->add('warning', $text, $prefix);
    }
    
    public function info(string $text, ?string $prefix = null): self
    {
        return $this->add('info', $text, $prefix);
    }
    
    public function tip(string $text, ?string $prefix = null): self
    {
        return $this->add('tip', $text, $prefix);
    }
    
    public function build(): array
    {
        return [
            'type' => 'messages',
            'data' => $this->data
        ];
    }
}

/**
 * Summary Component Builder
 */
class SummaryComponentBuilder
{
    protected array $data = [
        'items' => [],
        'ariaLabel' => 'Summary'
    ];
    
    public function item(string $key, $value, ?string $label = null): self
    {
        $this->data['items'][] = [
            'key' => $key,
            'value' => $value,
            'label' => $label ?? ucfirst(str_replace('_', ' ', $key))
        ];
        
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
            'type' => 'summary',
            'data' => $this->data
        ];
    }
}

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
            'cssClass' => 'btn-primary btn-success'
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
    
    public function build(): array
    {
        return [
            'type' => 'button',
            'data' => $this->data
        ];
    }
}

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
    
    public function createForm(string $action, string $inputName, array $hiddenFields = []): self
    {
        $this->data['createForm'] = [
            'action' => $action,
            'method' => 'POST',
            'inputName' => $inputName,
            'submitText' => '+',
            'ariaLabel' => 'Create new item',
            'hiddenFields' => $hiddenFields
        ];
        
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

/**
 * Form Component Builder
 */
class FormComponentBuilder
{
    protected array $data;
    
    public function __construct(string $id, string $title)
    {
        $this->data = [
            'id' => $id,
            'type' => 'secondary',
            'title' => $title,
            'itemName' => $title,
            'formAction' => '#',
            'deleteAction' => null,
            'messages' => [],
            'numericFields' => [],
            'commentField' => [
                'id' => $id . '-comment',
                'name' => 'comment',
                'label' => 'Notes:',
                'placeholder' => 'Add any notes...',
                'defaultValue' => ''
            ],
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
        
        $this->data['messages'][] = $message;
        return $this;
    }
    
    public function numericField(string $name, string $label, $defaultValue, float $increment = 1, float $min = 0, ?float $max = null): self
    {
        $this->data['numericFields'][] = [
            'id' => $this->data['id'] . '-' . $name,
            'name' => $name,
            'label' => $label,
            'defaultValue' => $defaultValue,
            'increment' => $increment,
            'min' => $min,
            'max' => $max,
            'step' => $increment,
            'ariaLabels' => [
                'decrease' => 'Decrease ' . strtolower($label),
                'increase' => 'Increase ' . strtolower($label)
            ]
        ];
        
        return $this;
    }
    
    public function selectField(string $name, string $label, array $options, $defaultValue): self
    {
        $this->data['numericFields'][] = [
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
        
        return $this;
    }
    
    public function commentField(string $label, string $placeholder, string $defaultValue = ''): self
    {
        $this->data['commentField'] = [
            'id' => $this->data['id'] . '-comment',
            'name' => 'comment',
            'label' => $label,
            'placeholder' => $placeholder,
            'defaultValue' => $defaultValue
        ];
        
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
    
    public function build(): array
    {
        return [
            'type' => 'form',
            'data' => $this->data
        ];
    }
}

/**
 * Logged Items Component Builder
 */
class LoggedItemsComponentBuilder
{
    protected array $data = [
        'items' => [],
        'emptyMessage' => null,
        'confirmMessages' => [
            'deleteItem' => 'Are you sure you want to delete this item?',
            'removeForm' => 'Are you sure you want to remove this item?'
        ],
        'ariaLabels' => [
            'section' => 'Logged items',
            'editItem' => 'Edit item',
            'deleteItem' => 'Delete item'
        ]
    ];
    
    public function item(int $id, string $title, $value, string $editAction, string $deleteAction): LoggedItemBuilder
    {
        return new LoggedItemBuilder($this, $id, $title, $value, $editAction, $deleteAction);
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
            'type' => 'logged-items',
            'data' => $this->data
        ];
    }
}

/**
 * Logged Item Builder (nested builder)
 */
class LoggedItemBuilder
{
    protected LoggedItemsComponentBuilder $parent;
    protected array $data;
    
    public function __construct(LoggedItemsComponentBuilder $parent, int $id, string $title, $value, string $editAction, string $deleteAction)
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
    
    public function add(): LoggedItemsComponentBuilder
    {
        $this->parent->addItem($this->data);
        return $this->parent;
    }
}
