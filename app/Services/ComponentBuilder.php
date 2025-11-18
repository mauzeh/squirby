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
     * Create a messages component from session flash data
     * Returns null if no session messages exist
     */
    public static function messagesFromSession(): ?array
    {
        $builder = new MessagesComponentBuilder();
        $hasMessages = false;
        
        if (session('success')) {
            $builder->success(session('success'));
            $hasMessages = true;
        }
        
        if (session('error')) {
            $builder->error(session('error'));
            $hasMessages = true;
        }
        
        if (session('warning')) {
            $builder->warning(session('warning'));
            $hasMessages = true;
        }
        
        if (session('info')) {
            $builder->info(session('info'));
            $hasMessages = true;
        }
        
        return $hasMessages ? $builder->build() : null;
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
    
    /**
     * Create a bulk action form component
     */
    public static function bulkActionForm(string $formId, string $action, string $buttonText): BulkActionFormComponentBuilder
    {
        return new BulkActionFormComponentBuilder($formId, $action, $buttonText);
    }
    
    /**
     * Create a select all control component
     */
    public static function selectAllControl(string $checkboxId, string $label = 'Select All'): SelectAllControlComponentBuilder
    {
        return new SelectAllControlComponentBuilder($checkboxId, $label);
    }
    
    /**
     * Create a raw HTML component
     */
    public static function rawHtml(string $html): array
    {
        return [
            'type' => 'raw_html',
            'data' => ['html' => $html]
        ];
    }
    
    /**
     * Create a chart component
     */
    public static function chart(string $canvasId, string $title): ChartComponentBuilder
    {
        return new ChartComponentBuilder($canvasId, $title);
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
    
    public function build(): array
    {
        return [
            'type' => 'item-list',
            'data' => $this->data,
            'requiresStyle' => 'create-item'
        ];
    }
}

/**
 * Form Component Builder
 */
class FormComponentBuilder
{
    protected array $data;
    
    protected ?string $currentSection = null;
    
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
            'sections' => [],
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
    
    public function section(string $title, bool $collapsible = false, string $initialState = 'expanded'): self
    {
        $this->currentSection = $title;
        $this->data['sections'][] = [
            'title' => $title,
            'collapsible' => $collapsible,
            'initialState' => $initialState,
            'fields' => [],
            'messages' => []
        ];
        return $this;
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
        
        // Add to current section if one is active, otherwise to form messages
        if ($this->currentSection !== null && !empty($this->data['sections'])) {
            $lastSectionIndex = count($this->data['sections']) - 1;
            // Add as a special field type so it renders inline
            $this->data['sections'][$lastSectionIndex]['fields'][] = [
                'type' => 'message',
                'messageType' => $type,
                'text' => $text,
                'prefix' => $prefix
            ];
        } else {
            $this->data['messages'][] = $message;
        }
        
        return $this;
    }
    
    public function numericField(string $name, string $label, $defaultValue, float $increment = 1, float $min = 0, ?float $max = null): self
    {
        $field = [
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
        
        // Add to current section if one is active, otherwise to form fields
        if ($this->currentSection !== null && !empty($this->data['sections'])) {
            $lastSectionIndex = count($this->data['sections']) - 1;
            $this->data['sections'][$lastSectionIndex]['fields'][] = $field;
        } else {
            $this->data['numericFields'][] = $field;
        }
        
        return $this;
    }
    
    public function selectField(string $name, string $label, array $options, $defaultValue): self
    {
        $field = [
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
        
        // Add to current section if one is active, otherwise to form fields
        if ($this->currentSection !== null && !empty($this->data['sections'])) {
            $lastSectionIndex = count($this->data['sections']) - 1;
            $this->data['sections'][$lastSectionIndex]['fields'][] = $field;
        } else {
            $this->data['numericFields'][] = $field;
        }
        
        return $this;
    }
    
    public function textField(string $name, string $label, string $defaultValue = '', string $placeholder = ''): self
    {
        $field = [
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
        
        // Add to current section if one is active, otherwise to form fields
        if ($this->currentSection !== null && !empty($this->data['sections'])) {
            $lastSectionIndex = count($this->data['sections']) - 1;
            $this->data['sections'][$lastSectionIndex]['fields'][] = $field;
        } else {
            $this->data['numericFields'][] = $field;
        }
        
        return $this;
    }
    
    public function textareaField(string $name, string $label, string $defaultValue = '', string $placeholder = ''): self
    {
        $field = [
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
        
        // Add to current section if one is active, otherwise to form fields
        if ($this->currentSection !== null && !empty($this->data['sections'])) {
            $lastSectionIndex = count($this->data['sections']) - 1;
            $this->data['sections'][$lastSectionIndex]['fields'][] = $field;
        } else {
            $this->data['numericFields'][] = $field;
        }
        
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
    
    public function hideSubmitButton(): self
    {
        $this->data['hideSubmitButton'] = true;
        return $this;
    }
    
    public function confirmMessage(string $message): self
    {
        $this->data['confirmMessage'] = $message;
        return $this;
    }
    
    public function cssClass(string $class): self
    {
        $this->data['cssClass'] = $class;
        return $this;
    }
    
    public function initialState(string $state): self
    {
        $this->data['initialState'] = $state;
        return $this;
    }
    
    public function build(): array
    {
        $component = [
            'type' => 'form',
            'data' => $this->data
        ];
        
        // Add required styles/scripts if using sections or collapsible
        $hasCollapsibleSections = false;
        if (!empty($this->data['sections'])) {
            foreach ($this->data['sections'] as $section) {
                if ($section['collapsible']) {
                    $hasCollapsibleSections = true;
                    break;
                }
            }
        }
        
        if ($hasCollapsibleSections || (isset($this->data['cssClass']) && strpos($this->data['cssClass'], 'collapsible-form') !== false)) {
            $component['requiresStyle'] = 'collapsible-form';
            $component['requiresScript'] = 'mobile-entry/collapsible-form';
        }
        
        return $component;
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
     * Set multiple rows at once
     */
    public function rows(array $rows): self
    {
        $this->data['rows'] = $rows;
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
     * Add spacing between rows
     */
    public function spacedRows(bool $spaced = true): self
    {
        $this->data['spacedRows'] = $spaced;
        return $this;
    }
    
    /**
     * Check if any row has a checkbox enabled
     */
    protected function hasCheckboxes(): bool
    {
        foreach ($this->data['rows'] as $row) {
            if (isset($row['checkbox']) && $row['checkbox']) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if any row has badges
     */
    protected function hasBadges(): bool
    {
        foreach ($this->data['rows'] as $row) {
            if (isset($row['badges']) && !empty($row['badges'])) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Build the component
     */
    public function build(): array
    {
        $component = [
            'type' => 'table',
            'data' => $this->data
        ];
        
        // Automatically include bulk selection script if any row has checkboxes
        if ($this->hasCheckboxes()) {
            $component['requiresScript'] = 'table-bulk-selection';
        }
        
        // Automatically include badge styles if any row has badges
        if ($this->hasBadges()) {
            $component['requiresStyle'] = 'table-badges';
        }
        
        return $component;
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
     * Use compact button size (75% of normal)
     */
    public function compact(bool $compact = true): self
    {
        $this->data['compact'] = $compact;
        return $this;
    }
    
    /**
     * Add a checkbox for bulk selection
     */
    public function checkbox(bool $enabled = true): self
    {
        $this->data['checkbox'] = $enabled;
        return $this;
    }
    
    /**
     * Allow action buttons to wrap to multiple lines
     */
    public function wrapActions(bool $wrap = true): self
    {
        $this->data['wrapActions'] = $wrap;
        return $this;
    }
    
    /**
     * Allow text content to wrap to multiple lines
     */
    public function wrapText(bool $wrap = true): self
    {
        $this->data['wrapText'] = $wrap;
        return $this;
    }
    
    /**
     * Add a badge/bubble to display metadata (mobile-friendly)
     * 
     * @param string $text Badge text
     * @param string $color Badge color (success, info, warning, danger, neutral, dark, or hex color)
     * @param bool $emphasized Whether to use emphasized badge style (darker, bold for important values)
     * @return self
     */
    public function badge(string $text, string $color = 'neutral', bool $emphasized = false): self
    {
        if (!isset($this->data['badges'])) {
            $this->data['badges'] = [];
        }
        
        // Predefined color classes
        $colorClasses = ['success', 'info', 'warning', 'danger', 'neutral', 'dark'];
        
        $badge = [
            'text' => $text,
            'emphasized' => $emphasized
        ];
        
        // Use CSS class for predefined colors, inline style for custom hex colors
        if (in_array($color, $colorClasses)) {
            $badge['colorClass'] = $color;
        } else {
            // Custom hex color
            $badge['customColor'] = $color;
        }
        
        $this->data['badges'][] = $badge;
        
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
     * Use compact button size (75% of normal)
     */
    public function compact(bool $compact = true): self
    {
        $this->data['compact'] = $compact;
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

/**
 * Bulk Action Form Component Builder
 * For forms that submit multiple selected items (bulk delete, bulk update, etc.)
 */
class BulkActionFormComponentBuilder
{
    protected array $data;
    
    public function __construct(string $formId, string $action, string $buttonText)
    {
        $this->data = [
            'formId' => $formId,
            'action' => $action,
            'buttonText' => $buttonText,
            'method' => 'POST',
            'buttonClass' => 'btn-danger',
            'icon' => 'fa-trash',
            'inputName' => 'selected_ids',
            'checkboxSelector' => '.template-checkbox',
            'confirmMessage' => null,
            'emptyMessage' => 'Please select at least one item.',
            'ariaLabel' => null
        ];
    }
    
    /**
     * Set HTTP method (default: POST)
     */
    public function method(string $method): self
    {
        $this->data['method'] = $method;
        return $this;
    }
    
    /**
     * Set button CSS class (default: btn-danger)
     */
    public function buttonClass(string $class): self
    {
        $this->data['buttonClass'] = $class;
        return $this;
    }
    
    /**
     * Set button icon (default: fa-trash)
     */
    public function icon(string $icon): self
    {
        $this->data['icon'] = $icon;
        return $this;
    }
    
    /**
     * Set the input name for selected IDs (default: selected_ids)
     */
    public function inputName(string $name): self
    {
        $this->data['inputName'] = $name;
        return $this;
    }
    
    /**
     * Set the checkbox selector (default: .template-checkbox)
     */
    public function checkboxSelector(string $selector): self
    {
        $this->data['checkboxSelector'] = $selector;
        return $this;
    }
    
    /**
     * Set confirmation message (null = no confirmation)
     */
    public function confirmMessage(?string $message): self
    {
        $this->data['confirmMessage'] = $message;
        return $this;
    }
    
    /**
     * Set empty selection message
     */
    public function emptyMessage(string $message): self
    {
        $this->data['emptyMessage'] = $message;
        return $this;
    }
    
    /**
     * Set aria label for accessibility
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
            'type' => 'bulk_action_form',
            'data' => $this->data,
            'requiresScript' => 'table-bulk-selection'
        ];
    }
}

/**
 * Select All Control Component Builder
 * For bulk selection "Select All" checkbox
 */
class SelectAllControlComponentBuilder
{
    protected array $data;
    
    public function __construct(string $checkboxId, string $label)
    {
        $this->data = [
            'checkboxId' => $checkboxId,
            'label' => $label,
            'checkboxSelector' => '.template-checkbox'
        ];
    }
    
    /**
     * Set the checkbox selector to target (default: .template-checkbox)
     */
    public function checkboxSelector(string $selector): self
    {
        $this->data['checkboxSelector'] = $selector;
        return $this;
    }
    
    /**
     * Build the component
     */
    public function build(): array
    {
        return [
            'type' => 'select_all_control',
            'data' => $this->data,
            'requiresScript' => 'table-bulk-selection'
        ];
    }
}


/**
 * Chart Component Builder
 * 
 * Builds Chart.js charts for the flexible component system
 */
class ChartComponentBuilder
{
    protected array $data;
    
    public function __construct(string $canvasId, string $title)
    {
        $this->data = [
            'canvasId' => $canvasId,
            'title' => $title,
            'type' => 'line',
            'datasets' => [],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => true,
            ],
            'height' => null,
            'containerClass' => 'form-container',
            'ariaLabel' => $title . ' chart'
        ];
    }
    
    /**
     * Set chart type (line, bar, pie, doughnut, radar, polarArea, bubble, scatter)
     */
    public function type(string $type): self
    {
        $this->data['type'] = $type;
        return $this;
    }
    
    /**
     * Set chart datasets
     */
    public function datasets(array $datasets): self
    {
        $this->data['datasets'] = $datasets;
        return $this;
    }
    
    /**
     * Set chart options (full Chart.js options object)
     */
    public function options(array $options): self
    {
        $this->data['options'] = array_merge($this->data['options'], $options);
        return $this;
    }
    
    /**
     * Set canvas height in pixels
     */
    public function height(int $pixels): self
    {
        $this->data['height'] = $pixels;
        return $this;
    }
    
    /**
     * Set container CSS class
     */
    public function containerClass(string $class): self
    {
        $this->data['containerClass'] = $class;
        return $this;
    }
    
    /**
     * Set aria label for accessibility
     */
    public function ariaLabel(string $label): self
    {
        $this->data['ariaLabel'] = $label;
        return $this;
    }
    
    /**
     * Helper: Configure time scale for X-axis
     */
    public function timeScale(string $unit = 'day', ?string $displayFormat = null): self
    {
        if (!isset($this->data['options']['scales'])) {
            $this->data['options']['scales'] = [];
        }
        
        $this->data['options']['scales']['x'] = [
            'type' => 'time',
            'time' => ['unit' => $unit]
        ];
        
        if ($displayFormat) {
            $this->data['options']['scales']['x']['time']['displayFormats'] = [
                $unit => $displayFormat
            ];
        }
        
        return $this;
    }
    
    /**
     * Helper: Configure Y-axis to begin at zero
     */
    public function beginAtZero(bool $value = true): self
    {
        if (!isset($this->data['options']['scales'])) {
            $this->data['options']['scales'] = [];
        }
        
        if (!isset($this->data['options']['scales']['y'])) {
            $this->data['options']['scales']['y'] = [];
        }
        
        $this->data['options']['scales']['y']['beginAtZero'] = $value;
        return $this;
    }
    
    /**
     * Helper: Show or hide legend
     */
    public function showLegend(bool $value = true): self
    {
        if (!isset($this->data['options']['plugins'])) {
            $this->data['options']['plugins'] = [];
        }
        
        if (!isset($this->data['options']['plugins']['legend'])) {
            $this->data['options']['plugins']['legend'] = [];
        }
        
        $this->data['options']['plugins']['legend']['display'] = $value;
        return $this;
    }
    
    /**
     * Helper: Set Y-axis label
     */
    public function yAxisLabel(string $label): self
    {
        if (!isset($this->data['options']['scales'])) {
            $this->data['options']['scales'] = [];
        }
        
        if (!isset($this->data['options']['scales']['y'])) {
            $this->data['options']['scales']['y'] = [];
        }
        
        $this->data['options']['scales']['y']['title'] = [
            'display' => true,
            'text' => $label
        ];
        
        return $this;
    }
    
    /**
     * Helper: Set X-axis label
     */
    public function xAxisLabel(string $label): self
    {
        if (!isset($this->data['options']['scales'])) {
            $this->data['options']['scales'] = [];
        }
        
        if (!isset($this->data['options']['scales']['x'])) {
            $this->data['options']['scales']['x'] = [];
        }
        
        $this->data['options']['scales']['x']['title'] = [
            'display' => true,
            'text' => $label
        ];
        
        return $this;
    }
    
    /**
     * Helper: Disable aspect ratio maintenance (allows custom height)
     */
    public function noAspectRatio(): self
    {
        $this->data['options']['maintainAspectRatio'] = false;
        return $this;
    }
    
    /**
     * Build the component
     */
    public function build(): array
    {
        return [
            'type' => 'chart',
            'data' => $this->data,
            'requiresScript' => 'chart-component'
        ];
    }
}
