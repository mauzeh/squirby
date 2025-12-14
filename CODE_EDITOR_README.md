# WOD Syntax Code Editor

An IDE-like code editor for writing WOD (Workout of the Day) syntax with syntax highlighting, line numbers, and enhanced editing features.

## ✨ Features

- **Syntax Highlighting** - Color-coded WOD syntax elements
- **Line Numbers** - Optional line numbers with scroll sync
- **Auto-Indent** - Maintains indentation on new lines
- **Tab Support** - Insert spaces with Tab key
- **Dark Theme** - Matches application theme
- **Mobile Optimized** - Touch-friendly on all devices
- **Progressive Enhancement** - Works without JavaScript
- **No Build Required** - Ready to use immediately

## 🚀 Quick Start

### Using in Controllers

```php
use App\Services\ComponentBuilder as C;

// In your controller method
$components[] = C::codeEditor('wod-editor', 'WOD Syntax')
    ->name('wod_syntax')
    ->value($workout->wod_syntax ?? '')
    ->mode('wod-syntax')
    ->height('400px')
    ->lineNumbers(true)
    ->build();
```

### WOD Syntax Example

```
# Block 1: Strength
[Back Squat]: 5-5-5-5-5
[Bench Press]: 3x8
[Warm-up]: 2x10

# Block 2: Conditioning
AMRAP 12min:
10 [Box Jumps]
15 [Push-ups]
20 [Air Squats]

// This is a comment
```

## 📖 Documentation

- **[Setup Guide](docs/code-editor-setup.md)** - Installation and setup
- **[Usage Guide](docs/code-editor-usage.md)** - How to use the component
- **[Testing Guide](docs/code-editor-testing.md)** - Testing checklist
- **[Implementation Details](docs/code-editor-implementation.md)** - Technical architecture
- **[Upgrade Path](docs/code-editor-upgrade.md)** - CodeMirror 6 upgrade instructions

## 🎨 Syntax Highlighting

| Element | Color | Example |
|---------|-------|---------|
| Headers | Green | `# Block 1: Strength` |
| Exercises | Orange | `[Back Squat]` |

| Special Formats | Purple | `AMRAP 12min:` |
| Rep Schemes | Light Green | `5-5-5-5-5`, `3x8` |
| Comments | Green Italic | `// comment` |
| Brackets | Gray | `[`, `]` |

## 🔧 Configuration Options

```php
C::codeEditor($id, $label)
    ->name($fieldName)           // Form field name (required)
    ->value($content)            // Initial content
    ->placeholder($example)      // Placeholder text
    ->mode('wod-syntax')         // Syntax mode
    ->theme('dark')              // Theme
    ->height('400px')            // Editor height
    ->lineNumbers(true)          // Show line numbers
    ->lineWrapping(true)         // Wrap long lines
    ->readOnly(false)            // Read-only mode
    ->autofocus(false)           // Auto-focus on load
    ->ariaLabel($label)          // Accessibility label
    ->build();
```

## 📱 Mobile Support

- Touch-optimized scrolling
- Responsive font sizes
- Virtual keyboard handling
- Larger tap targets
- Adjusted line number width

## ♿ Accessibility

- Proper ARIA labels
- Keyboard navigation
- Screen reader compatible
- Focus indicators
- Fallback to textarea

## 🔒 Security

- HTML escaping prevents XSS
- CSRF protection on forms
- Server-side validation
- No eval() or dangerous operations

## 🏗️ Architecture

### Current: Simple Overlay Approach

- Enhanced textarea with syntax highlighting overlay
- No external dependencies
- Works immediately without build step
- Handles ~500 lines smoothly

### Future: CodeMirror 6

Dependencies already in `package.json` for easy upgrade:
- Better mobile support
- Virtual scrolling
- Autocomplete
- Advanced features

See [Upgrade Guide](docs/code-editor-upgrade.md) for details.

## 📂 File Structure

```
app/Services/Components/Interactive/
  └── CodeEditorComponentBuilder.php

resources/views/mobile-entry/components/
  ├── code-editor.blade.php
  └── wod-form.blade.php

public/
  ├── js/mobile-entry/components/
  │   └── code-editor.js
  └── css/mobile-entry/components/
      └── code-editor.css

docs/
  ├── code-editor-setup.md
  ├── code-editor-usage.md
  ├── code-editor-testing.md
  ├── code-editor-implementation.md
  └── code-editor-upgrade.md
```

## 🧪 Testing

Navigate to `/workouts/create?type=wod` to test the editor.

See [Testing Guide](docs/code-editor-testing.md) for comprehensive testing checklist.

## 🐛 Troubleshooting

### Editor not showing?
- Check browser console for errors
- Verify CSS and JS files are loaded
- Hard refresh (Ctrl+Shift+R)

### Syntax highlighting not working?
- Verify `data-mode="wod-syntax"` is set
- Check that code-editor.js is loaded
- Look for JavaScript errors in console

### Line numbers not scrolling?
- Check wrapper has `position: relative`
- Verify scroll event listener is attached
- Check z-index values

See [Setup Guide](docs/code-editor-setup.md) for more troubleshooting tips.

## 🎯 Use Cases

### WOD Creation
Perfect for writing workout programming with syntax highlighting.

### WOD Editing
Edit existing workouts with better UX than plain textarea.

### Read-Only Display
Show WOD syntax with highlighting in read-only mode.

### Custom Syntax Modes
Extend for other syntax types (markdown, JSON, etc.).

## 🚦 Browser Compatibility

| Browser | Status |
|---------|--------|
| Chrome/Edge | ✅ Fully supported |
| Firefox | ✅ Fully supported |
| Safari | ✅ Fully supported |
| Mobile Safari | ✅ Fully supported |
| Chrome Mobile | ✅ Fully supported |
| No JavaScript | ✅ Falls back to textarea |

## 📈 Performance

- Handles ~500 lines smoothly
- No noticeable lag on mobile
- Regex-based highlighting is fast
- Upgrade to CodeMirror 6 for unlimited size

## 🔮 Future Enhancements

### Short Term
- Keyboard shortcuts (Ctrl+S)
- Fullscreen mode
- Syntax error indicators

### Medium Term
- CodeMirror 6 upgrade
- Autocomplete for exercises
- Real-time validation
- Search/replace (Ctrl+F)

### Long Term
- Collaborative editing
- Version history
- WOD templates/snippets
- Exercise library integration

## 📝 License

Part of the Quantified Athletics application.

## 🤝 Contributing

When adding new features:
1. Update relevant documentation
2. Test on mobile devices
3. Ensure accessibility
4. Maintain backward compatibility

## 📞 Support

- Check documentation in `docs/` folder
- Review troubleshooting section
- Test with simple examples first
- Verify all files are in place

---

**Ready to use!** No build step required. Just start creating WODs with the new editor. 🎉
