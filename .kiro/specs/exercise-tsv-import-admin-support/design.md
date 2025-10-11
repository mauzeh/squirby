# Design Document

## Overview

This design updates the existing Exercise TSV import functionality to integrate with the admin-managed exercises system. The solution extends the current `TsvImporterService` and related components to support both global and user-specific exercise imports while maintaining backward compatibility.

## Architecture

### Service Layer Updates

The `TsvImporterService::importExercises()` method will be enhanced to support import modes and proper conflict resolution with the new exercise scoping system.

### Controller Updates

The exercise import controller will be updated to:
- Provide admin users with global import options
- Handle the new import mode parameter
- Use the enhanced service methods

### UI Updates

The exercise import interface will be updated to show appropriate options based on user role.

## Components and Interfaces

### Enhanced TsvImporterService

```php
class TsvImporterService
{
    public function importExercises(string $tsvData, int $userId, bool $importAsGlobal = false): array
    {
        // Validate admin permission for global imports
        if ($importAsGlobal) {
            $user = User::find($userId);
            if (!$user || !$user->hasRole('Admin')) {
                throw new \Exception('Only administrators can import global exercises.');
            }
        }

        $rows = explode("\n", $tsvData);
        $importedCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $invalidRows = [];
        $importedExercises = [];
        $updatedExercises = [];
        $skippedExercises = [];

        foreach ($rows as $row) {
            if (empty(trim($row))) {
                continue;
            }

            $columns = array_map('trim', explode("\t", $row));

            if (count($columns) < 2 || empty($columns[0])) {
                $invalidRows[] = $row;
                continue;
            }

            $title = $columns[0];
            $description = $columns[1] ?? '';
            $isBodyweight = $this->parseBooleanValue($columns[2] ?? 'false');

            $result = $this->processExerciseImport($title, $description, $isBodyweight, $userId, $importAsGlobal);
            
            switch ($result['action']) {
                case 'imported':
                    $importedCount++;
                    $importedExercises[] = [
                        'title' => $result['exercise']->title,
                        'description' => $result['exercise']->description,
                        'is_bodyweight' => $result['exercise']->is_bodyweight,
                        'type' => $result['exercise']->isGlobal() ? 'global' : 'personal'
                    ];
                    break;
                case 'updated':
                    $updatedCount++;
                    $updatedExercises[] = [
                        'title' => $result['exercise']->title,
                        'description' => $result['exercise']->description,
                        'is_bodyweight' => $result['exercise']->is_bodyweight,
                        'type' => $result['exercise']->isGlobal() ? 'global' : 'personal',
                        'changes' => $result['changes'] ?? []
                    ];
                    break;
                case 'skipped':
                    $skippedCount++;
                    $skippedExercises[] = [
                        'title' => $title,
                        'reason' => $result['reason']
                    ];
                    break;
            }
        }

        return [
            'importedCount' => $importedCount,
            'updatedCount' => $updatedCount,
            'skippedCount' => $skippedCount,
            'invalidRows' => $invalidRows,
            'importedExercises' => $importedExercises,
            'updatedExercises' => $updatedExercises,
            'skippedExercises' => $skippedExercises,
            'importMode' => $importAsGlobal ? 'global' : 'personal'
        ];
    }

    private function processExerciseImport(string $title, string $description, bool $isBodyweight, int $userId, bool $importAsGlobal): array
    {
        if ($importAsGlobal) {
            return $this->processGlobalExerciseImport($title, $description, $isBodyweight);
        } else {
            return $this->processUserExerciseImport($title, $description, $isBodyweight, $userId);
        }
    }

    private function processGlobalExerciseImport(string $title, string $description, bool $isBodyweight): array
    {
        // Check for existing global exercise
        $existingGlobal = Exercise::global()
            ->whereRaw('LOWER(title) = ?', [strtolower($title)])
            ->first();

        if ($existingGlobal) {
            // Check if data differs
            $changes = [];
            if ($existingGlobal->description !== $description) {
                $changes['description'] = ['from' => $existingGlobal->description, 'to' => $description];
            }
            if ($existingGlobal->is_bodyweight !== $isBodyweight) {
                $changes['is_bodyweight'] = ['from' => $existingGlobal->is_bodyweight, 'to' => $isBodyweight];
            }
            
            if (!empty($changes)) {
                $existingGlobal->update([
                    'description' => $description,
                    'is_bodyweight' => $isBodyweight,
                ]);
                return ['action' => 'updated', 'exercise' => $existingGlobal, 'changes' => $changes];
            } else {
                return ['action' => 'skipped', 'reason' => "Global exercise '{$title}' already exists with same data"];
            }
        }

        // Check for any user exercise conflict
        $userConflict = Exercise::whereNotNull('user_id')
            ->whereRaw('LOWER(title) = ?', [strtolower($title)])
            ->first();

        if ($userConflict) {
            return ['action' => 'skipped', 'reason' => "Exercise '{$title}' conflicts with existing user exercise"];
        }

        // Create new global exercise
        $exercise = Exercise::create([
            'user_id' => null, // Global exercise
            'title' => $title,
            'description' => $description,
            'is_bodyweight' => $isBodyweight,
        ]);

        return ['action' => 'imported', 'exercise' => $exercise];
    }

    private function processUserExerciseImport(string $title, string $description, bool $isBodyweight, int $userId): array
    {
        // Check for global exercise conflict first
        $globalConflict = Exercise::global()
            ->whereRaw('LOWER(title) = ?', [strtolower($title)])
            ->first();

        if ($globalConflict) {
            return ['action' => 'skipped', 'reason' => "Exercise '{$title}' conflicts with existing global exercise"];
        }

        // Check for existing user exercise
        $existingUser = Exercise::userSpecific($userId)
            ->whereRaw('LOWER(title) = ?', [strtolower($title)])
            ->first();

        if ($existingUser) {
            // Check if data differs
            $changes = [];
            if ($existingUser->description !== $description) {
                $changes['description'] = ['from' => $existingUser->description, 'to' => $description];
            }
            if ($existingUser->is_bodyweight !== $isBodyweight) {
                $changes['is_bodyweight'] = ['from' => $existingUser->is_bodyweight, 'to' => $isBodyweight];
            }
            
            if (!empty($changes)) {
                $existingUser->update([
                    'description' => $description,
                    'is_bodyweight' => $isBodyweight,
                ]);
                return ['action' => 'updated', 'exercise' => $existingUser, 'changes' => $changes];
            } else {
                return ['action' => 'skipped', 'reason' => "Personal exercise '{$title}' already exists with same data"];
            }
        }

        // Create new user exercise
        $exercise = Exercise::create([
            'user_id' => $userId,
            'title' => $title,
            'description' => $description,
            'is_bodyweight' => $isBodyweight,
        ]);

        return ['action' => 'imported', 'exercise' => $exercise];
    }

    private function parseBooleanValue(string $value): bool
    {
        $boolValue = strtolower(trim($value));
        return in_array($boolValue, ['true', '1', 'yes', 'y']);
    }
}
```

### Controller Updates

```php
class ExerciseController extends Controller
{
    public function importTsv(Request $request)
    {
        $request->validate([
            'tsv_data' => 'required|string',
            'import_as_global' => 'boolean'
        ]);

        $tsvData = $request->input('tsv_data');
        $importAsGlobal = $request->boolean('import_as_global', false);

        if (empty(trim($tsvData))) {
            return redirect()->route('exercises.index')
                ->with('error', 'TSV data cannot be empty.');
        }

        // Validate admin permission for global imports
        if ($importAsGlobal && !auth()->user()->hasRole('Admin')) {
            return redirect()->route('exercises.index')
                ->with('error', 'Only administrators can import global exercises.');
        }

        try {
            $result = app(TsvImporterService::class)->importExercises(
                $tsvData, 
                auth()->id(), 
                $importAsGlobal
            );

            $message = $this->buildImportSuccessMessage($result);
            
            return redirect()->route('exercises.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            return redirect()->route('exercises.index')
                ->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    private function buildImportSuccessMessage(array $result): string
    {
        $mode = $result['importMode'] === 'global' ? 'global' : 'personal';
        $parts = ["TSV data processed successfully!"];

        // Imported exercises
        if ($result['importedCount'] > 0) {
            $parts[] = "Imported {$result['importedCount']} new {$mode} exercises:";
            foreach ($result['importedExercises'] as $exercise) {
                $bodyweightText = $exercise['is_bodyweight'] ? ' (bodyweight)' : '';
                $parts[] = "• {$exercise['title']}{$bodyweightText}";
            }
        }

        // Updated exercises
        if ($result['updatedCount'] > 0) {
            $parts[] = "Updated {$result['updatedCount']} existing {$mode} exercises:";
            foreach ($result['updatedExercises'] as $exercise) {
                $changeDetails = [];
                foreach ($exercise['changes'] as $field => $change) {
                    if ($field === 'is_bodyweight') {
                        $changeDetails[] = "bodyweight: " . ($change['from'] ? 'yes' : 'no') . " → " . ($change['to'] ? 'yes' : 'no');
                    } else {
                        $changeDetails[] = "{$field}: '{$change['from']}' → '{$change['to']}'";
                    }
                }
                $parts[] = "• {$exercise['title']} (" . implode(', ', $changeDetails) . ")";
            }
        }

        // Skipped exercises
        if ($result['skippedCount'] > 0) {
            $parts[] = "Skipped {$result['skippedCount']} exercises:";
            foreach ($result['skippedExercises'] as $exercise) {
                $parts[] = "• {$exercise['title']} - {$exercise['reason']}";
            }
        }

        // Invalid rows
        if (count($result['invalidRows']) > 0) {
            $parts[] = "Found " . count($result['invalidRows']) . " invalid rows that were skipped.";
        }

        if ($result['importedCount'] === 0 && $result['updatedCount'] === 0) {
            $parts[] = "No new data was imported or updated - all entries already exist with the same data.";
        }

        return implode("\n", $parts);
    }
}
```

### View Updates

The exercise import form will be updated to include the global import option for administrators:

```blade
<!-- exercises/index.blade.php - Import Form Section -->
<form method="POST" action="{{ route('exercises.import-tsv') }}" class="mb-4">
    @csrf
    <div class="form-group">
        <label for="tsv_data">TSV Data:</label>
        <textarea name="tsv_data" id="tsv_data" class="form-control" rows="10" 
                  placeholder="Exercise Name	Description	Is Bodyweight (true/false)"></textarea>
    </div>
    
    @if(auth()->user()->hasRole('Admin'))
    <div class="form-group">
        <div class="form-check">
            <input type="checkbox" name="import_as_global" id="import_as_global" 
                   class="form-check-input" value="1">
            <label for="import_as_global" class="form-check-label">
                Import as Global Exercises (available to all users)
            </label>
        </div>
        <small class="form-text text-muted">
            Global exercises will be available to all users and can only be managed by administrators.
        </small>
    </div>
    @endif
    
    <button type="submit" class="btn btn-primary">Import Exercises</button>
</form>
```

## Data Models

No changes to the Exercise model are required as it already supports the global/user-specific functionality through the existing scopes and methods.

## Error Handling

### Import Validation

1. **Permission Validation**: Verify admin role for global imports
2. **Conflict Detection**: Detailed reporting of name conflicts
3. **Data Validation**: Proper handling of malformed TSV data

### User Feedback

1. **Success Messages**: Clear indication of import results with counts
2. **Error Messages**: Specific error descriptions for different failure modes
3. **Conflict Reports**: Detailed information about skipped exercises

## Testing Strategy

### Unit Tests

1. **Service Method Tests**: Test both global and user import modes
2. **Conflict Resolution**: Test various conflict scenarios
3. **Permission Validation**: Test admin-only global import restrictions
4. **Data Processing**: Test TSV parsing and boolean value handling

### Feature Tests

1. **Controller Integration**: Test full import workflow through web interface
2. **Permission Tests**: Verify admin vs user access to global import options
3. **UI Tests**: Test form rendering and submission with different user roles
4. **Import Result Display**: Test success/error message generation

### Integration Tests

1. **End-to-End Import**: Test complete import workflow with existing exercises
2. **Cross-User Conflicts**: Test global vs personal exercise conflicts
3. **Data Integrity**: Verify no duplicate exercises are created
4. **Backward Compatibility**: Ensure existing import functionality still works