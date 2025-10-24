# Design Document

## Overview

This design refactors the monolithic `lift-logs-table.blade.php` component into smaller, focused components that follow single responsibility principles. The refactoring will improve maintainability by separating concerns while preserving all existing functionality and user experience.

## Architecture

### Component Hierarchy

```
lift-logs-table (main container)
├── lift-logs-table-header (table header with column definitions)
├── lift-logs-table-body (table body container)
│   └── lift-log-row (individual table row, repeated for each log)
│       ├── lift-log-checkbox-cell (selection checkbox)
│       ├── lift-log-date-cell (date display with responsive behavior)
│       ├── lift-log-exercise-cell (exercise name and mobile summary)
│       ├── lift-log-weight-cell (weight and reps display)
│       ├── lift-log-1rm-cell (one rep max display)
│       ├── lift-log-comments-cell (comments with truncation)
│       └── lift-log-actions-cell (edit/delete buttons)
└── lift-logs-table-footer (bulk actions)
    └── bulk-selection-controls (JavaScript functionality component)
```

### Data Flow

1. **Controller Preparation**: `LiftLogController` prepares formatted data using a new `LiftLogTablePresenter`
2. **Main Component**: `lift-logs-table` receives formatted data and configuration
3. **Sub-components**: Each cell component receives only the data it needs
4. **JavaScript**: Isolated in `bulk-selection-controls` component

## Components and Interfaces

### Main Table Component

**File**: `resources/views/components/lift-logs-table.blade.php`

```php
@props(['liftLogs', 'hideExerciseColumn' => false, 'tableConfig'])

<table class="log-entries-table">
    <x-lift-logs-table-header :config="$tableConfig" />
    <x-lift-logs-table-body :liftLogs="$liftLogs" :config="$tableConfig" />
    <x-lift-logs-table-footer :config="$tableConfig" />
</table>
```

### Table Header Component

**File**: `resources/views/components/lift-logs-table-header.blade.php`

```php
@props(['config'])

<thead>
    <tr>
        <th><input type="checkbox" id="select-all-lift-logs"></th>
        <th class="{{ $config['dateColumnClass'] }}">Date</th>
        @unless($config['hideExerciseColumn'])
            <th>Exercise</th>
        @endunless
        <th class="hide-on-mobile">Weight (reps x rounds)</th>
        <th class="hide-on-mobile">1RM (est.)</th>
        <th class="hide-on-mobile comments-column">Comments</th>
        <th class="actions-column">Actions</th>
    </tr>
</thead>
```

### Table Body Component

**File**: `resources/views/components/lift-logs-table-body.blade.php`

```php
@props(['liftLogs', 'config'])

<tbody>
    @foreach ($liftLogs as $liftLog)
        <x-lift-log-row :liftLog="$liftLog" :config="$config" />
    @endforeach
</tbody>
```

### Individual Row Component

**File**: `resources/views/components/lift-log-row.blade.php`

```php
@props(['liftLog', 'config'])

<tr>
    <x-lift-log-checkbox-cell :liftLog="$liftLog" />
    <x-lift-log-date-cell :liftLog="$liftLog" :config="$config" />
    @unless($config['hideExerciseColumn'])
        <x-lift-log-exercise-cell :liftLog="$liftLog" />
    @endunless
    <x-lift-log-weight-cell :liftLog="$liftLog" />
    <x-lift-log-1rm-cell :liftLog="$liftLog" />
    <x-lift-log-comments-cell :liftLog="$liftLog" />
    <x-lift-log-actions-cell :liftLog="$liftLog" />
</tr>
```

### Cell Components

Each cell component handles a specific piece of data and its responsive behavior:

**Checkbox Cell**: `resources/views/components/lift-log-checkbox-cell.blade.php`
**Date Cell**: `resources/views/components/lift-log-date-cell.blade.php`
**Exercise Cell**: `resources/views/components/lift-log-exercise-cell.blade.php`
**Weight Cell**: `resources/views/components/lift-log-weight-cell.blade.php`
**1RM Cell**: `resources/views/components/lift-log-1rm-cell.blade.php`
**Comments Cell**: `resources/views/components/lift-log-comments-cell.blade.php`
**Actions Cell**: `resources/views/components/lift-log-actions-cell.blade.php`

### Bulk Selection Component

**File**: `resources/views/components/bulk-selection-controls.blade.php`

```php
@props(['config'])

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Existing JavaScript logic moved here
        // Select all functionality
        // Bulk delete form handling
    });
</script>
```

## Data Models

### LiftLogTablePresenter

**File**: `app/Presenters/LiftLogTablePresenter.php`

```php
class LiftLogTablePresenter
{
    public function formatForTable(Collection $liftLogs, bool $hideExerciseColumn = false): array
    {
        return [
            'liftLogs' => $liftLogs->map(function ($liftLog) {
                return $this->formatLiftLog($liftLog);
            }),
            'config' => $this->buildTableConfig($hideExerciseColumn)
        ];
    }

    private function formatLiftLog(LiftLog $liftLog): array
    {
        return [
            'id' => $liftLog->id,
            'formatted_date' => $liftLog->logged_at->format('m/d'),
            'exercise_title' => $liftLog->exercise->title,
            'exercise_url' => route('exercises.show-logs', $liftLog->exercise),
            'formatted_weight' => $this->formatWeight($liftLog),
            'formatted_reps_sets' => $this->formatRepsSets($liftLog),
            'formatted_1rm' => $this->format1RM($liftLog),
            'truncated_comments' => Str::limit($liftLog->comments, 50),
            'full_comments' => $liftLog->comments,
            'edit_url' => route('lift-logs.edit', $liftLog->id),
            'mobile_summary' => $this->buildMobileSummary($liftLog)
        ];
    }

    private function buildTableConfig(bool $hideExerciseColumn): array
    {
        return [
            'hideExerciseColumn' => $hideExerciseColumn,
            'dateColumnClass' => $hideExerciseColumn ? '' : 'hide-on-mobile',
            'colspan' => $hideExerciseColumn ? 6 : 7
        ];
    }
}
```

## Error Handling

### Component Error Boundaries

- Each component validates its required props
- Missing data gracefully degrades (empty cells vs errors)
- JavaScript errors are contained within the bulk-selection-controls component

### Validation Strategy

- Props validation at component level using Laravel's component validation
- Data formatting errors handled in the presenter layer
- JavaScript validation maintains existing user experience

## Testing Strategy

### Component Testing

- **Unit Tests**: Test each cell component in isolation
- **Integration Tests**: Test main table component with various data configurations
- **Presenter Tests**: Test data formatting logic separately from view logic

### Test Structure

```
tests/
├── Unit/
│   ├── Components/
│   │   ├── LiftLogRowTest.php
│   │   ├── LiftLogCellsTest.php
│   └── Presenters/
│       └── LiftLogTablePresenterTest.php
└── Feature/
    └── LiftLogsTableTest.php
```

### Testing Approach

- Mock data creation for consistent test scenarios
- Component rendering tests using Laravel's component testing features
- Presenter logic tests with various lift log configurations
- No JavaScript testing required per requirements

## Implementation Notes

### CSS Class Strategy

Replace inline styles and complex conditionals with CSS classes:

```css
.comments-column {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mobile-summary {
    font-size: 0.9em;
    color: #ccc;
}

.actions-flex {
    display: flex;
    gap: 5px;
}
```

### Backward Compatibility

- Maintain existing prop interface for `lift-logs-table` component
- Preserve all CSS classes and IDs for existing JavaScript/CSS dependencies
- Keep same HTML structure for styling compatibility

### Performance Considerations

- Presenter formatting happens once in controller, not per component render
- Component hierarchy minimizes prop drilling
- JavaScript remains inline to avoid additional HTTP requests