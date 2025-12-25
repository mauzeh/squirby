# Changelog - Version 1.4

**Release Date:** December 13, 2025

## Overview

Version 1.4 represents a major expansion of the flexible component system with four new component types, significant enhancements to existing components, and improved mobile UX patterns. This release adds specialized components for fitness tracking, data visualization, content editing, and personal record management.

## New Components

### 1. PR Cards Component

Personal record tracking with visual highlighting and time-based display.

**Features:**
- Display personal records across 1-10 rep ranges
- Horizontal scrolling for multiple rep ranges
- Pulsating halo effect for recent PRs
- Time ago display for achievement dates
- Responsive card layout optimized for mobile

**API:**
```php
C::prCards('Personal Records')
    ->card('1RM', '225 lbs', '2 days ago', true)  // Recent PR with halo
    ->card('5RM', '185 lbs', '1 week ago', false)
    ->card('10RM', '155 lbs', '3 weeks ago', false)
    ->build();
```

**Use Cases:**
- Exercise detail pages
- Workout summaries
- Progress tracking displays

**Files:**
- `app/Services/Components/Display/PRCardsComponentBuilder.php`
- `resources/views/mobile-entry/components/pr-cards.blade.php`
- `public/css/mobile-entry/components/pr-cards.css`

### 2. Calculator Grid Component

Interactive calculation display for exercise metrics and estimations.

**Features:**
- Grid layout for calculation results
- Contextual messages and notes
- Estimated 1RM calculations
- Responsive design for mobile screens
- Integration with exercise data

**API:**
```php
C::calculatorGrid('1RM Calculator')
    ->item('current', '185 lbs', 'Current 5RM')
    ->item('estimated', '208 lbs', 'Estimated 1RM')
    ->item('next', '190 lbs', 'Next Goal')
    ->note('Based on Epley formula')
    ->build();
```

**Use Cases:**
- Exercise analysis pages
- Training calculators
- Progress projections

**Files:**
- `app/Services/Components/Display/CalculatorGridComponentBuilder.php`
- `resources/views/mobile-entry/components/calculator-grid.blade.php`
- `public/css/mobile-entry/components/calculator-grid.css`

### 3. Code Editor Component

IDE-like syntax editor with highlighting for WOD (Workout of the Day) syntax.

**Features:**
- Syntax highlighting for exercise notation
- Auto-completion for exercise names
- Auto-closing brackets on selection
- Monospace font with proper spacing
- Custom styling for WOD syntax fields

**API:**
```php
C::codeEditor('wod-editor', 'Workout Description')
    ->content('3x5 Bench Press [185]')
    ->placeholder('Enter workout using WOD syntax...')
    ->autocomplete(['Bench Press', 'Squats', 'Deadlift'])
    ->build();
```

**WOD Syntax Examples:**
- `3x5 Bench Press [185]` - 3 sets of 5 reps at 185 lbs
- `AMRAP 10: 10 Push-ups, 15 Squats` - As Many Rounds As Possible
- `5 rounds: 200m Run, 20 Burpees` - Structured rounds

**Use Cases:**
- Workout template creation
- Exercise program editing
- Training plan documentation

**Files:**
- `app/Services/Components/Interactive/CodeEditorComponentBuilder.php`
- `resources/views/mobile-entry/components/code-editor.blade.php`
- `public/css/mobile-entry/components/code-editor.css`
- `public/js/code-editor.js`

### 4. Markdown Component

Rich text rendering with custom dark mode styling and exercise integration.

**Features:**
- Custom dark mode color palette
- Monospace font support
- Heading hierarchy styling
- Exercise bracket processing
- Link color theming
- Improved contrast and readability

**API:**
```php
C::markdown('## Workout Notes\n\nCompleted **3x5 Bench Press** at 185 lbs.\n\nNext session: try 190 lbs.')
    ->processExercises(true)  // Convert [Exercise Name] to links
    ->darkMode(true)
    ->build();
```

**Features:**
- Automatic exercise link generation from `[Exercise Name]` syntax
- Responsive typography
- Consistent theming with app design
- Support for standard Markdown syntax

**Use Cases:**
- Workout descriptions
- Exercise notes
- Training program documentation
- Rich text content display

**Files:**
- `app/Services/Components/Display/MarkdownComponentBuilder.php`
- `resources/views/mobile-entry/components/markdown.blade.php`
- `public/css/mobile-entry/components/markdown.css`

## Enhanced Existing Components

### Chart Component Enhancements

**New Features:**
- Font sizing for chart labels and legends
- Label color styling support
- Improved responsive layout
- Better mobile optimization

**API Updates:**
```php
C::chart('exercise-progress', 'Progress Over Time')
    ->labelColor('#ffffff')
    ->fontSize(14)
    ->responsive(true)
    ->build();
```

### Table Component Enhancements

**New Features:**
- Badge support with proper CSS styling
- Bulk selection with automatic script loading
- Action button wrapping for multiple actions
- Chevron icons for clickable rows
- Text wrapping and emphasized badges
- Spaced rows for better readability
- HTML subtitle support (unescaped)

**API Updates:**
```php
C::table()
    ->row(1, 'Exercise Name', '<strong>185 lbs</strong>', 'Personal Record')
        ->badge('PR', 'success')
        ->badge('Recent', 'info')
        ->linkAction('fa-edit', route('edit'), 'Edit')
        ->formAction('fa-trash', route('delete'), 'DELETE', [], 'Delete', 'btn-danger', true)
        ->chevron(true)  // Add chevron for clickable indication
        ->add()
    ->bulkSelection(true)
    ->build();
```

### Form Component Enhancements

**New Features:**
- Customizable submit button styling
- Numeric input mode for number fields
- Text selection on input focus
- Press-and-hold increment/decrement
- Improved mobile input handling

**API Updates:**
```php
C::form('workout-form', 'Log Exercise')
    ->numericField('weight', 'Weight:', 185, 5, 45, 500)
    ->submitButton('Log Exercise', 'btn-success btn-large')
    ->inputMode('numeric')  // Mobile numeric keyboard
    ->selectOnFocus(true)   // Select text on focus
    ->build();
```

### Mobile Entry UX Improvements

**New Features:**
- Search UX improvements with icons
- Updated placeholder text
- Metrics-first logging flow preference
- Exercise selection expansion
- Improved empty state messaging
- Contextual help messages
- User preference for form value prefilling
- Next button disabled for today/future dates

### Navigation Component Enhancements

**New Features:**
- Conditional next button disabling
- Better date handling for today/future
- Improved accessibility

## CSS Architecture Improvements

### Component-Based Organization

Refactored CSS into organized component structure:

```
public/css/mobile-entry/components/
├── navigation.css
├── title.css
├── messages.css
├── summary.css
├── button.css
├── form.css
├── table.css
├── list.css
├── badges.css
├── collapsible.css
├── pr-cards.css          # New
├── calculator-grid.css   # New
├── chart.css            # Enhanced
├── markdown.css         # New
└── code-editor.css      # New
```

### Color Palette Updates

**New CSS Variables:**
```css
:root {
    --color-title: #ffffff;
    --color-primary-light: #4a90e2;
    --color-success-dark: #1e7e34;
    --spacing-xl: 2rem;
    --touch-target-compact: 33px;
}
```

### Mobile-First Enhancements

- Improved touch targets
- Better spacing variables
- Enhanced mobile input handling
- Responsive component layouts

## JavaScript Enhancements

### Automatic Script Loading

Components now automatically load required JavaScript:

```php
// Component declares script requirement
'requiresScript' => ['code-editor', 'chart-config']

// View automatically includes scripts
@foreach(array_keys($requiredScripts) as $scriptName)
    <script src="{{ asset('js/' . $scriptName . '.js') }}"></script>
@endforeach
```

### New JavaScript Features

**Code Editor (`public/js/code-editor.js`):**
- Syntax highlighting
- Auto-completion
- Bracket auto-closing
- Exercise name suggestions

**Enhanced Mobile Entry (`public/js/mobile-entry.js`):**
- Improved search interactions
- Better form handling
- Enhanced touch responses

## Service Layer Improvements

### ComponentBuilder Reorganization

Split ComponentBuilder into organized subdirectories:

```
app/Services/Components/
├── Charts/
│   └── ChartComponentBuilder.php
├── Display/
│   ├── TitleComponentBuilder.php
│   ├── MessagesComponentBuilder.php
│   ├── SummaryComponentBuilder.php
│   ├── PRCardsComponentBuilder.php      # New
│   ├── CalculatorGridComponentBuilder.php # New
│   └── MarkdownComponentBuilder.php     # New
├── Interactive/
│   ├── FormComponentBuilder.php
│   ├── ButtonComponentBuilder.php
│   ├── CodeEditorComponentBuilder.php   # New
│   ├── BulkActionFormComponentBuilder.php
│   └── SelectAllControlComponentBuilder.php
├── Lists/
│   ├── ItemListComponentBuilder.php
│   └── ItemBuilder.php
├── Navigation/
│   └── NavigationComponentBuilder.php
└── Tables/
    ├── TableComponentBuilder.php
    ├── TableRowBuilder.php
    └── TableSubItemBuilder.php
```

### Helper Methods

**New ComponentBuilder Helpers:**
```php
// Automatic session message handling
C::messagesFromSession(); // Returns null if no messages

// Raw HTML for complex content
C::rawHtml('<div class="custom">Content</div>');
```

## Production Usage

### Workout Templates Integration

- PR cards for exercise history
- Calculator grids for 1RM estimates
- Code editor for WOD syntax
- Enhanced table display with badges

### Exercise Logging

- Improved form UX with numeric inputs
- Press-and-hold increment controls
- Better mobile keyboard handling
- Contextual help messages

### Data Visualization

- Enhanced charts with better styling
- PR highlighting with visual effects
- Responsive layouts for mobile

## Breaking Changes

None - all changes are backward compatible.

## Performance Improvements

- Automatic script loading reduces manual management
- Component-based CSS improves caching
- Optimized mobile interactions
- Reduced JavaScript bundle size through modular loading

## Browser Compatibility

All features tested and working in:
- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Mobile browsers (iOS Safari, Chrome Mobile)

## Testing

- All existing tests continue to pass
- New components include comprehensive test coverage
- Mobile UX tested on actual devices
- Performance tested with large datasets

## Documentation Updates

This changelog documents all changes since v1.3. Additional documentation needed:

- PR Cards component guide
- Calculator Grid component guide  
- Code Editor component guide
- Markdown component guide
- Updated quick reference with new APIs

## Statistics

**Since v1.3 (November 13, 2025):**
- **328 total commits**
- **4 new component types** added
- **50+ enhancements** to existing components
- **15+ new CSS files** for component styling
- **5+ new JavaScript modules**
- **100+ new API methods** across components

## Contributors

- Enhanced by development team
- Tested in production environment
- Feedback incorporated from real-world usage
- Mobile UX validated on multiple devices

## Next Steps

Potential future enhancements:
- Drag-and-drop component reordering
- Component state persistence
- Advanced chart types (pie, radar)
- Rich text editor component
- Video/media components
- Animation and transition effects
- Component composition patterns
- Advanced form validation components

## Support

For questions or issues:
1. Check the [Component Builder Quick Reference](component-builder-quick-reference.md)
2. Review component-specific documentation (when available)
3. Look at working examples in production controllers
4. Check [Testing Guide](testing.md) for test patterns
5. Examine component builder source code for API details

---

**Version:** 1.4  
**Status:** Production Ready ✅  
**Release Date:** December 13, 2025  
**Commits Since v1.3:** 328  
**New Components:** 4  
**Enhanced Components:** 8+