# Code Editor Testing Guide

## Testing the WOD Syntax Editor

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

### 3. Test Line Numbers

- Line numbers should appear on the left
- They should scroll with the content
- They should update as you add/remove lines

### 4. Test Editor Features

**Auto-indent:**
- Press Enter after a line with indentation
- The next line should maintain the same indentation

**Tab key:**
- Press Tab to insert 2 spaces
- Works for indentation

**Scrolling:**
- Line numbers should scroll with content
- Syntax highlighting should remain aligned

### 5. Test Form Submission

- Fill in the WOD name
- Enter some WOD syntax
- Click "Create WOD"
- The WOD should be created successfully
- The syntax should be saved correctly

### 6. Test Edit WOD

- Navigate to an existing WOD
- Click to edit it
- The code editor should show the existing syntax
- Syntax highlighting should work
- Changes should save correctly

### 7. Test Mobile Responsiveness

On mobile devices (or browser dev tools mobile view):
- Editor should be usable
- Line numbers should be smaller but readable
- Touch scrolling should work
- Virtual keyboard should not break layout

### 8. Test Fallback (No JavaScript)

Disable JavaScript in browser:
- The editor should fall back to a regular textarea
- Form should still be submittable
- No syntax highlighting, but fully functional

## Known Issues to Watch For

1. **Caret visibility**: Make sure the cursor is visible when typing
2. **Scroll sync**: Line numbers and highlighting should stay aligned
3. **Performance**: Large WODs (100+ lines) should still be responsive
4. **Copy/paste**: Should work normally
5. **Undo/redo**: Browser's native undo should work

## Browser Compatibility

Tested on:
- [ ] Chrome/Edge (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Mobile Safari (iOS)
- [ ] Chrome Mobile (Android)

## Future Enhancements

When upgrading to CodeMirror 6:
- Better mobile support
- Autocomplete for exercise names
- Real-time syntax validation
- Better undo/redo
- Search and replace (Ctrl+F)
