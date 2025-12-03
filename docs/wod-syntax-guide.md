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

Exercises are written as: `Exercise Name: scheme`

**Format Options:**

1. **Sets x Reps**: `3x8` or `3 x 8`
   ```
   Bench Press: 3x8
   ```

2. **Rep Ladder**: `5-5-5-3-3-1`
   ```
   Back Squat: 5-5-5-5-5
   Deadlift: 5-3-1-1-1
   ```

3. **Rep Range**: `3x8-12`
   ```
   Dumbbell Row: 3x8-12
   Face Pulls: 3x15-20
   ```

4. **Single Set**: Just a number
   ```
   Max Deadlift: 1
   ```

5. **Time/Distance**: `500m`, `5min`, `2km`, `30sec`
   ```
   Row: 500m
   Run: 5min
   Bike: 2km
   Plank: 30sec
   ```

6. **Time Format**: `2:00` (minutes:seconds)
   ```
   L-Sit Hold: 0:30
   ```

## Special Formats

### AMRAP (As Many Rounds As Possible)

```
AMRAP 12min:
  10 Box Jumps
  15 Push-ups
  20 Air Squats
```

### EMOM (Every Minute On the Minute)

```
EMOM 16min:
  5 Pull-ups
  10 Push-ups
```

### For Time

```
For Time:
  100 Wall Balls
  75 Kettlebell Swings
  50 Box Jumps
```

Or with rep scheme:

```
21-15-9 For Time:
  Thrusters (95/65)
  Pull-ups
```

### Rounds

```
5 Rounds:
  10 Push-ups
  20 Squats
  30 Sit-ups
```

## Complete Examples

### CrossFit Style

```
# Strength
Back Squat: 5-5-3-3-1-1

# Metcon
21-15-9 For Time:
  Thrusters (95/65)
  Pull-ups
```

### Bodybuilding Style

```
# Chest & Triceps
Bench Press: 4x8
Incline Dumbbell Press: 3x10-12
Cable Flyes: 3x15

# Triceps
Skull Crushers: 3x12
Rope Pushdowns: 3x15
```

### Functional Fitness

```
# Warm-up
Row: 500m
Dynamic Stretching: 5min

# WOD
AMRAP 20min:
  5 Pull-ups
  10 Push-ups
  15 Air Squats

# Cool Down
Stretch: 10min
```

### Strength & Conditioning

```
# Block 1: Strength
Deadlift: 5-5-5-3-3-1
Romanian Deadlift: 3x8

# Block 2: Accessory
Dumbbell Row: 3x12
Face Pulls: 3x15-20

# Block 3: Conditioning
EMOM 12min:
  10 Kettlebell Swings
  5 Burpees
```

## Comments

Add comments using `//` or `--`:

```
# Strength
// Focus on form today
Back Squat: 5x5
-- Keep rest periods to 2 minutes
```

Comments are ignored when parsing.

## Tips

1. **Keep it simple**: The syntax is designed to be quick to type
2. **Be specific**: Include weight recommendations in parentheses if needed
3. **Use blocks**: Organize your workout into logical sections
4. **Indentation matters**: For special formats (AMRAP, EMOM, etc.), indent the exercises with 2 spaces
5. **Blank lines**: Use blank lines between blocks for readability - they're ignored

## Usage

When creating a WOD:
- WODs appear alongside workout templates in your workouts list
- Athletes can log exercises directly from the WOD view
- WODs are just another way to structure your workouts using text syntax
