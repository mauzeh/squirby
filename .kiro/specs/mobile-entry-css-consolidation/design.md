# Design Document

## Overview

This design consolidates the CSS styling between food-logs/mobile-entry and lift-logs/mobile-entry forms by extracting shared styles into reusable components while maintaining form-specific functionality. The consolidation will reduce CSS duplication from approximately 1,300+ lines to under 400 lines of shared styles, with form-specific styles being much smaller and focused.

## Architecture

### CSS Organization Structure

```
resources/css/
├── mobile-entry-shared.css (new - shared styles)
├── mobile-entry-food.css (new - food-specific styles)  
└── mobile-entry-lift.css (new - lift-specific styles)
```

### Style Consolidation Strategy

1. **Shared Base Styles**: Common container, navigation, form, and responsive styles
2. **Component-Specific Extensions**: Unique functionality styling for each form
3. **Consistent Class Naming**: Standardized class names across both forms
4. **Responsive Behavior**: Unified mobile-first responsive patterns

## Components and Interfaces

### Shared Style Components

#### 1. Container and Layout
```css
/* Shared base container */
.mobile-entry-container {
    max-width: 600px;
    margin: 20px auto;
    padding: 15px;
    background-color: #2a2a2a;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
    color: #f2f2f2;
}

/* Date navigation - standardized from lift-logs */
.date-navigation-mobile {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    font-size: 1.2em;
}

.nav-button {
    background-color: #007bff;
    color: white;
    padding: 10px 15px;
    border-radius: 5px;
    text-decoration: none;
    font-weight: bold;
}
```

#### 2. Button System
```css
/* Consolidated button base */
.button-large {
    color: white;
    text-align: center;
    display: block;
    width: 100%;
    box-sizing: border-box;
    border: none;
    padding: 15px 25px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 1.5em;
    font-weight: bold;
}

/* Color variants */
.button-green { background-color: #28a745; }
.button-blue { background-color: #007bff; }
.button-gray { background-color: #6c757d; }
.button-danger { background-color: #dc3545; }

/* Delete button - standardized from lift-logs */
.delete-button {
    background-color: #dc3545;
    color: white;
    border: none;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    cursor: pointer;
    font-size: 16px;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}
```

#### 3. Form Input System
```css
/* Shared form styling */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #f2f2f2;
    font-weight: bold;
    font-size: 1.1em;
}

.input-group {
    display: flex;
    align-items: center;
}

.large-input {
    text-align: center;
    flex-grow: 1;
    border-radius: 0;
    font-size: 2.2em;
    border: none;
    padding: 15px 10px;
    background-color: #4a4a4a;
    color: #f2f2f2;
    box-sizing: border-box;
    font-weight: bold;
}

.large-textarea {
    width: 100%;
    background-color: #2a2a2a;
    border: 1px solid #555;
    border-radius: 5px;
    color: #f2f2f2;
    font-size: 1.1em;
    padding: 15px;
    resize: vertical;
    min-height: 80px;
    box-sizing: border-box;
}

.decrement-button,
.increment-button {
    background-color: #6c757d;
    color: white;
    border: none;
    padding: 15px 20px;
    cursor: pointer;
    font-size: 1.5em;
    touch-action: manipulation;
}
```

#### 4. List and Selection Interface
```css
/* Generic list system */
.item-list-container {
    margin-top: 20px;
    padding: 20px;
    background-color: #3a3a3a;
    border-radius: 8px;
}

.item-list {
    display: flex;
    flex-direction: column;
}

.item-list-item {
    color: #f2f2f2;
    padding: 15px;
    text-decoration: none;
    border-bottom: 1px solid #555;
    font-size: 1.2em;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
}

.item-name {
    flex: 1;
    min-width: 0;
    z-index: 2;
    position: relative;
    background: inherit;
    padding-right: 10px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.item-label {
    flex-shrink: 0;
    font-size: 0.9em;
    opacity: 0.8;
    z-index: 1;
}
```

#### 5. Message System
```css
/* Comprehensive message system from food-logs */
.message-container {
    margin: 15px 0;
    padding: 0;
    border-radius: 8px;
    animation: slideIn 0.3s ease-out;
}

.message-error {
    background-color: #dc3545;
    border-left: 4px solid #a71e2a;
}

.message-success {
    background-color: #28a745;
    border-left: 4px solid #1e7e34;
}

.message-validation {
    background-color: #ffc107;
    border-left: 4px solid #d39e00;
}
```

#### 6. Responsive Behavior
```css
/* Mobile-first responsive design */
@media (max-width: 768px) {
    .mobile-entry-container {
        margin: 10px;
        padding: 10px;
    }
    
    /* Form fields on separate lines */
    .form-group {
        display: flex;
        flex-direction: column;
        align-items: stretch;
    }
    
    .form-group label {
        flex: none;
        text-align: left;
        margin-bottom: 5px;
    }
    
    /* Consistent touch targets */
    .nav-button {
        min-height: 44px;
        min-width: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .item-list-item {
        min-height: 44px;
        padding: 12px 15px;
    }
    
    .decrement-button,
    .increment-button {
        min-width: 44px;
        min-height: 44px;
        padding: 12px 16px;
    }
}
```

### Form-Specific Components

#### Food-Logs Specific Styles
```css
/* Food selection interface */
.food-list-item.ingredient-item {
    background-color: #2d4a3a;
}

.food-list-item.meal-item {
    background-color: #4a3a2d;
}

/* Daily nutrition totals */
.daily-nutrition-totals {
    margin-top: 30px;
    padding: 20px;
    background-color: #3a3a3a;
    border-radius: 8px;
    border-left: 4px solid #28a745;
}

/* Food log entries */
.food-log-entry {
    background-color: #3a3a3a;
    border-radius: 8px;
    padding: 15px;
    border-left: 4px solid #007bff;
}
```

#### Lift-Logs Specific Styles
```css
/* Program cards */
.program-card {
    background-color: #3a3a3a;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
    position: relative;
}

/* Band color selector */
.band-color-selector {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
    justify-content: center;
}

/* Completion badge */
.completed-badge {
    border-left: 5px solid #28a745;
    padding-left: 20px;
    position: relative;
}

/* Exercise list variants */
.exercise-list-item.recommended-exercise {
    background-color: #4a4a2d;
}

.exercise-list-item.user-exercise {
    background-color: #2d3a4a;
}
```

## Data Models

### CSS Class Mapping

#### Before Consolidation
```
Food-logs classes:
- .button-large, .button-green, .button-blue, .button-gray
- .food-list-container, .food-list, .food-list-item
- .food-name, .food-label
- Custom delete button styling

Lift-logs classes:
- .large-button, .button-green (different implementation)
- .exercise-list-container, .exercise-list, .exercise-list-item
- .exercise-name, .exercise-label
- .delete-button (standardized)
```

#### After Consolidation
```
Shared classes:
- .button-large (consolidated), .button-green, .button-blue, .button-gray, .button-danger
- .item-list-container, .item-list, .item-list-item
- .item-name, .item-label
- .delete-button (standardized from lift-logs)

Form-specific modifiers:
- .food-list-item, .exercise-list-item (extend .item-list-item)
- .ingredient-item, .meal-item, .recommended-exercise, .user-exercise
```

### File Structure Changes

#### Current Structure
```
resources/views/food_logs/mobile-entry.blade.php (1,320 lines with embedded CSS)
resources/views/lift-logs/mobile-entry.blade.php (580 lines with embedded CSS)
```

#### New Structure
```
resources/css/mobile-entry-shared.css (~350 lines)
resources/css/mobile-entry-food.css (~150 lines)
resources/css/mobile-entry-lift.css (~100 lines)

resources/views/food_logs/mobile-entry.blade.php (HTML only, ~400 lines)
resources/views/lift-logs/mobile-entry.blade.php (HTML only, ~300 lines)
```

## Error Handling

### CSS Loading Strategy
- Shared styles loaded first to establish base styling
- Form-specific styles loaded after to provide overrides
- Fallback styling for missing CSS files
- Progressive enhancement approach

### Validation and Testing
- Visual regression testing for both forms
- Cross-browser compatibility testing
- Mobile device testing for responsive behavior
- Accessibility compliance verification

## Implementation Phases

### Phase 1: Extract Shared Styles
- Create mobile-entry-shared.css with common styles
- Update both forms to use shared classes
- Test basic layout and styling consistency

### Phase 2: Standardize Components
- Implement consistent button system
- Standardize form input styling
- Apply unified list/selection interface

### Phase 3: Responsive Consolidation
- Merge mobile responsive rules
- Ensure consistent touch targets
- Implement form field layout standardization

### Phase 4: Form-Specific Refinements
- Create form-specific CSS files
- Implement unique component styling
- Fine-tune visual differences

### Phase 5: Final Integration
- Verify all forms load correctly with new CSS structure
- Ensure JavaScript interactions work with updated class names
- Validate CSS consolidation meets duplication reduction goals