# Mobile Entry Components

This directory contains reusable Blade components for the mobile-entry interfaces used in lift-logs and food-logs.

## Architecture

The mobile-entry component system is designed to eliminate code duplication between lift-logs and food-logs mobile interfaces while maintaining all existing functionality.

### Component Structure

```
resources/views/components/mobile-entry/
├── layouts/
│   └── base.blade.php              # Base layout template
├── message-system.blade.php        # Error/success/validation messages
├── date-navigation.blade.php       # Date navigation (prev/today/next)
├── page-title.blade.php           # Conditional date title display
├── item-card.blade.php            # Unified card component
├── add-item-button.blade.php      # Add exercise/food button
├── number-input.blade.php         # Increment/decrement input
├── empty-state.blade.php          # No items message
├── COMPONENT_TEMPLATE.md          # Template for new components
└── README.md                      # This file
```

### PHP Classes

```
app/View/Components/MobileEntry/
├── BaseComponent.php              # Base class for all components
├── ComponentInterface.php         # Interface contract
├── MessageSystem.php             # Message system component class
├── DateNavigation.php            # Date navigation component class
├── PageTitle.php                 # Page title component class
├── ItemCard.php                  # Item card component class
├── AddItemButton.php             # Add item button component class
├── NumberInput.php               # Number input component class
└── EmptyState.php                # Empty state component class
```

## Usage

All components use the `x-mobile-entry.*` namespace:

```php
<x-mobile-entry.message-system :errors="$errors" />
<x-mobile-entry.date-navigation :selected-date="$selectedDate" route-name="lift-logs.mobile-entry" />
<x-mobile-entry.page-title :selected-date="$selectedDate" />
```

## Component Guidelines

### 1. Consistent Parameter Handling

All components extend `BaseComponent` and implement `ComponentInterface` for consistent parameter validation and handling.

### 2. XSS Protection

All user-provided content is automatically sanitized through the base component methods.

### 3. Accessibility

Components include proper ARIA labels, keyboard navigation, and screen reader support.

### 4. Mobile-First Design

All components are optimized for mobile devices with touch-friendly interfaces.

### 5. Performance

Components are designed to minimize DOM elements and optimize rendering performance.

## Creating New Components

1. Copy `COMPONENT_TEMPLATE.md` and rename it to your component name
2. Create the Blade template in this directory
3. Create the PHP class in `app/View/Components/MobileEntry/` if needed
4. Extend `BaseComponent` and implement `ComponentInterface`
5. Add comprehensive documentation following the template
6. Write unit and integration tests

## Testing

Components should be tested at multiple levels:

- **Unit Tests:** Test component logic in isolation
- **Integration Tests:** Test components within template context
- **Visual Regression Tests:** Ensure consistent appearance
- **Accessibility Tests:** Validate WCAG compliance

## Performance Considerations

- Components are cached by Laravel's view cache
- Minimize database queries within components
- Use lazy loading for expensive operations
- Optimize CSS and JavaScript dependencies

## Browser Support

- Modern browsers (Chrome 90+, Firefox 88+, Safari 14+)
- Mobile browsers (iOS Safari 14+, Chrome Mobile 90+)
- Progressive enhancement for older browsers

## Migration from Legacy Templates

When migrating existing markup to components:

1. Identify duplicate patterns between templates
2. Extract common functionality into components
3. Test thoroughly to ensure identical behavior
4. Update documentation and remove deprecated code

## Related Files

- **CSS:** `public/css/entry-interface.css` - Consolidated mobile-first styles
- **JavaScript:** `public/js/mobile-entry-shared.js` - Shared utilities
- **Tests:** `tests/Unit/View/Components/MobileEntry/` - Component tests

## Support

For questions about mobile-entry components, refer to:

- Component documentation in each component file
- `COMPONENT_TEMPLATE.md` for creating new components
- Base classes for common functionality patterns