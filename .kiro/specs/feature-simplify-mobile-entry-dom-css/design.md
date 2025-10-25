# Design Document

## Overview

This design consolidates the mobile-entry interfaces for lift-logs and food-logs by creating a shared component architecture that eliminates code duplication while maintaining all existing functionality. The solution focuses on extracting identical code patterns into reusable Blade components, optimizing CSS through consolidation, and streamlining JavaScript through shared utilities.

## Architecture

### Component-Based Architecture

The design implements a hierarchical component structure where shared UI patterns are extracted into reusable components:

```
components/mobile-entry/
├── layouts/
│   └── base.blade.php                  # Base layout with common structure
├── message-system.blade.php            # Error/success/validation messages
├── date-navigation.blade.php           # Prev/Today/Next navigation
├── page-title.blade.php               # Conditional date title display
├── item-card.blade.php                # Unified card component
├── add-item-button.blade.php          # Add exercise/food button
├── number-input.blade.php             # Increment/decrement input
└── empty-state.blade.php              # No items message
```

### Template Hierarchy

```
app.blade.php
└── components/mobile-entry/layouts/base.blade.php
    ├── lift-logs/mobile-entry.blade.php    # Lift-specific content only
    └── food-logs/mobile-entry.blade.php    # Food-specific content only
```

## Components and Interfaces

### 1. Message System Component

**File:** `resources/views/components/mobile-entry/message-system.blade.php`

**Interface:**
```php
<x-mobile-entry.message-system
    :errors="$errors ?? null"
    :success="session('success') ?? null"
    :show-validation="true" />
```

**Implementation:**
- Consolidates identical error/success/validation message markup
- Supports auto-hide functionality through shared JavaScript
- Maintains existing styling and behavior

### 2. Date Navigation Component

**File:** `resources/views/components/mobile-entry/date-navigation.blade.php`

**Interface:**
```php
<x-mobile-entry.date-navigation
    :selected-date="$selectedDate"
    route-name="lift-logs.mobile-entry" />
```

**Implementation:**
- Generates prev/today/next navigation links
- Accepts route name parameter for different contexts
- Maintains existing Carbon date logic

### 3. Page Title Component

**File:** `resources/views/components/mobile-entry/page-title.blade.php`

**Interface:**
```php
<x-mobile-entry.page-title :selected-date="$selectedDate" />
```

**Implementation:**
- Handles Today/Yesterday/Tomorrow/formatted date logic
- Identical across both templates

### 4. Item Card Component

**File:** `resources/views/components/mobile-entry/item-card.blade.php`

**Interface:**
```php
<x-mobile-entry.item-card
    :title="$item->name"
    :delete-route="route('items.destroy', $item->id)"
    delete-confirm-text="Are you sure?"
    :hidden-fields="['redirect_to' => 'mobile-entry']"
    :move-actions="$moveButtons ?? null">
    {{ $cardContent }}
</x-mobile-entry.item-card>
```

**Implementation:**
- Unified card structure with configurable actions
- Supports optional move up/down buttons for lift cards
- Flexible content slot for different card types
- Standardized delete button functionality

### 5. Number Input Component

**File:** `resources/views/components/mobile-entry/number-input.blade.php`

**Interface:**
```php
<x-mobile-entry.number-input
    name="quantity"
    id="quantity-input"
    :value="1"
    label="Quantity:"
    unit="grams"
    :step="0.01"
    :min="0" />
```

**Implementation:**
- Standardized increment/decrement button layout
- Configurable step values and validation
- Integrated with shared JavaScript handlers

### 6. Add Item Button Component

**File:** `resources/views/components/mobile-entry/add-item-button.blade.php`

**Interface:**
```php
<x-mobile-entry.add-item-button
    id="add-exercise-button"
    label="Add exercise"
    target-container="exercise-list-container" />
```

**Implementation:**
- Standardized button styling and behavior
- Configurable label and target container
- Integrated with shared JavaScript

## Data Models

### Component Parameter Models

**MessageSystemData:**
```php
[
    'errors' => Collection|null,
    'success' => string|null,
    'showValidation' => boolean
]
```

**DateNavigationData:**
```php
[
    'selectedDate' => Carbon,
    'routeName' => string
]
```

**ItemCardData:**
```php
[
    'title' => string,
    'deleteRoute' => string,
    'deleteConfirmText' => string,
    'hiddenFields' => array,
    'moveActions' => string|null,  // HTML content
    'content' => string            // HTML content
]
```

**NumberInputData:**
```php
[
    'name' => string,
    'id' => string,
    'value' => numeric,
    'label' => string,
    'unit' => string|null,
    'step' => numeric,
    'min' => numeric
]
```

## Error Handling

### Component Error Handling

1. **Missing Parameters:** Components validate required parameters and provide meaningful error messages
2. **Invalid Data Types:** Type checking for critical parameters with fallback defaults
3. **Route Validation:** Date navigation validates route existence before generating links
4. **Content Sanitization:** All user-provided content is properly escaped

### Graceful Degradation

1. **JavaScript Disabled:** All functionality works without JavaScript
2. **CSS Loading Failure:** Semantic HTML structure remains usable
3. **Component Missing:** Fallback to inline markup with deprecation warning

## Testing Strategy

### Component Testing

1. **Unit Tests:** Test each component in isolation with various parameter combinations
2. **Integration Tests:** Test components within template context
3. **Visual Regression Tests:** Ensure identical appearance after consolidation

### Template Testing

1. **Functional Tests:** Verify all existing functionality works after refactoring
2. **Performance Tests:** Measure DOM reduction and CSS optimization impact
3. **Accessibility Tests:** Validate WCAG compliance maintained

### Cross-Browser Testing

1. **Desktop Browsers:** Chrome, Firefox, Safari, Edge
2. **Mobile Browsers:** iOS Safari, Chrome Mobile, Samsung Internet
3. **Responsive Testing:** All breakpoints and orientations

## CSS Architecture

### Mobile-First Design Philosophy

The CSS architecture follows a mobile-first approach where the base styles are optimized for mobile devices, with progressive enhancement for larger screens. There is no separation between "mobile" and "desktop" CSS - all styles are unified in a single responsive system.

### File Structure

```
public/css/
├── entry-interface.css                 # Consolidated mobile-first CSS
├── components/
│   ├── message-system.css             # Component-specific styles
│   ├── item-card.css                  # Card component styles
│   ├── number-input.css               # Input component styles
│   └── navigation.css                 # Navigation styles
└── legacy/
    ├── mobile-entry-shared.css        # Deprecated
    └── mobile-entry-lift.css          # Deprecated
```

### CSS Optimization Strategy

1. **Mobile-First Responsive Design:** Base styles target mobile devices with min-width media queries for larger screens
2. **Component-Based Organization:** Group styles by component rather than device type
3. **Selector Optimization:** Reduce specificity and improve performance
4. **Duplicate Removal:** Eliminate redundant rules through component consolidation
5. **Progressive Enhancement:** Add complexity for larger screens rather than overriding mobile styles

### CSS Variables (Mobile-First)

```css
:root {
  /* Base mobile-optimized values */
  --entry-bg: #2a2a2a;
  --entry-text: #f2f2f2;
  --entry-accent: #007bff;
  --entry-success: #28a745;
  --entry-danger: #dc3545;
  --entry-border-radius: 8px;
  --entry-spacing: 15px;
  --entry-touch-target: 44px;        /* Minimum touch target size */
  --entry-font-size-base: 1rem;      /* Mobile-optimized base font */
  --entry-font-size-large: 1.5rem;   /* Large inputs for mobile */
}
```

## JavaScript Architecture

### Shared Utilities

**File:** `public/js/mobile-entry-shared.js`

```javascript
// Message system utilities
MobileEntry.Messages = {
    autoHide: function(duration = 5000) { /* ... */ },
    show: function(message, type) { /* ... */ },
    hide: function(messageId) { /* ... */ }
};

// Form utilities
MobileEntry.Forms = {
    setupIncrementButtons: function(selector) { /* ... */ },
    validateForm: function(formData, rules) { /* ... */ },
    preventDoubleSubmit: function(form) { /* ... */ }
};

// Navigation utilities
MobileEntry.Navigation = {
    setupAddItemButtons: function() { /* ... */ },
    hideAllLists: function() { /* ... */ },
    showContainer: function(containerId) { /* ... */ }
};
```

### Event Management

1. **Delegated Events:** Use event delegation for dynamic content
2. **Namespace Events:** Prevent conflicts with other JavaScript
3. **Memory Management:** Proper cleanup of event listeners

## Performance Optimizations

### DOM Optimization

1. **Element Reduction:** Target 50% reduction in DOM elements
2. **Nesting Simplification:** Reduce unnecessary wrapper elements
3. **Class Optimization:** Minimize CSS class usage per element

### CSS Optimization

1. **File Size Reduction:** Target 30% reduction in total CSS through mobile-first consolidation
2. **Selector Performance:** Optimize for faster CSS parsing on mobile devices
3. **Touch-Friendly Design:** Ensure all interactive elements meet minimum 44px touch targets
4. **Mobile Performance:** Optimize for mobile rendering and bandwidth constraints

### JavaScript Optimization

1. **Code Splitting:** Separate shared utilities from page-specific code
2. **Minification:** Compress JavaScript for production
3. **Caching:** Leverage browser caching for shared files

## Migration Strategy

### Phase 1: Component Creation
1. Create all shared components with existing functionality
2. Test components in isolation
3. Validate component interfaces

### Phase 2: Template Refactoring
1. Refactor lift-logs template to use shared components
2. Refactor food-logs template to use shared components
3. Remove duplicate code from templates

### Phase 3: CSS Consolidation
1. Merge CSS files into component-based structure
2. Remove redundant styles
3. Optimize selectors and media queries

### Phase 4: JavaScript Consolidation
1. Create shared JavaScript utilities
2. Remove duplicate event handlers
3. Optimize for performance

### Phase 5: Cleanup and Optimization
1. Remove deprecated files
2. Update documentation
3. Performance testing and optimization

## Backward Compatibility

### Component Versioning
- Components maintain stable interfaces
- Deprecated parameters supported with warnings
- Migration guides for breaking changes

### CSS Compatibility
- Maintain existing class names during transition
- Gradual deprecation of unused styles
- Fallback styles for older browsers

### JavaScript Compatibility
- Maintain existing global functions during transition
- Progressive enhancement approach
- Graceful degradation for older browsers