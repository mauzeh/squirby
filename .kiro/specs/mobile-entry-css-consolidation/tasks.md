# Implementation Plan

- [ ] 1. Create shared CSS foundation
  - Create `resources/css/mobile-entry-shared.css` with base container, navigation, and layout styles
  - Extract common responsive breakpoints and mobile-first styling patterns
  - Implement standardized date navigation styling from lift-logs design
  - _Requirements: 1.1, 1.2, 1.3, 2.1, 2.2, 7.1, 7.4_

- [ ] 2. Consolidate button system
  - Standardize button classes by consolidating `.large-button` and `.button-large` into single `.button-large` class
  - Implement consistent color variant classes (`.button-green`, `.button-blue`, `.button-gray`, `.button-danger`)
  - Replace food-logs delete button styling with lift-logs standardized `.delete-button` implementation
  - Add hover states and consistent sizing across both forms
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6_

- [ ] 3. Unify form input styling
  - Consolidate `.large-input` and `.large-textarea` styling with consistent validation states
  - Standardize `.input-group` with increment/decrement button styling and behavior
  - Implement consistent form group layout with labels and inputs on separate lines for mobile
  - Add shared input validation error styling patterns
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 7.4_

- [ ] 4. Create generic list interface system
  - Implement generic `.item-list-container`, `.item-list`, and `.item-list-item` classes
  - Create `.item-name` and `.item-label` classes for consistent list item structure
  - Add consistent hover states and visual feedback patterns
  - Implement modifier classes for different item types (food vs exercise)
  - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_

- [ ] 5. Implement comprehensive message system
  - Extend food-logs message system to create shared `.message-container` base class
  - Create `.message-error`, `.message-success`, and `.message-validation` variant classes
  - Implement consistent close button styling and animation keyframes
  - Add message system to lift-logs form with identical styling and behavior
  - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

- [ ] 6. Consolidate responsive behavior
  - Merge duplicate mobile-specific styling rules into shared CSS
  - Implement consistent minimum touch target sizes (44px) across both forms
  - Ensure form fields display labels and inputs on separate lines for mobile screens
  - Standardize responsive breakpoints and mobile layout patterns
  - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6_

- [ ] 7. Create form-specific CSS extensions
  - Create `resources/css/mobile-entry-food.css` with food-specific styling (nutrition totals, ingredient/meal variants)
  - Create `resources/css/mobile-entry-lift.css` with lift-specific styling (program cards, band selectors, completion badges)
  - Implement form-specific list item variants while maintaining shared base styling
  - Ensure unique components maintain their distinct visual identity
  - _Requirements: 2.5, 8.3_

- [ ] 8. Update food-logs mobile entry template
  - Remove embedded CSS from `resources/views/food_logs/mobile-entry.blade.php`
  - Update HTML classes to use consolidated class names
  - Replace date navigation with lift-logs standardized implementation
  - Update delete buttons to use standardized `.delete-button` styling
  - Link shared and food-specific CSS files
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 3.4, 8.1_

- [ ] 9. Update lift-logs mobile entry template
  - Remove embedded CSS from `resources/views/lift-logs/mobile-entry.blade.php`
  - Update HTML classes to use consolidated class names
  - Add message system HTML structure for error/success/validation messages
  - Link shared and lift-specific CSS files
  - _Requirements: 2.1, 2.2, 6.4, 8.2_

