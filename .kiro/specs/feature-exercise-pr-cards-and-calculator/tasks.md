# Task List

## Phase 1: Service Layer
- [ ] Create `app/Services/ExercisePRService.php`
  - [ ] Implement `supportsPRTracking()` method (barbell only)
  - [ ] Implement `getPRData()` method (find max weight for 1, 2, 3 reps)
  - [ ] Implement `getCalculatorGrid()` method (generate percentage grid)
  - [ ] Add `calculate1RM()` helper method (Brzycki formula)

## Phase 2: Unit Tests
- [ ] Create `tests/Unit/ExercisePRServiceTest.php`
  - [ ] Test `supportsPRTracking()` returns true for barbell
  - [ ] Test `supportsPRTracking()` returns false for dumbbell
  - [ ] Test `supportsPRTracking()` returns false for cardio
  - [ ] Test `getPRData()` returns null for unsupported exercise
  - [ ] Test `getPRData()` returns correct PRs for each rep range
  - [ ] Test `getPRData()` handles no lift logs
  - [ ] Test `getPRData()` handles partial data (missing rep ranges)
  - [ ] Test `getPRData()` selects highest weight when multiple logs exist
  - [ ] Test `getCalculatorGrid()` generates correct percentages
  - [ ] Test `getCalculatorGrid()` rounds weights correctly
  - [ ] Test `getCalculatorGrid()` handles three columns
  - [ ] Test `getCalculatorGrid()` returns null when no PR data
  - [ ] Test `calculate1RM()` uses Brzycki formula correctly
  - [ ] Test `calculate1RM()` handles edge cases (reps = 1, reps >= 37)

## Phase 3: ComponentBuilder Extensions
- [ ] Update `app/Services/ComponentBuilder.php`
  - [ ] Add `prCards()` static method
  - [ ] Add `card()` method for adding individual cards
  - [ ] Add `calculatorGrid()` static method
  - [ ] Add `columns()` method for setting grid columns
  - [ ] Add `percentages()` method for setting percentage rows

## Phase 4: Blade Templates
- [ ] Create `resources/views/mobile-entry/components/pr-cards.blade.php`
  - [ ] Implement card grid layout
  - [ ] Display label, value, and unit for each card
  - [ ] Handle null values (show "—")
- [ ] Create `resources/views/mobile-entry/components/calculator-grid.blade.php`
  - [ ] Implement table layout with headers
  - [ ] Display percentage labels in first column
  - [ ] Display calculated weights in data columns
  - [ ] Handle null values (show "—")

## Phase 5: CSS Styling
- [ ] Create `public/css/mobile-entry/components/pr-cards.css`
  - [ ] Style card container (3-column grid)
  - [ ] Style individual cards (background, border, padding)
  - [ ] Style labels, values, and units
  - [ ] Add mobile responsive styles
- [ ] Create `public/css/mobile-entry/components/calculator-grid.css`
  - [ ] Style table layout
  - [ ] Style headers and data cells
  - [ ] Add alternating row colors
  - [ ] Style percentage labels and weight values
  - [ ] Add mobile responsive styles (scrollable)

## Phase 6: Controller Integration
- [ ] Update `app/Http/Controllers/ExerciseController.php`
  - [ ] Inject ExercisePRService in `showLogs()` method
  - [ ] Check if exercise supports PR tracking
  - [ ] Get PR data from service
  - [ ] Get calculator grid from service
  - [ ] Build PR cards component
  - [ ] Build calculator grid component
  - [ ] Add components before chart in component array

## Phase 7: View Integration
- [ ] Update `resources/views/mobile-entry/flexible.blade.php`
  - [ ] Add CSS import for `pr-cards.css`
  - [ ] Add CSS import for `calculator-grid.css`

## Phase 8: Integration Tests
- [ ] Create `tests/Feature/ExercisePRCardsIntegrationTest.php`
  - [ ] Test exercise logs page shows PR cards for barbell exercise
  - [ ] Test exercise logs page shows calculator grid
  - [ ] Test exercise logs page does not show PR features for dumbbell
  - [ ] Test exercise logs page does not show PR features for cardio
  - [ ] Test PR cards display correct weights from database
  - [ ] Test calculator grid shows accurate percentages
  - [ ] Test PR cards handle missing rep ranges gracefully
  - [ ] Test page works normally when no lift logs exist

## Phase 9: Manual Testing & Polish
- [ ] Create test barbell exercise (e.g., Back Squat)
- [ ] Add lift logs with 1, 2, and 3 rep sets
- [ ] Visit exercise logs page and verify PR cards
- [ ] Verify calculator grid shows correct percentages
- [ ] Test with dumbbell exercise (should not show features)
- [ ] Test with cardio exercise (should not show features)
- [ ] Test with no lift logs (should not show features)
- [ ] Test mobile responsive design
- [ ] Test with partial data (only 1 rep range)
- [ ] Verify existing chart and table still work
- [ ] Performance check (page load time)
- [ ] Cross-browser testing

## Final Steps
- [ ] Run all tests (`php artisan test`)
- [ ] Check for any linting issues (`php artisan pint`)
- [ ] Code review
- [ ] Update documentation if needed
- [ ] Merge to main branch
