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
     * Create an items component
     */
    public static function items(): ItemsComponentBuilder
    {
        return new ItemsComponentBuilder();
    }
    
    /**
     * Create a table component
     */
    public static function table(): TableComponentBuilder
    {
        return new TableComponentBuilder();
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
            'subtitle' => $subtitle,
            'backButton' => null
        ];
    }
    
    public function subtitle(string $subtitle): self
    {
        $this->data['subtitle'] = $subtitle;
        return $this;
    }
    
    public function backButton(string $icon, string $url, ?string $ariaLabel = null): self
    {
        $this->data['backButton'] = [
            'icon' => $icon,
            'url' => $url,
            'ariaLabel' => $ariaLabel ?? 'Go back'
        ];
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
            'cssClass' => 'btn-primary',
            'type' => 'button', // 'button' or 'link'
            'initialState' => 'visible' // 'visible' or 'hidden'
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
        'initialState' => 'collapsed', // 'collapsed' or 'expanded'
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
    
    public function initialState(string $state): self
    {
        $this->data['initialState'] = $state;
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
            'confirmMessage' => null,
            'messages' => [],
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
    
    public function textField(string $name, string $label, string $defaultValue = '', string $placeholder = ''): self
    {
        $this->data['numericFields'][] = [
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
        
        return $this;
    }
    
    public function textareaField(string $name, string $label, string $defaultValue = '', string $placeholder = ''): self
    {
        $this->data['numericFields'][] = [
            'id' => $this->data['id'] . '-' . $name,
            'name' => $name,
            'label' => $label,
            'type' => 'textarea',
            'defaultValue' => $defaultValue,
            'placeholder' => $placeholder,
            'ariaLabels' => [
                'field' => $label
            ]
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
    
    public function confirmMessage(string $message): self
    {
        $this->data['confirmMessage'] = $message;
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

/**
 * Table Component Builder
 * 
 * Builds a tabular CRUD list optimized for narrow screens.
 * Each row can have up to 3 lines of text with edit and delete actions.
 */
class TableComponentBuilder
{
    protected array $data = [
        'rows' => [],
        'emptyMessage' => '',
        'confirmMessages' => [],
        'ariaLabels' => [
            'section' => 'Data table',
            'editItem' => 'Edit item',
            'deleteItem' => 'Delete item'
        ]
    ];
    
    /**
     * Add a row with custom actions
     * 
     * @param int $id Row identifier
     * @param string $line1 First line (bold, primary text)
     * @param string|null $line2 Second line (secondary text)
     * @param string|null $line3 Third line (muted, italic text)
     * @return TableRowBuilder
     */
    public function row(int $id, string $line1, ?string $line2 = null, ?string $line3 = null): TableRowBuilder
    {
        return new TableRowBuilder($this, $id, $line1, $line2, $line3);
    }
    
    /**
     * Add a row directly (internal use)
     */
    public function addRow(array $row): self
    {
        $this->data['rows'][] = $row;
        return $this;
    }
    
    /**
     * Set empty message when no rows exist
     */
    public function emptyMessage(string $message): self
    {
        $this->data['emptyMessage'] = $message;
        return $this;
    }
    
    /**
     * Add a confirmation message for delete actions
     */
    public function confirmMessage(string $key, string $message): self
    {
        $this->data['confirmMessages'][$key] = $message;
        return $this;
    }
    
    /**
     * Set aria label for the table section
     */
    public function ariaLabel(string $label): self
    {
        $this->data['ariaLabels']['section'] = $label;
        return $this;
    }
    
    /**
     * Build the component
     */
    public function build(): array
    {
        return [
            'type' => 'table',
            'data' => $this->data
        ];
    }
}

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
            'titleClass' => 'cell-title', // Default title class
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
    
    /**
     * Set custom CSS class for the title (line1)
     */
    public function titleClass(string $class): self
    {
        $this->data['titleClass'] = $class;
        return $this;
    }
    
    /**
     * Add a link action (GET request)
     * 
     * @param string $icon FontAwesome icon class (e.g., 'fa-edit', 'fa-arrow-up')
     * @param string $url Action URL
     * @param string|null $ariaLabel Accessibility label
     * @param string|null $cssClass Additional CSS classes
     */
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
    
    /**
     * Add a form action (POST/DELETE request)
     * 
     * @param string $icon FontAwesome icon class
     * @param string $url Action URL
     * @param string $method HTTP method (POST, DELETE, etc.)
     * @param array $params Additional form parameters
     * @param string|null $ariaLabel Accessibility label
     * @param string|null $cssClass Additional CSS classes
     * @param bool $requiresConfirm Whether to show confirmation dialog
     */
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
    
    /**
     * Add a sub-item with custom actions
     */
    public function subItem(int $id, string $line1, ?string $line2 = null, ?string $line3 = null): TableSubItemBuilder
    {
        return new TableSubItemBuilder($this, $id, $line1, $line2, $line3);
    }
    
    /**
     * Add sub-item directly (internal use)
     */
    public function addSubItem(array $subItem): self
    {
        $this->data['subItems'][] = $subItem;
        return $this;
    }
    
    /**
     * Set whether sub-items should be collapsible (default: true)
     */
    public function collapsible(bool $collapsible = true): self
    {
        $this->data['collapsible'] = $collapsible;
        return $this;
    }
    
    /**
     * Set initial state for collapsible rows (default: 'collapsed')
     */
    public function initialState(string $state): self
    {
        $this->data['initialState'] = $state; // 'collapsed' or 'expanded'
        return $this;
    }
    
    /**
     * Add the row and return to parent builder
     */
    public function add(): TableComponentBuilder
    {
        $this->parent->addRow($this->data);
        return $this->parent;
    }
}

/**
 * Table Sub-Item Builder
 */
class TableSubItemBuilder
{
    protected $parent; // Can be TableRowBuilder or TableRowBuilderV2
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
    
    /**
     * Add a link action
     */
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
    
    /**
     * Add a form action
     */
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
    
    /**
     * Add a message to the sub-item
     */
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
    
    /**
     * Add the sub-item and return to parent row builder
     */
    public function add()
    {
        $this->parent->addSubItem($this->data);
        return $this->parent;
    }
}
