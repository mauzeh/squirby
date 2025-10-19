# Implementation Plan

- [ ] 1. Set up mobile food entry route and basic controller method
  - Add route for `food-logs/mobile-entry` in web.php
  - Create `mobileEntry()` method in FoodLogController
  - Implement date parameter handling and basic data fetching
  - _Requirements: 1.1, 2.5_

- [ ] 2. Create mobile food entry view with date navigation
  - Create `resources/views/food_logs/mobile-entry.blade.php` view file
  - Implement date navigation controls (Previous, Today, Next buttons)
  - Add contextual date display (Today, Yesterday, Tomorrow, or specific date)
  - Copy and adapt mobile styling from lift logs mobile entry
  - _Requirements: 1.2, 1.3, 1.4, 2.1, 2.2, 2.3, 2.4_

- [ ] 3. Implement food selection interface
  - Create expandable "Add Food" button and food list container
  - Display user ingredients and meals in unified list
  - Add visual distinction between ingredients and meals
  - Implement JavaScript for showing/hiding food list
  - _Requirements: 4.1, 4.2, 4.3, 4.5, 8.3_

- [ ] 4. Build dynamic form fields for ingredient and meal logging
  - Create ingredient form fields (quantity, notes) with hidden state
  - Create meal form fields (portion, notes) with hidden state
  - Implement JavaScript to show appropriate fields based on selection
  - Add form submission handling with proper hidden inputs
  - _Requirements: 5.1, 5.2, 5.4_

- [ ] 5. Implement unit-specific increment/decrement controls
  - Add increment/decrement buttons with lift logs mobile styling
  - Create JavaScript function for unit-specific increment amounts
  - Implement button event handlers for quantity/portion adjustment
  - Prevent negative quantities and display current values prominently
  - _Requirements: 5.3, 6.1, 6.2, 6.3, 6.4, 6.5_

- [ ] 6. Add form submission and food log creation
  - Update existing store method to handle mobile-entry redirect parameter
  - Implement ingredient logging (single FoodLog entry)
  - Implement meal logging (multiple FoodLog entries with meal info in notes)
  - Add real-time logging with automatic timestamp and 15-minute rounding
  - _Requirements: 3.1, 3.2, 3.3, 5.5_

- [ ] 7. Display existing food logs for the selected date
  - Show logged food entries below the form in chronological order
  - Display entry details (ingredient name, quantity, unit, time)
  - Add calculated calories and macros for each entry
  - Implement delete functionality for logged entries
  - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5_

- [ ] 8. Add daily nutrition totals display
  - Calculate and display daily totals using NutritionService
  - Show calories, protein, carbs, fats prominently
  - Update totals after form submission via redirect
  - Style totals for mobile readability
  - _Requirements: 3.4, 3.5_

- [ ] 9. Add comprehensive error handling and validation
  - Implement client-side validation for positive quantities
  - Add form validation error display
  - Handle edge cases for deleted ingredients/meals
  - Add loading states during form submission
  - _Requirements: Form validation, Network error handling, Data consistency_

- [ ] 10. Write tests for mobile food entry functionality
  - Create feature tests for mobile entry controller method
  - Test date navigation and parameter handling
  - Test ingredient and meal logging workflows
  - Test increment amount calculation for different unit types
  - _Requirements: Unit tests, Integration tests, Mobile-specific tests_