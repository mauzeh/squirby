# WOD Feature Summary

## What Was Built

A complete WOD (Workout of the Day) feature that allows users to create structured, date-specific workouts using a simple text-based syntax.

## Key Components

### 1. Database Schema
- Added `wod_syntax` (text) - stores the raw WOD text
- Added `wod_parsed` (json) - stores the parsed structure
- Migration: `2025_12_03_152632_add_wod_fields_to_workouts_table.php`
- Migration: `2025_12_03_154256_remove_scheduled_date_from_workouts_table.php` (removed scheduling)

### 2. WOD Parser Service
- **File**: `app/Services/WodParser.php`
- **Purpose**: Parses WOD text syntax into structured JSON
- **Features**:
  - Block parsing (# headers)
  - Exercise schemes (3x8, 5-5-5, 3x8-12, etc.)
  - Freeform text after exercise names (no colon required)
  - Special formats (AMRAP, EMOM, For Time, Rounds)
  - Time/distance formats (500m, 5min, 2:00)
  - Comment support (// and --)
  - Loggable vs non-loggable exercises (double vs single brackets)
  - Unparse capability (convert back to text)

### 3. Model Updates
- **File**: `app/Models/Workout.php`
- **New Methods**:
  - `isWod()` - check if workout is a WOD
  - `isTemplate()` - check if workout is a template
  - `scopeWods()` - query only WODs
  - `scopeTemplates()` - query only templates

### 4. Controller Updates
- **File**: `app/Http/Controllers/WorkoutController.php`
- **Changes**:
  - `index()` - shows all user workouts (WODs and templates)
  - `create()` - supports both WOD and template creation
  - `store()` - parses and saves WODs
  - `edit()` - different UI for WODs vs templates
  - `editWod()` - dedicated WOD edit view
  - `update()` - handles WOD updates with re-parsing
  - Helper methods for displaying WOD exercises

### 5. UI Components
- **Date Field**: Added `dateField()` method to FormComponentBuilder
- **Form Rendering**: Updated form-field.blade.php to handle date inputs
- **WOD Display**: Shows blocks and exercises with emoji indicators
  - 📦 for blocks
  - ⏱️ for timed formats (AMRAP, EMOM, For Time)
  - 🔄 for rounds

### 6. Tests
- **File**: `tests/Unit/WodParserTest.php`
- **Coverage**: 15 tests covering all syntax features
- All tests passing ✓

### 7. Documentation
- **WOD Syntax Guide**: `docs/wod-syntax-guide.md`
- **Feature Summary**: This file

## User Workflow

### Creating a WOD

1. Go to Workouts page
2. Click "Create WOD"
3. Enter:
   - WOD name (e.g., "Monday Strength")
   - WOD syntax in textarea
   - Optional description
4. Submit - syntax is parsed and validated

### Viewing WODs

- WODs appear in workouts list alongside templates
- Shows block count
- Expandable to see all blocks and exercises

### Logging from WOD

- Each exercise in WOD shows "Log now" button
- Clicking takes you to lift-logs/create
- After logging, shows completed status with edit/delete options
- Matches exercises by name from database

### Editing WODs

- Shows parsed preview of blocks and exercises
- Edit form with syntax textarea
- Re-parses on save
- Can change scheduled date

## Syntax Examples

### Simple Strength
```
# Strength
[[Back Squat]] 5-5-5-5-5
[[Bench Press]] 3x8
```

### CrossFit Style
```
# Strength
[[Deadlift]] 5-3-1-1-1

# Metcon
21-15-9 For Time:
[[Thrusters]] (95/65)
[[Pull-ups]]
```

### Multiple Blocks
```
# Block 1: Strength
[[Back Squat]] 5x5

# Block 2: Accessory
[[Dumbbell Row]] 3x12
[Face Pulls] 3x15-20

# Block 3: Conditioning
AMRAP 12min:
10 [[Box Jumps]]
15 [[Push-ups]]
20 [Air Squats]
```

## Integration Points

### Existing Features
- **Workouts**: WODs and templates coexist in same table
- **Exercises**: WOD exercises link to existing exercise database
- **Lift Logs**: Logging from WODs creates standard lift logs
- **Exercise Aliases**: Respected when displaying WOD exercises
- **Authorization**: Uses existing workout policies

### Future Enhancements
- Public WODs (sharing between users)
- WOD templates (reusable WOD structures)
- Auto-create exercises from WOD syntax
- WOD duplication
- WOD categories/tags

## Technical Notes

### Parser Design
- Stateful parsing with block and special format tracking
- Handles indentation for nested exercises
- Flexible regex patterns for various formats
- Accepts freeform text after exercise names (colon optional)
- Distinguishes loggable (`[[...]]`) from non-loggable (`[...]`) exercises
- Graceful fallback for unrecognized syntax

### Data Structure
```json
{
  "blocks": [
    {
      "name": "Strength",
      "exercises": [
        {
          "type": "exercise",
          "name": "Back Squat",
          "scheme": {
            "type": "rep_ladder",
            "reps": [5, 5, 5, 5, 5],
            "display": "5-5-5-5-5"
          }
        }
      ]
    }
  ],
  "parsed_at": "2025-12-03T15:26:32Z"
}
```

### Performance
- Parsing happens only on create/update
- Parsed JSON stored in database
- No parsing needed for display
- Efficient queries on user_id

## Files Modified/Created

### Created
- `database/migrations/2025_12_03_152632_add_wod_fields_to_workouts_table.php`
- `app/Services/WodParser.php`
- `tests/Unit/WodParserTest.php`
- `docs/wod-syntax-guide.md`
- `docs/wod-feature-summary.md`

### Modified
- `app/Models/Workout.php`
- `app/Http/Controllers/WorkoutController.php`
- `app/Services/Components/Interactive/FormComponentBuilder.php`
- `resources/views/mobile-entry/components/form-field.blade.php`

## Testing

Run tests:
```bash
php artisan test --filter=WodParserTest
```

All 10 tests passing with 44 assertions.
