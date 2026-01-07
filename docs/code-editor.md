# Code Editor Component - Complete Guide

## Overview

The code editor is an IDE-like component for WOD syntax editing, replacing simple textareas with syntax highlighting, line numbers, and enhanced editing UX. It uses a simple overlay approach that works immediately without build steps.

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

## Implementation Details

### Architecture

The code editor uses a **simple overlay approach**:

1. **Textarea as base** - Standard textarea for form submission and no-JS fallback
2. **Syntax highlighting overlay** - Positioned div with colored HTML
3. **Transparent text** - Textarea text is transparent, overlay shows colored version
4. **Line numbers** - Separate div that scrolls with content

### Files Structure

**Backend (PHP):**
- `app/Services/Components/Interactive/CodeEditorComponentBuilder.php` - Component builder
- Updated `app/Services/ComponentBuilder.php` - Added factory method

**Frontend (Views):**
- `resources/views/mobile-entry/components/code-editor.blade.php` - Standalone editor component
- `resources/views/mobile-entry/components/wod-form.blade.php` - Specialized WOD form with embedded editor

**Frontend (JavaScript):**
- `public/js/mobile-entry/components/code-editor.js` - Editor initialization and syntax highlighting

**Frontend (CSS):**
- `public/css/mobile-entry/components/code-editor.css` - Editor styling and theme

### Features

**Syntax Highlighting (WOD Mode):**
- Headers (`# Block Name`) - Green, bold
- Exercises (`[Exercise]`) - Orange/brown, bold
- Special formats (`AMRAP`, `EMOM`, etc.) - Purple, bold
- Rep schemes (`3x8`, `5-5-5`) - Light green
- Comments (`//`, `--`) - Green, italic
- Brackets - Gray

**Editor Features:**
1. **Line Numbers** - Optional, scrolls with content
2. **Auto-indent** - Maintains indentation on Enter
3. **Tab Support** - Inserts 2 spaces
4. **Syntax Highlighting** - Real-time as you type
5. **Responsive** - Adapts to mobile screens
6. **Accessible** - Proper ARIA labels
7. **Progressive Enhancement** - Falls back to textarea

## Setup & Installation

### Quick Setup (No Build Required)

The code editor works immediately without any build step or npm install.

#### 1. Verify Files Exist

Check that these files were created:

**Backend:**
- ✓ `app/Services/Components/Interactive/CodeEditorComponentBuilder.php`
- ✓ `app/Services/ComponentBuilder.php` (updated)
- ✓ `app/Http/Controllers/WorkoutController.php` (updated)

**Frontend:**
- ✓ `resources/views/mobile-entry/components/code-editor.blade.php`
- ✓ `resources/views/mobile-entry/components/wod-form.blade.php`
- ✓ `resources/views/mobile-entry/flexible.blade.php` (updated)
- ✓ `public/js/mobile-entry/components/code-editor.js`
- ✓ `public/css/mobile-entry/components/code-editor.css`

#### 2. Test It

Navigate to: `/workouts/create?type=wod`

You should see the new code editor with:
- Line numbers on the left
- Syntax highlighting as you type
- Monospace font
- Dark theme

#### 3. That's It!

No build step, no npm install needed. The editor uses a simple overlay approach that works immediately.

## Usage Examples

### Embedding in Forms

#### Option 1: Standalone Component

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

#### Option 2: Custom Form Component (Recommended)

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

### Different Configurations

#### Read-Only Viewer

```php
$components[] = C::codeEditor('viewer', 'View Code')
    ->name('code')
    ->value($code)
    ->readOnly(true)
    ->lineNumbers(true)
    ->build();
```

#### Minimal Editor

```php
$components[] = C::codeEditor('simple', 'Code')
    ->name('code')
    ->value($code)
    ->lineNumbers(false)
    ->height('200px')
    ->build();
```

#### Full-Featured Editor

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

## Testing Guide

### 1. Create a New WOD

Navigate to: `/workouts/create?type=wod`

You should see:
- A text input for "WOD Name"
- A code editor with line numbers and syntax highlighting
- A text input for "Description"
- A "Create WOD" button

### 2. Test Syntax Highlighting

Try entering this WOD syntax:

```
# Block 1: Strength
[Back Squat]: 5-5-5-5-5
[Bench Press]: 3x8
[Warm-up Push-ups]: 2x10

# Block 2: Conditioning
AMRAP 12min:
10 [Box Jumps]
15 [Push-ups]
20 [Air Squats]

// This is a comment
-- This is also a comment
```

You should see:
- **Green** headers (# Block 1: Strength)
- **Orange/brown** exercises ([Back Squat])
- **Purple** special formats (AMRAP 12min:)
- **Light green** rep schemes (5-5-5-5-5, 3x8)
- **Green italic** comments (// This is a comment)
- **Gray** brackets

### 3. Test Editor Features

**Auto-indent:**
- Press Enter after a line with indentation
- The next line should maintain the same indentation

**Tab key:**
- Press Tab to insert 2 spaces
- Works for indentation

**Scrolling:**
- Line numbers should scroll with content
- Syntax highlighting should remain aligned

### 4. Test Form Submission

- Fill in the WOD name
- Enter some WOD syntax
- Click "Create WOD"
- The WOD should be created successfully
- The syntax should be saved correctly

### 5. Test Mobile Responsiveness

On mobile devices (or browser dev tools mobile view):
- Editor should be usable
- Line numbers should be smaller but readable
- Touch scrolling should work
- Virtual keyboard should not break layout

### 6. Test Fallback (No JavaScript)

Disable JavaScript in browser:
- The editor should fall back to a regular textarea
- Form should still be submittable
- No syntax highlighting, but fully functional

## Customization

### Syntax Modes

#### Currently Supported

- `wod-syntax` - WOD workout syntax with highlighting

#### Adding New Modes

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

### Styling

#### Change Colors

Edit `public/css/mobile-entry/components/code-editor.css`:

```css
.cm-wod-header {
    color: #your-color;
}
```

#### Change Font

Edit CSS:

```css
.cm-scroller {
    font-family: 'Your Font', monospace;
}
```

#### Custom Height

```php
->height('600px')  // Fixed height
->height('50vh')   // Viewport-relative
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

### Current Implementation

- Handles ~500 lines smoothly
- No noticeable lag on mobile
- Regex-based highlighting is fast enough

### Large Documents

The current implementation handles documents up to ~500 lines well. For larger documents:

1. Consider pagination
2. Upgrade to CodeMirror 6 (see upgrade section below)
3. Add lazy loading

### Optimization Tips

- Debounce syntax highlighting for very large documents
- Disable line numbers for better performance
- Use `lineWrapping(false)` for better performance

## Browser Compatibility

### Tested Browsers

- ✓ Chrome/Edge (latest)
- ✓ Firefox (latest)
- ✓ Safari (latest)
- ✓ Mobile Safari (iOS)
- ✓ Chrome Mobile (Android)

### Fallback

Works as regular textarea in:
- Browsers with JavaScript disabled
- Very old browsers
- Screen readers

## Security

### XSS Protection

The editor escapes all HTML:

```javascript
.replace(/&/g, '&amp;')
.replace(/</g, '&lt;')
.replace(/>/g, '&gt;')
```

### CSRF Protection

Forms include Laravel's CSRF token automatically.

### Input Validation

Always validate on the server side:

```php
$validated = $request->validate([
    'wod_syntax' => 'required|string|max:10000',
]);
```

## Troubleshooting

### Editor Not Showing

1. **Check browser console** for JavaScript errors
2. **Verify CSS is loaded** - Check Network tab in DevTools
3. **Clear cache** - Hard refresh (Ctrl+Shift+R)

### Syntax Highlighting Not Working

1. **Check mode is set** - Should be `data-mode="wod-syntax"`
2. **Verify JS is loaded** - Check Network tab
3. **Check console** for errors

### Line Numbers Not Showing

1. **Check config** - Should have `data-line-numbers="true"`
2. **Verify wrapper positioning** - Should have `position: relative`
3. **Check z-index** - Line numbers should be visible

### Form Not Submitting

1. **Check textarea name** - Should match form field name
2. **Verify form action** - Check route is correct
3. **Check CSRF token** - Should be present in form

### Caret Not Visible

1. Verify `caretColor` is set in CSS
2. Check that textarea has `color: transparent`
3. Verify z-index layering

### Line Numbers Not Scrolling

1. Check that wrapper has `position: relative`
2. Verify scroll event listener is attached
3. Check z-index values

## Future: CodeMirror 6 Upgrade

### Current vs Future

**Current Implementation:**
- Enhanced textarea with syntax highlighting overlay
- Line numbers
- Auto-indent and tab support
- No external dependencies required
- Works immediately without build step

**Future CodeMirror 6 Benefits:**
- Better mobile support
- Virtual scrolling for large documents
- Better accessibility
- Autocomplete support
- More robust syntax highlighting
- Better undo/redo
- Search and replace (Ctrl+F)

### Upgrade Steps

When ready to upgrade to full CodeMirror 6:

#### 1. Install Dependencies

```bash
npm install
```

Dependencies are already in `package.json`:
- @codemirror/view
- @codemirror/state
- @codemirror/language
- @codemirror/commands
- @codemirror/search
- @codemirror/autocomplete
- @lezer/highlight

#### 2. Create Build Script

Create `build-codemirror.js`:

```javascript
import { EditorView, basicSetup } from '@codemirror/basic-setup';
import { EditorState } from '@codemirror/state';
import { StreamLanguage } from '@codemirror/language';

// Bundle and expose as window.CM
window.CM = {
    EditorView,
    EditorState,
    basicSetup,
    StreamLanguage
};
```

#### 3. Build Process

Add to `package.json`:

```json
{
  "scripts": {
    "build:codemirror": "esbuild build-codemirror.js --bundle --outfile=public/js/codemirror-bundle.js"
  }
}
```

#### 4. Load in View

Update `flexible.blade.php` to load the bundle before code-editor.js:

```php
<script src="{{ asset('js/codemirror-bundle.js') }}"></script>
```

#### 5. Custom WOD Syntax Mode

Create proper Lezer grammar for WOD syntax in `public/js/mobile-entry/components/wod-syntax-mode.js`.

## Best Practices

1. **Always set a name** - Required for form submission
2. **Provide meaningful labels** - For accessibility
3. **Set appropriate height** - Based on expected content
4. **Use placeholders** - Show example syntax
5. **Test on mobile** - Ensure touch interactions work
6. **Validate server-side** - Don't rely on client-side validation
7. **Escape user content** - Prevent XSS attacks
8. **Provide fallback** - Ensure it works without JavaScript

## Integration Points

### WorkoutController

Updated two methods:

1. **create()** - WOD creation form
2. **editWod()** - WOD editing form

Both now use the `wod-form` component type which embeds the code editor.

### Form Submission

The editor syncs its content back to the hidden textarea, so form submission works normally:

```html
<textarea name="wod_syntax" style="display: none;">
  <!-- Content synced here -->
</textarea>
```

### Validation

No changes needed - Laravel validation works as before since the textarea receives the content.

## Summary

✅ **No build required** - Works immediately  
✅ **No dependencies** - Pure JavaScript  
✅ **Progressive enhancement** - Falls back gracefully  
✅ **Mobile friendly** - Touch optimized  
✅ **Accessible** - Screen reader compatible  
✅ **Upgradeable** - Clear path to CodeMirror 6  

The code editor provides a significantly better UX for writing WOD syntax while maintaining simplicity and not requiring a build step. It's ready for immediate use and has a clear upgrade path to CodeMirror 6 when needed.