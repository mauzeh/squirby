# WOD (Workout of the Day) System - Complete Guide

## Overview

The WOD (Workout of the Day) feature allows you to create structured workouts using a simple text-based syntax. This makes it easy to quickly write out programming that can be displayed and logged by athletes.

## What Was Built

A complete WOD feature that allows users to create structured, date-specific workouts using a simple text-based syntax.

### Key Components

1. **Database Schema**
   - Added `wod_syntax` (text) - stores the raw WOD text
   - Added `wod_parsed` (json) - stores the parsed structure
   - Migration: `2025_12_03_152632_add_wod_fields_to_workouts_table.php`
   - Migration: `2025_12_03_154256_remove_scheduled_date_from_workouts_table.php` (removed scheduling)

2. **WOD Parser Service**
   - **File**: `app/Services/WodParser.php`
   - **Purpose**: Parses WOD text syntax into structured JSON
   - **Features**:
     - Block parsing (# headers)
     - Exercise schemes (3x8, 5-5-5, 3x8-12, etc.)
     - Freeform text after exercise names (no colon required)
     - Special formats (AMRAP, EMOM, For Time, Rounds)
     - Time/distance formats (500m, 5min, 2:00)
     - Comment support (// and --)
     - Exercise parsing with single bracket notation
     - Unparse capability (convert back to text)

3. **Model Updates**
   - **File**: `app/Models/Workout.php`
   - **New Methods**:
     - `isWod()` - check if workout is a WOD
     - `isTemplate()` - check if workout is a template
     - `scopeWods()` - query only WODs
     - `scopeTemplates()` - query only templates

4. **Controller Updates**
   - **File**: `app/Http/Controllers/WorkoutController.php`
   - **Changes**:
     - `index()` - shows all user workouts (WODs and templates)
     - `create()` - supports both WOD and template creation
     - `store()` - parses and saves WODs
     - `edit()` - different UI for WODs vs templates
     - `editWod()` - dedicated WOD edit view
     - `update()` - handles WOD updates with re-parsing
     - Helper methods for displaying WOD exercises

5. **UI Components**
   - **Date Field**: Added `dateField()` method to FormComponentBuilder
   - **Form Rendering**: Updated form-field.blade.php to handle date inputs
   - **WOD Display**: Shows blocks and exercises with emoji indicators
     - 📦 for blocks
     - ⏱️ for timed formats (AMRAP, EMOM, For Time)
     - 🔄 for rounds

6. **Tests**
   - **File**: `tests/Unit/WodParserTest.php`
   - **Coverage**: 15 tests covering all syntax features
   - All tests passing ✓

## WOD Syntax Guide

### Basic Syntax

#### Blocks

Workouts are organized into blocks. Each block starts with a header:

```
# Block Name
```

You can use 1-3 hash marks (`#`, `##`, `###`) - they all work the same way.

**Examples:**
```
# Strength
# Block 1: Warm-up
## Conditioning
### Accessory Work
```

#### Exercises

Exercises are written with brackets around the name, optionally followed by a scheme or description.

**Important:** 
- **Brackets `[...]`** = Exercises that can be logged by users

All bracketed exercises will be matched to your exercise library using fuzzy matching and can be logged during workouts.

**Format Options:**

1. **Sets x Reps**: `3x8` or `3 x 8`
   ```
   [Bench Press] 3x8
   [Bench Press]: 3x8        // Also works with colon
   [Warm-up Push-ups] 2x10
   ```

2. **Rep Ladder**: `5-5-5-3-3-1`
   ```
   [Back Squat] 5-5-5-5-5
   [Deadlift]: 5-3-1-1-1     // Colon optional
   ```

3. **Rep Range**: `3x8-12`
   ```
   [Dumbbell Row] 3x8-12
   [Face Pulls]: 3x15-20
   ```

4. **Freeform Text**: Any text after the exercise name
   ```
   [Back Squat] 5 reps, building
   [Deadlift] work up to heavy single
   [Stretching] 5 minutes
   [Mobility Work] as needed
   ```

5. **Single Set**: Just a number
   ```
   [Max Deadlift] 1
   ```

6. **Time/Distance**: `500m`, `5min`, `2km`, `30sec`
   ```
   [Row] 500m
   [Run]: 5min
   [Bike] 2km
   [Plank] 30sec
   ```

7. **Time Format**: `2:00` (minutes:seconds)
   ```
   [L-Sit Hold] 0:30
   ```

**Note:** The colon (`:`) is completely optional. You can write:
- `[Exercise] 3x8` ✓
- `[Exercise]: 3x8` ✓
- `[Exercise] any text here` ✓
- `[Exercise]: any text here` ✓

All formats work the same way!

### Special Formats

#### AMRAP (As Many Rounds As Possible)

```
AMRAP 12min:
10 [Box Jumps]
15 [Push-ups]
20 [Air Squats]
```

#### EMOM (Every Minute On the Minute)

```
EMOM 16min:
5 [Pull-ups]
10 [Push-ups]
```

#### For Time

```
For Time:
100 [Wall Balls]
75 [Kettlebell Swings]
50 [Box Jumps]
```

Or with rep scheme:

```
21-15-9 For Time:
[Thrusters]
[Pull-ups]
```

#### Rounds

```
5 Rounds:
10 [Push-ups]
20 [Squats]
30 [Sit-ups]
```

### Comments

Add comments using `//` or `--`:

```
# Strength
// Focus on form today
[Back Squat] 5x5
-- Keep rest periods to 2 minutes
```

Comments are ignored when parsing.

## Complete Examples

### CrossFit Style

```
# Strength
[Back Squat] 5-5-3-3-1-1

# Metcon
21-15-9 For Time:
[Thrusters]
[Pull-ups]
```

### Bodybuilding Style

```
# Chest & Triceps
[Bench Press] 4x8
[Incline Dumbbell Press] 3x10-12
[Cable Flyes] 3x15

# Triceps
[Skull Crushers] 3x12
[Rope Pushdowns] 3x15
```

### Functional Fitness

```
# Warm-up
Row 500m easy pace
Dynamic stretching 5 minutes

# WOD
AMRAP 20min:
5 [Pull-ups]
10 [Push-ups]
15 [Air Squats]

# Cool Down
Stretch 10min
```

### Strength & Conditioning

```
# Block 1: Strength
[Deadlift] 5-5-5-3-3-1
[Romanian Deadlift] 3x8

# Block 2: Accessory
[Dumbbell Row] 3x12
[Face Pulls] 3x15-20

# Block 3: Conditioning
EMOM 12min:
10 [Kettlebell Swings]
5 [Burpees]
```

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

## Autocomplete Feature

### Overview

Minimal autocomplete implementation for exercise names in the WOD syntax editor using inline JSON for better performance.

### How It Works

1. **Data Source**
   - Exercise names are embedded directly in the page as `window.exerciseNames`
   - No Ajax call needed - data available immediately on page load
   - Generated by `WorkoutController` and passed to the view

2. **JavaScript Implementation**
   - Uses inline exercise names from `window.exerciseNames`
   - Triggers when typing inside brackets `[` or `[[`
   - Shows dropdown with matching exercises (max 10)
   - Filters by substring match (case-insensitive)

3. **User Experience**
   - Type `[[Back` → shows "Back Squat", "Back Extension", etc.
   - Arrow keys to navigate
   - Enter to select
   - Escape to close
   - Click to select
   - Auto-hides when typing outside brackets

4. **Styling**
   - Dark theme matching editor
   - Positioned below cursor
   - Scrollable list
   - Hover highlighting

### Files Modified

1. **app/Http/Controllers/WorkoutController.php** (updated)
   - Added `getExerciseNames()` helper method
   - Modified `create()` and `edit()` methods to include exercise data in view
   - Exercise names passed as `exerciseNames` in view data

2. **resources/views/mobile-entry/flexible.blade.php** (updated)
   - Added `window.exerciseNames` to JavaScript configuration
   - Exercise names available immediately without Ajax call

3. **public/js/mobile-entry/components/code-editor-autocomplete.js** (updated)
   - Now uses only `window.exerciseNames` for data
   - Removed API fallback code
   - Simplified and more reliable

4. **routes/web.php** (updated)
   - Removed API route for exercise autocomplete
   - Cleaner routing with no unused endpoints

### Performance Benefits

**Before**: Ajax call on every page load
- Extra HTTP request
- Delay before autocomplete works
- Network dependency

**After**: Inline JSON in page
- No extra HTTP request
- Autocomplete works immediately
- Better user experience
- Reduced server load
- Simplified codebase

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
- Uses single bracket notation (`[...]`) for all exercises
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

## Tips

1. **Keep it simple**: The syntax is designed to be quick to type
2. **Use brackets for exercises**: Use `[Exercise]` for exercises you want users to log
3. **Use plain text for notes**: Use plain text for warm-up instructions, notes, or non-trackable items
4. **Be specific**: Include weight recommendations in parentheses if needed
5. **Use blocks**: Organize your workout into logical sections
6. **No indentation needed**: Exercises following special formats (AMRAP, EMOM, etc.) are automatically grouped
7. **Blank lines**: Use blank lines between blocks for readability - they're ignored

## Usage

When creating a WOD:
- WODs appear alongside workout templates in your workouts list
- Athletes can log exercises directly from the WOD view
- WODs are just another way to structure your workouts using text syntax

## Files Created/Modified

### Created
- `database/migrations/2025_12_03_152632_add_wod_fields_to_workouts_table.php`
- `app/Services/WodParser.php`
- `tests/Unit/WodParserTest.php`

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

All 28 tests passing with 141 assertions.