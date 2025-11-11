# Workout Templates Refactor - Priority-Based System

## Changes Made

Templates have been refactored to store only exercises and their priority order, removing sets and reps storage.

## Rationale

Templates should represent **what** exercises to do, not **how** to do them. Training parameters (weight, sets, reps) should come from the user's training history and progression system, allowing the same template to adapt as the user gets stronger.

## Database Changes

### Migration: `remove_sets_and_reps_from_workout_template_exercises`

Removed columns from `workout_template_exercises`:
- `sets` (removed)
- `reps` (removed)
- `notes` (removed)
- `rest_seconds` (removed)

Kept columns:
- `order` - Now represents priority (1 = highest priority)

## Code Changes

### Models
- **WorkoutTemplateExercise**: Removed sets/reps from fillable and casts
- **WorkoutTemplate**: Updated `duplicate()` method to only copy exercise and order

### Controller
- **WorkoutTemplateController**: 
  - Removed sets/reps fields from add exercise form
  - Updated display to show "Priority: X" instead of "X sets × Y reps"
  - Simplified validation to only require exercise name

### Seeder
- **WorkoutTemplateSeeder**: Simplified to store only exercise names in order

### Documentation
- Updated all docs to reflect priority-based system
- Clarified that training parameters come from history

## User Impact

### Before
- User adds exercise with specific sets/reps
- Template stores these values
- When applied, uses stored sets/reps

### After
- User adds exercise (no sets/reps)
- Template stores only exercise and priority
- When applied, system suggests sets/reps from training history
- Template adapts to user's current strength level

## Benefits

1. **Adaptive**: Same template works for beginners and advanced users
2. **Progressive**: Template "grows" with the user
3. **Simpler**: Less data entry when creating templates
4. **Flexible**: Training parameters can vary based on context (deload, PR attempt, etc.)

## Example

**Template: "Push Day"**
1. Bench Press (Priority 1)
2. Strict Press (Priority 2)
3. Dips (Priority 3)
4. Tricep Extensions (Priority 4)

When applied:
- System looks at recent training history for each exercise
- Suggests appropriate weight, sets, and reps
- User can modify before logging

---

**Date**: November 10, 2025
**Status**: Complete ✅
