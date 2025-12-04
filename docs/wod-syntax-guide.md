# WOD Syntax Guide

## Overview

The WOD (Workout of the Day) feature allows you to create structured workouts using a simple text-based syntax. This makes it easy to quickly write out programming that can be displayed and logged by athletes.

## Basic Syntax

### Blocks

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

### Exercises

Exercises are written as: `[[Exercise Name]]: scheme` or `[Exercise Name]: scheme`

**Important:** 
- **Double brackets `[[...]]`** = Loggable exercises (user needs to log these)
- **Single brackets `[...]`** = Non-loggable exercises (informational only, like warm-ups or stretches)

Both types will be matched to your exercise library using fuzzy matching, but only double-bracketed exercises will appear in the workout display for logging.

**Format Options:**

1. **Sets x Reps**: `3x8` or `3 x 8`
   ```
   [[Bench Press]]: 3x8        // Loggable
   [Warm-up Push-ups]: 2x10    // Not loggable
   ```

2. **Rep Ladder**: `5-5-5-3-3-1`
   ```
   [[Back Squat]]: 5-5-5-5-5   // Loggable
   [[Deadlift]]: 5-3-1-1-1     // Loggable
   ```

3. **Rep Range**: `3x8-12`
   ```
   [[Dumbbell Row]]: 3x8-12    // Loggable
   [Face Pulls]: 3x15-20       // Not loggable
   ```

4. **Single Set**: Just a number
   ```
   [[Max Deadlift]]: 1         // Loggable
   ```

5. **Time/Distance**: `500m`, `5min`, `2km`, `30sec`
   ```
   [Row]: 500m                 // Not loggable (warm-up)
   [[Run]]: 5min               // Loggable
   [Bike]: 2km                 // Not loggable
   [[Plank]]: 30sec            // Loggable
   ```

6. **Time Format**: `2:00` (minutes:seconds)
   ```
   [[L-Sit Hold]]: 0:30        // Loggable
   ```

**Note:** The colon (`:`) is optional. You can write `[Exercise] 3x8` or `[Exercise]: 3x8` - both work!

## Special Formats

### AMRAP (As Many Rounds As Possible)

```
AMRAP 12min:
10 [[Box Jumps]]      // Loggable
15 [[Push-ups]]       // Loggable
20 [Air Squats]       // Not loggable
```

### EMOM (Every Minute On the Minute)

```
EMOM 16min:
5 [[Pull-ups]]        // Loggable
10 [[Push-ups]]       // Loggable
```

### For Time

```
For Time:
100 [[Wall Balls]]           // Loggable
75 [[Kettlebell Swings]]     // Loggable
50 [Box Jumps]               // Not loggable
```

Or with rep scheme:

```
21-15-9 For Time:
[[Thrusters]]         // Loggable
[[Pull-ups]]          // Loggable
```

### Rounds

```
5 Rounds:
10 [[Push-ups]]       // Loggable
20 [[Squats]]         // Loggable
30 [Sit-ups]          // Not loggable
```

## Complete Examples

### CrossFit Style

```
# Strength
[[Back Squat]]: 5-5-3-3-1-1

# Metcon
21-15-9 For Time:
[[Thrusters]]
[[Pull-ups]]
```

### Bodybuilding Style

```
# Chest & Triceps
[[Bench Press]]: 4x8
[[Incline Dumbbell Press]]: 3x10-12
[Cable Flyes]: 3x15              // Accessory, not tracked

# Triceps
[[Skull Crushers]]: 3x12
[Rope Pushdowns]: 3x15           // Accessory, not tracked
```

### Functional Fitness

```
# Warm-up
[Row]: 500m                      // Warm-up, not tracked
[Dynamic Stretching]: 5min       // Warm-up, not tracked

# WOD
AMRAP 20min:
5 [[Pull-ups]]
10 [[Push-ups]]
15 [[Air Squats]]

# Cool Down
[Stretch]: 10min                 // Cool down, not tracked
```

### Strength & Conditioning

```
# Block 1: Strength
[[Deadlift]]: 5-5-5-3-3-1
[[Romanian Deadlift]]: 3x8

# Block 2: Accessory
[[Dumbbell Row]]: 3x12
[Face Pulls]: 3x15-20            // Optional accessory

# Block 3: Conditioning
EMOM 12min:
10 [[Kettlebell Swings]]
5 [[Burpees]]
```

## Comments

Add comments using `//` or `--`:

```
# Strength
// Focus on form today
[Back Squat]: 5x5
-- Keep rest periods to 2 minutes
```

Comments are ignored when parsing.

## Tips

1. **Keep it simple**: The syntax is designed to be quick to type
2. **Use double brackets for main work**: Use `[[Exercise]]` for exercises you want users to log (main lifts, metcon movements)
3. **Use single brackets for auxiliary work**: Use `[Exercise]` for warm-ups, cool-downs, stretches, or optional accessories
4. **Be specific**: Include weight recommendations in parentheses if needed
5. **Use blocks**: Organize your workout into logical sections
6. **No indentation needed**: Exercises following special formats (AMRAP, EMOM, etc.) are automatically grouped
7. **Blank lines**: Use blank lines between blocks for readability - they're ignored

## Usage

When creating a WOD:
- WODs appear alongside workout templates in your workouts list
- Athletes can log exercises directly from the WOD view
- WODs are just another way to structure your workouts using text syntax
