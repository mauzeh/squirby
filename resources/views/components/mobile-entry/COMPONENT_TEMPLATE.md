# Mobile Entry Component Template

This template provides a standardized format for documenting mobile-entry components.

## Component Name: [ComponentName]

### Purpose
Brief description of what this component does and why it exists.

### Location
- **Blade Template:** `resources/views/components/mobile-entry/[component-name].blade.php`
- **PHP Class:** `app/View/Components/MobileEntry/[ComponentName].php` (if needed)

### Usage

```php
<x-mobile-entry.[component-name]
    required-param="value"
    :optional-param="$variable"
    optional-string="default-value" />
```

### Parameters

#### Required Parameters
| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `required-param` | string | Description of required parameter | `"example-value"` |

#### Optional Parameters
| Parameter | Type | Default | Description | Example |
|-----------|------|---------|-------------|---------|
| `optional-param` | mixed | `null` | Description of optional parameter | `$variable` |
| `optional-string` | string | `"default"` | Description with default | `"custom-value"` |

### Slots
If the component supports slots, document them here:

#### Default Slot
Description of the default slot content.

```php
<x-mobile-entry.[component-name]>
    <p>Default slot content goes here</p>
</x-mobile-entry.[component-name]>
```

#### Named Slots
| Slot Name | Required | Description |
|-----------|----------|-------------|
| `header` | No | Content for the header section |
| `footer` | No | Content for the footer section |

### Examples

#### Basic Usage
```php
<x-mobile-entry.[component-name]
    required-param="basic-example" />
```

#### Advanced Usage
```php
<x-mobile-entry.[component-name]
    required-param="advanced-example"
    :optional-param="$complexVariable"
    optional-string="custom-value">
    
    <x-slot name="header">
        <h3>Custom Header</h3>
    </x-slot>
    
    <p>Main content goes here</p>
    
    <x-slot name="footer">
        <button>Custom Action</button>
    </x-slot>
</x-mobile-entry.[component-name]>
```

### CSS Classes
Document the main CSS classes used by this component:

- `.component-wrapper` - Main wrapper class
- `.component-content` - Content area class
- `.component-action` - Action button class

### JavaScript Dependencies
List any JavaScript dependencies or event handlers:

- Requires `entry-interface.js` for [specific functionality]
- Emits `component:action` event when [condition]
- Listens for `component:update` event

### Accessibility
Document accessibility features:

- ARIA labels and roles
- Keyboard navigation support
- Screen reader considerations

### Browser Support
- Modern browsers (Chrome 90+, Firefox 88+, Safari 14+)
- Mobile browsers (iOS Safari 14+, Chrome Mobile 90+)

### Migration Notes
If this component replaces existing markup, document the migration:

#### Before (Legacy)
```php
<div class="old-markup">
    <!-- Old implementation -->
</div>
```

#### After (Component)
```php
<x-mobile-entry.[component-name]
    required-param="migrated-value" />
```

### Testing
Document how to test this component:

- Unit tests location: `tests/Unit/View/Components/MobileEntry/[ComponentName]Test.php`
- Integration tests: `tests/Feature/MobileEntry/[ComponentName]Test.php`
- Manual testing scenarios

### Related Components
List related components that work together:

- `x-mobile-entry.related-component` - Description of relationship
- `x-mobile-entry.another-component` - Description of relationship

### Changelog
Track major changes to the component:

- **v1.0.0** - Initial implementation
- **v1.1.0** - Added optional-param support
- **v1.2.0** - Improved accessibility features