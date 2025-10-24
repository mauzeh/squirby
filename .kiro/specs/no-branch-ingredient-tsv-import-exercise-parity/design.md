# Design Document

## Overview

This design document outlines the implementation approach to make the Ingredient TSV import functionality identical to the Exercise TSV import system. The key focus is on enhancing the import logic with detailed result tracking, conflict resolution, and comprehensive user feedback while maintaining the existing 15-column TSV format and personal-only ingredient model.

## Architecture

### Current State Analysis

**Existing Ingredient Import:**
- Uses `IngredientTsvProcessorService` for complex 15-column processing
- Basic import/update logic without detailed tracking
- Simple success/error messages
- Personal ingredients only (user_id = current user)

**Target Exercise Import Pattern:**
- Direct processing in `TsvImporterService`
- Detailed result tracking (imported, updated, skipped with reasons)
- Rich HTML success messages with change details
- Conflict resolution with case-insensitive matching
- Global vs personal support (we'll adapt for personal-only)

### Implementation Strategy

We will enhance the existing ingredient import system by:
1. Extending the current `importIngredients` method to match exercise import patterns
2. Maintaining the existing `IngredientTsvProcessorService` for TSV parsing
3. Adding detailed result tracking and conflict resolution
4. Implementing rich success message generation
5. Updating the controller and view to match exercise patterns

## Components and Interfaces

### 1. Enhanced TsvImporterService::importIngredients Method

**Current Signature:**
```php
public function importIngredients(string $tsvData, int $userId): array
```

**Enhanced Return Structure:**
```php
return [
    'importedCount' => int,
    'updatedCount' => int, 
    'skippedCount' => int,
    'invalidRows' => array,
    'importedIngredients' => array,
    'updatedIngredients' => array,
    'skippedIngredients' => array,
    'importMode' => 'personal' // Always personal for ingredients
];
```

**Enhanced Processing Logic:**
```php
public function importIngredients(string $tsvData, int $userId): array
{
    // Use existing IngredientTsvProcessorService for parsing
    $expectedHeader = [...]; // Existing 15-column format
    
    $importedCount = 0;
    $updatedCount = 0;
    $skippedCount = 0;
    $importedIngredients = [];
    $updatedIngredients = [];
    $skippedIngredients = [];
    
    $result = $this->ingredientTsvProcessorService->processTsv(
        $tsvData,
        $expectedHeader,
        function ($rowData) use ($userId, &$importedCount, &$updatedCount, &$skippedCount, ...) {
            $processResult = $this->processIngredientImport($rowData, $userId);
            
            switch ($processResult['action']) {
                case 'imported':
                    $importedCount++;
                    $importedIngredients[] = $processResult['ingredient'];
                    break;
                case 'updated':
                    $updatedCount++;
                    $updatedIngredients[] = $processResult['ingredient'];
                    break;
                case 'skipped':
                    $skippedCount++;
                    $skippedIngredients[] = $processResult['reason'];
                    break;
            }
        }
    );
    
    return [
        'importedCount' => $importedCount,
        'updatedCount' => $updatedCount,
        'skippedCount' => $skippedCount,
        'invalidRows' => $result['invalidRows'],
        'importedIngredients' => $importedIngredients,
        'updatedIngredients' => $updatedIngredients,
        'skippedIngredients' => $skippedIngredients,
        'importMode' => 'personal'
    ];
}
```

### 2. New Helper Method: processIngredientImport

```php
private function processIngredientImport(array $rowData, int $userId): array
{
    $ingredientName = $rowData['Ingredient'];
    
    // Case-insensitive lookup for existing ingredient
    $existingIngredient = Ingredient::where('user_id', $userId)
        ->whereRaw('LOWER(name) = ?', [strtolower($ingredientName)])
        ->first();
    
    // Prepare ingredient data
    $ingredientData = [
        'user_id' => $userId,
        'name' => $ingredientName,
        'base_quantity' => (float)($rowData['Amount'] ?? 1),
        // ... all other nutritional fields
    ];
    
    if ($existingIngredient) {
        // Check if data differs
        $changes = $this->detectIngredientChanges($existingIngredient, $ingredientData);
        
        if (!empty($changes)) {
            $existingIngredient->update($ingredientData);
            return [
                'action' => 'updated',
                'ingredient' => $existingIngredient,
                'changes' => $changes
            ];
        } else {
            return [
                'action' => 'skipped',
                'reason' => "Ingredient '{$ingredientName}' already exists with same data"
            ];
        }
    }
    
    // Create new ingredient
    $ingredient = Ingredient::create($ingredientData);
    return [
        'action' => 'imported',
        'ingredient' => $ingredient
    ];
}
```

### 3. Change Detection Method

```php
private function detectIngredientChanges(Ingredient $existing, array $newData): array
{
    $changes = [];
    $trackableFields = [
        'name', 'base_quantity', 'protein', 'carbs', 'fats', 
        'sodium', 'fiber', 'calcium', 'iron', 'potassium',
        'caffeine', 'added_sugars', 'cost_per_unit'
    ];
    
    foreach ($trackableFields as $field) {
        if (isset($newData[$field]) && $existing->$field != $newData[$field]) {
            $changes[$field] = [
                'from' => $existing->$field,
                'to' => $newData[$field]
            ];
        }
    }
    
    return $changes;
}
```

### 4. Enhanced IngredientController::importTsv Method

```php
public function importTsv(Request $request)
{
    $validated = $request->validate([
        'tsv_data' => 'required|string',
    ]);

    $tsvData = trim($validated['tsv_data']);

    if (empty($tsvData)) {
        return redirect()
            ->route('ingredients.index')
            ->with('error', 'TSV data cannot be empty.');
    }

    try {
        $result = $this->tsvImporterService->importIngredients($tsvData, auth()->id());

        $message = $this->buildImportSuccessMessage($result);
        
        return redirect()
            ->route('ingredients.index')
            ->with('success', $message);

    } catch (\Exception $e) {
        return redirect()
            ->route('ingredients.index')
            ->with('error', 'Import failed: ' . $e->getMessage());
    }
}
```

### 5. Success Message Builder

```php
private function buildImportSuccessMessage(array $result): string
{
    $html = "<p>TSV data processed successfully!</p>";

    // Imported ingredients
    if ($result['importedCount'] > 0) {
        $html .= "<p>Imported {$result['importedCount']} new ingredients:</p><ul>";
        foreach ($result['importedIngredients'] as $ingredient) {
            $html .= "<li>" . e($ingredient['name']) . " ({$ingredient['base_quantity']}{$ingredient['unit_abbreviation']})</li>";
        }
        $html .= "</ul>";
    }

    // Updated ingredients
    if ($result['updatedCount'] > 0) {
        $html .= "<p>Updated {$result['updatedCount']} existing ingredients:</p><ul>";
        foreach ($result['updatedIngredients'] as $ingredient) {
            $changeDetails = [];
            foreach ($ingredient['changes'] as $field => $change) {
                $changeDetails[] = e($field) . ": '" . e($change['from']) . "' → '" . e($change['to']) . "'";
            }
            $html .= "<li>" . e($ingredient['name']) . " (" . implode(', ', $changeDetails) . ")</li>";
        }
        $html .= "</ul>";
    }

    // Skipped ingredients
    if ($result['skippedCount'] > 0) {
        $html .= "<p>Skipped {$result['skippedCount']} ingredients:</p><ul>";
        foreach ($result['skippedIngredients'] as $skipped) {
            $html .= "<li>" . e($skipped['name']) . " - " . e($skipped['reason']) . "</li>";
        }
        $html .= "</ul>";
    }

    // Invalid rows
    if (count($result['invalidRows']) > 0) {
        $html .= "<p>Found " . count($result['invalidRows']) . " invalid rows that were skipped.</p>";
    }

    if ($result['importedCount'] === 0 && $result['updatedCount'] === 0) {
        $html .= "<p>No new data was imported or updated - all entries already exist with the same data.</p>";
    }

    return $html;
}
```

## Data Models

### Ingredient Model Enhancements

The existing Ingredient model will remain unchanged as it already supports the required functionality:

```php
class Ingredient extends Model
{
    // Existing fillable fields include all necessary attributes
    protected $fillable = [
        'name', 'protein', 'carbs', 'added_sugars', 'fats',
        'sodium', 'iron', 'potassium', 'fiber', 'calcium',
        'caffeine', 'base_quantity', 'base_unit_id', 
        'cost_per_unit', 'user_id',
    ];
    
    // Existing relationships and methods remain unchanged
}
```

### Database Considerations

No database changes are required as:
- Ingredients already have `user_id` for personal ownership
- All nutritional fields exist
- Relationships with units are established

## Error Handling

### Validation Strategy

1. **Input Validation:**
   - Required TSV data validation
   - Empty data detection
   - Format validation through existing `IngredientTsvProcessorService`

2. **Processing Error Handling:**
   - Invalid row collection and reporting
   - Unit lookup failures
   - Database constraint violations
   - Service exceptions with user-friendly messages

3. **Response Handling:**
   - Graceful error messages for users
   - Detailed success messages with HTML formatting
   - Proper redirect responses with session flash messages

### Exception Management

```php
try {
    $result = $this->tsvImporterService->importIngredients($tsvData, auth()->id());
    // Success handling
} catch (ValidationException $e) {
    // Handle validation errors
} catch (DatabaseException $e) {
    // Handle database errors
} catch (\Exception $e) {
    // Handle general exceptions
    return redirect()->back()->with('error', 'Import failed: ' . $e->getMessage());
}
```

## Testing Strategy

### Unit Tests

1. **TsvImporterService Tests:**
   - Test ingredient import with various scenarios
   - Test conflict resolution logic
   - Test change detection
   - Test result tracking accuracy

2. **Controller Tests:**
   - Test import endpoint validation
   - Test success message generation
   - Test error handling paths

### Feature Tests

1. **Integration Tests:**
   - End-to-end import workflow
   - UI form submission and response
   - Success message display
   - Error message handling

2. **Edge Case Tests:**
   - Empty data handling
   - Invalid TSV format
   - Duplicate ingredient names
   - Case-insensitive matching

### Test Data Scenarios

```php
// Test scenarios to implement:
- Import new ingredients
- Update existing ingredients with changes
- Skip ingredients with identical data
- Handle invalid rows gracefully
- Case-insensitive name matching
- Mixed import results (new, updated, skipped)
```

## Implementation Phases

### Phase 1: Core Logic Enhancement
- Enhance `importIngredients` method with detailed tracking
- Implement `processIngredientImport` helper method
- Add change detection logic

### Phase 2: Controller and Message Updates
- Update `IngredientController::importTsv` method
- Implement `buildImportSuccessMessage` method
- Enhance error handling

### Phase 3: UI Consistency
- Keep the existing ingredients index view form unchanged
- Ensure form validation and user experience consistency with existing behavior
- Maintain current form styling and structure

### Phase 4: Testing and Validation
- Implement comprehensive unit tests
- Add feature tests for end-to-end workflows
- Validate against exercise import behavior patterns

This design maintains the existing ingredient TSV format and personal-only model while providing the same sophisticated import experience as the exercise system.