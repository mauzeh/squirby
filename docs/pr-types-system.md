# PR Types System

## Overview

The PR (Personal Record) detection system now supports multiple types of PRs using a modern PHP 8.2+ enum-based bitwise flag system. This allows a single lift to be recognized for multiple PR achievements simultaneously.

## PR Types

### Currently Implemented

1. **ONE_RM (1)** - Estimated one-rep max PR
   - Label: "🎉 NEW PR!"
   - Triggered when the estimated 1RM exceeds previous best

2. **REP_SPECIFIC (2)** - Rep-specific PR (1-10 reps)
   - Label: "🏆 Rep PR!"
   - Triggered when weight for a specific rep count exceeds previous best
   - Only applies to 1-10 rep ranges

3. **VOLUME (4)** - Total volume PR
   - Label: "💪 Volume PR!"
   - Triggered when total session volume (weight × reps × sets) exceeds previous best
   - Calculated as sum of all sets in a single lift log

### Future PR Types (Placeholders)

4. **DENSITY (8)** - Work density PR
5. **TIME (16)** - Time-based PR
6. **ENDURANCE (32)** - Endurance PR

## Technical Implementation

### Enum Structure

```php
enum PRType: int
{
    case NONE = 0;
    case ONE_RM = 1;
    case REP_SPECIFIC = 2;
    case VOLUME = 4;
    // ... more types
}
```

### Bitwise Operations

Multiple PR types can be combined using bitwise OR:
```php
$prFlags = PRType::ONE_RM->value | PRType::VOLUME->value; // = 5
```

Check for specific PR type:
```php
if (PRType::VOLUME->isIn($prFlags)) {
    // Has volume PR
}
```

### Backward Compatibility

The system maintains backward compatibility with existing code:

- Returns `int` instead of `bool` from `PRDetectionService::isLiftLogPR()`
- `0` = no PR (falsy in boolean context)
- `>0` = has PR (truthy in boolean context)
- Existing `if ($isPR)` checks continue to work
- Session storage works with integer flags

### Display Priority

When multiple PR types are achieved, the label shown follows this priority:
1. ONE_RM
2. REP_SPECIFIC
3. VOLUME
4. DENSITY
5. TIME
6. ENDURANCE

## Usage Examples

### Checking for Any PR

```php
$prFlags = $prDetectionService->isLiftLogPR($liftLog, $exercise, $user);

if ($prFlags) {
    // This is a PR!
    $label = PRType::getBestLabel($prFlags);
}
```

### Checking for Specific PR Type

```php
if (PRType::VOLUME->isIn($prFlags)) {
    // This is a volume PR
}
```

### Getting All PR Types

```php
$types = PRType::getTypes($prFlags);
// Returns array of PRType enums
```

### Combining PR Types

```php
$flags = PRType::combine(PRType::ONE_RM, PRType::VOLUME);
// Returns combined bitwise flags
```

## Volume PR Calculation

Volume is calculated as:
```
Total Volume = Σ (weight × reps) for all sets in a lift log
```

Example:
- Set 1: 100 lbs × 5 reps = 500 lbs
- Set 2: 100 lbs × 5 reps = 500 lbs  
- Set 3: 100 lbs × 5 reps = 500 lbs
- **Total Volume: 1500 lbs**

A volume PR is achieved when the current session's total volume exceeds all previous sessions for that exercise.

## Badge Display

PR badges are displayed in lift log tables when a log contains any PR type. The badge shows "🏆 PR" regardless of which specific PR type(s) were achieved.

## Testing

Comprehensive test coverage includes:
- Individual PR type detection
- Multiple PR types in single lift
- Volume PR specific scenarios
- Backward compatibility with existing tests
- All 1677 tests passing

See `tests/Feature/VolumePRDetectionTest.php` for volume PR examples.
