# Code Editor - CodeMirror 6 Upgrade Path

## Current Implementation

The code editor currently uses a **simple overlay approach** with syntax highlighting for immediate functionality:

- Enhanced textarea with syntax highlighting overlay
- Line numbers
- Auto-indent and tab support
- No external dependencies required
- Works immediately without build step

## Future: CodeMirror 6 Integration

When ready to upgrade to full CodeMirror 6:

### 1. Install Dependencies

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

### 2. Create Build Script

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

### 3. Build Process

Add to `package.json`:

```json
{
  "scripts": {
    "build:codemirror": "esbuild build-codemirror.js --bundle --outfile=public/js/codemirror-bundle.js"
  }
}
```

### 4. Load in View

Update `flexible.blade.php` to load the bundle before code-editor.js:

```php
<script src="{{ asset('js/codemirror-bundle.js') }}"></script>
```

### 5. Custom WOD Syntax Mode

Create proper Lezer grammar for WOD syntax in `public/js/mobile-entry/components/wod-syntax-mode.js`.

## Benefits of Upgrade

- Better mobile support
- Virtual scrolling for large documents
- Better accessibility
- Autocomplete support
- More robust syntax highlighting
- Better undo/redo
- Search and replace (Ctrl+F)

## Current Limitations

The simple overlay approach has these limitations:
- No autocomplete
- No advanced features like multiple cursors
- Syntax highlighting is regex-based (less robust)
- No virtual scrolling

These are acceptable trade-offs for immediate functionality without a build step.
