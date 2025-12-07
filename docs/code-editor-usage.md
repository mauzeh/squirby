# Code Editor Component - Usage Guide

## Quick Start

### Basic Usage

```php
use App\Services\ComponentBuilder as C;

$components[] = C::codeEditor('my-editor', 'Code')
    ->name('code_field')
    ->value($existingCode)
    ->build();
```

### Full Configuration

```php
$components[] = C::codeEditor('wod-editor', 'WOD Syntax')
    ->name('wod_syntax')              // Form field name
    ->value($workout->wod_syntax)     // Initial content
    ->placeholder($exampleSyntax)     // Placeholder text
    ->mode('wod-syntax')              // Syntax mode
    ->theme('dark')                   // Theme (currently only 'dark')
    ->height('400px')                 // Editor height
    ->lineNumbers(true)               // Show line numbers
    ->lineWrapping(true)              // Wrap long lines
    ->readOnly(false)                 // Read-only mode
    ->autofocus(false)                // Auto-focus on load
    ->ariaLabel('WOD Syntax Editor')  // Accessibility label
    ->build();
```

## Embedding in Forms

### Option 1: Standalone Component

Use the code editor as a separate component between form fields:

```php
// Name field
$components[] = C::form('details', 'Details')
    ->textField('name', 'Name:', $name)
    ->build();

// Code editor
$components[] = C::codeEditor('editor', 'Code')
    ->name('code')
    ->value($code)
    ->build();

// Description field
$components[] = C::form('more', 'More Details')
    ->textField('description', 'Description:', $desc)
    ->submitButton('Save')
    ->build();
```

**Note:** This creates multiple `<form>` elements. You'll need to handle submission differently.

### Option 2: Custom Form Component (Recommended)

Create a custom form component that embeds the editor:

```php
$components[] = [
    'type' => 'custom-form',
    'data' => [
        'id' => 'my-form',
        'formAction' => route('save'),
        'fields' => [...],
        'codeEditor' => [
            'id' => 'editor',
            'name' => 'code',
            'value' => $code,
            // ... other config
        ],
        'submitButton' => 'Save'
    ],
    'requiresScript' => 'mobile-entry/components/code-editor'
];
```

Then create `resources/views/mobile-entry/components/custom-form.blade.php` similar to `wod-form.blade.php`.

## Syntax Modes

### Currently Supported

- `wod-syntax` - WOD workout syntax with highlighting

### Adding New Modes

To add a new syntax mode:

1. **Add highlighting function** in `code-editor.js`:

```javascript
function highlightMyMode(text) {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/pattern/g, '<span class="cm-my-token">$1</span>');
}
```

2. **Add CSS classes** in `code-editor.css`:

```css
.cm-my-token {
    color: #4ec9b0;
    font-weight: bold;
}
```

3. **Register mode** in `initCodeEditors()`:

```javascript
if (config.mode === 'my-mode') {
    initMyModeEditor(textarea, wrapper, config);
}
```

## Styling

### Custom Height

```php
->height('600px')  // Fixed height
->height('50vh')   // Viewport-relative
```

### Custom CSS Classes

Add custom classes via the wrapper:

```javascript
// In your custom script
document.querySelector('[data-editor-id="my-editor"]')
    .classList.add('my-custom-class');
```

## JavaScript API

### Accessing the Editor

```javascript
const textarea = document.querySelector('#my-editor-textarea');
const value = textarea.value;  // Get content
textarea.value = 'new content'; // Set content
textarea.dispatchEvent(new Event('input')); // Trigger update
```

### Events

```javascript
const textarea = document.querySelector('#my-editor-textarea');

// Listen for changes
textarea.addEventListener('input', (e) => {
    console.log('Content changed:', e.target.value);
});

// Listen for scroll
textarea.addEventListener('scroll', (e) => {
    console.log('Scrolled to:', e.target.scrollTop);
});
```

## Accessibility

### ARIA Labels

Always provide meaningful labels:

```php
->ariaLabel('WOD Syntax Editor - Enter your workout syntax here')
```

### Keyboard Navigation

Supported keys:
- **Tab** - Insert 2 spaces
- **Enter** - New line with auto-indent
- **Ctrl+A** - Select all
- **Ctrl+C/V/X** - Copy/paste/cut
- **Ctrl+Z/Y** - Undo/redo (browser native)

## Mobile Considerations

### Touch Scrolling

The editor automatically handles touch scrolling on mobile devices.

### Virtual Keyboard

The editor adjusts when the virtual keyboard appears. Test on actual devices.

### Responsive Height

Consider using viewport-relative heights on mobile:

```php
// In controller
$isMobile = request()->header('User-Agent') // detect mobile
$height = $isMobile ? '300px' : '500px';

->height($height)
```

## Performance

### Large Documents

The current implementation handles documents up to ~500 lines well. For larger documents:

1. Consider pagination
2. Upgrade to CodeMirror 6 (see `code-editor-upgrade.md`)
3. Add lazy loading

### Optimization Tips

- Debounce syntax highlighting for very large documents
- Disable line numbers for better performance
- Use `lineWrapping(false)` for better performance

## Troubleshooting

### Syntax Highlighting Not Working

1. Check browser console for errors
2. Verify `data-mode` attribute is set
3. Check that `code-editor.js` is loaded
4. Verify CSS is loaded

### Line Numbers Not Scrolling

1. Check that wrapper has `position: relative`
2. Verify scroll event listener is attached
3. Check z-index values

### Caret Not Visible

1. Verify `caretColor` is set in CSS
2. Check that textarea has `color: transparent`
3. Verify z-index layering

### Form Not Submitting Content

1. Check that textarea has correct `name` attribute
2. Verify content is synced to textarea
3. Check browser console for JavaScript errors

## Examples

### Read-Only Viewer

```php
$components[] = C::codeEditor('viewer', 'View Code')
    ->name('code')
    ->value($code)
    ->readOnly(true)
    ->lineNumbers(true)
    ->build();
```

### Minimal Editor

```php
$components[] = C::codeEditor('simple', 'Code')
    ->name('code')
    ->value($code)
    ->lineNumbers(false)
    ->height('200px')
    ->build();
```

### Full-Featured Editor

```php
$components[] = C::codeEditor('advanced', 'Advanced Editor')
    ->name('code')
    ->value($code)
    ->mode('wod-syntax')
    ->height('600px')
    ->lineNumbers(true)
    ->lineWrapping(true)
    ->autofocus(true)
    ->ariaLabel('Advanced code editor with syntax highlighting')
    ->build();
```

## Best Practices

1. **Always set a name** - Required for form submission
2. **Provide meaningful labels** - For accessibility
3. **Set appropriate height** - Based on expected content
4. **Use placeholders** - Show example syntax
5. **Test on mobile** - Ensure touch interactions work
6. **Validate server-side** - Don't rely on client-side validation
7. **Escape user content** - Prevent XSS attacks
8. **Provide fallback** - Ensure it works without JavaScript

## Related Documentation

- `code-editor-implementation.md` - Technical implementation details
- `code-editor-testing.md` - Testing guide
- `code-editor-upgrade.md` - CodeMirror 6 upgrade path
- `wod-syntax-guide.md` - WOD syntax reference
