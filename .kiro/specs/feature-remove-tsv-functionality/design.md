# Design Document

## Overview

This design outlines the complete removal of all TSV (Tab-Separated Values) functionality from the application, including user-facing import features, TSV processing services, and CSV/TSV-based seeders. The removal will be replaced with minimal hardcoded seeders that provide essential data for development and testing without the complexity of file-based data processing.

## Architecture

The removal involves three main architectural layers:

1. **Web Layer**: Remove TSV import routes, controller methods, and UI forms
2. **Service Layer**: Remove all TSV processing services and related utilities
3. **Data Layer**: Replace CSV/TSV-based seeders with hardcoded data arrays

## Components and Interfaces

### 1. Web Layer Removal

#### Routes to Remove
- `POST food-logs/import-tsv`
- `POST ingredients/import-tsv`
- `POST body-logs/import-tsv`
- `POST exercises/import-tsv`
- `POST lift-logs/import-tsv`
- `POST programs/import`

#### Controller Methods to Remove
- `FoodLogController::importTsv`
- `IngredientController::importTsv`
- `BodyLogController::importTsv`
- `ExerciseController::importTsv`
- `LiftLogController::importTsv`
- `ProgramController::import`

#### View Components to Remove
- TSV import forms in all data management views
- TSV-related form validation and error handling
- Environment-based form hiding logic

#### Middleware to Remove
- `RestrictTsvImportsInProduction` middleware
- Related middleware registration

### 2. Service Layer Removal

#### Services to Remove
```php
// Complete removal of these classes
app/Services/TsvImporterService.php
app/Services/IngredientTsvProcessorService.php
app/Services/ProgramTsvImporterService.php
```

#### Dependencies to Clean Up
- Remove TSV service constructor injections from controllers
- Remove TSV service provider registrations
- Clean up any service container bindings

### 3. Data Layer Transformation

#### Current CSV/TSV-Based Seeders
```php
// Current implementation
class IngredientSeeder extends Seeder
{
    public function run(): void
    {
        // Reads CSV file and uses IngredientTsvProcessorService
        $processor = new IngredientTsvProcessorService();
        // Complex CSV to TSV conversion and processing
    }
}
```

#### New Hardcoded Seeders
```php
// New implementation
class IngredientSeeder extends Seeder
{
    public function run(): void
    {
        $ingredients = $this->getHardcodedIngredients();
        
        foreach ($ingredients as $ingredientData) {
            Ingredient::firstOrCreate(
                ['name' => $ingredientData['name'], 'user_id' => $adminUser->id],
                $ingredientData
            );
        }
    }
    
    private function getHardcodedIngredients(): array
    {
        return [
            // Minimal but comprehensive ingredient dataset
        ];
    }
}
```

### 4. Hardcoded Data Design

#### Ingredient Dataset Structure
```php
[
    'name' => 'Chicken Breast',
    'base_quantity' => 100,
    'base_unit_id' => $gramUnit->id,
    'protein' => 31.0,
    'carbs' => 0.0,
    'fats' => 3.6,
    'calories' => 165,
    'sodium' => 74,
    'fiber' => 0,
    // ... other nutritional fields
]
```

#### Exercise Dataset Structure
```php
[
    'title' => 'Push-up',
    'description' => 'Bodyweight upper body exercise',
    'is_bodyweight' => true,
    'band_type' => null,
    'user_id' => null, // Global exercise
]
```

## Data Models

### Ingredient Data Categories
1. **Proteins**: Chicken breast, salmon, eggs, tofu
2. **Carbohydrates**: Rice, oats, banana, sweet potato
3. **Fats**: Olive oil, avocado, nuts, seeds
4. **Vegetables**: Broccoli, spinach, carrots, bell peppers
5. **Dairy**: Milk, Greek yogurt, cheese

### Exercise Data Categories
1. **Upper Body**: Push-ups, pull-ups, bench press, rows
2. **Lower Body**: Squats, deadlifts, lunges, calf raises
3. **Core**: Planks, crunches, mountain climbers
4. **Cardio**: Running, cycling, jumping jacks
5. **Full Body**: Burpees, thrusters, clean and press

### Unit Requirements
The hardcoded data must work with existing units:
- Grams (g) for most ingredients
- Pieces (pc) for countable items
- Tablespoons (tbsp) and teaspoons (tsp) for small quantities
- Milliliters (ml) for liquids

## Error Handling

### Route Removal Strategy
- Remove routes entirely rather than returning 404s
- Clean up route group middleware that's no longer needed
- Update any route-based navigation or links

### Seeder Error Handling
```php
// Robust seeder implementation
public function run(): void
{
    try {
        $adminUser = User::where('email', 'admin@example.com')->first();
        if (!$adminUser) {
            $this->command->error('Admin user not found. Please run UserSeeder first.');
            return;
        }
        
        $this->seedIngredients($adminUser);
        $this->command->info('Successfully seeded ingredients');
    } catch (\Exception $e) {
        $this->command->error('Failed to seed ingredients: ' . $e->getMessage());
        throw $e;
    }
}
```

## Testing Strategy

### Test Removal
1. **Remove TSV-specific tests**:
   - `TsvImporterServiceTest.php`
   - `IngredientTsvProcessorServiceTest.php`
   - `ProgramTsvImporterServiceTest.php`
   - Controller TSV import tests

2. **Remove TSV route tests**:
   - All `import-tsv` route tests
   - TSV form submission tests
   - TSV validation tests

### Test Updates
1. **Update seeder tests**:
   - Test hardcoded data creation
   - Verify seeder performance improvements
   - Test seeder error handling

2. **Update integration tests**:
   - Remove TSV import workflow tests
   - Update any tests that depend on TSV-seeded data

### Performance Testing
- Measure seeder execution time before/after
- Verify application startup time improvements
- Test memory usage during seeding

## Migration Strategy

### Phase 1: Service and Route Removal
1. Remove TSV import routes from `routes/web.php`
2. Remove TSV import methods from controllers
3. Remove TSV service classes
4. Remove TSV middleware

### Phase 2: View Cleanup
1. Remove TSV import forms from all views
2. Clean up TSV-related JavaScript/CSS
3. Remove environment-based TSV form logic
4. Update navigation/UI that referenced TSV features

### Phase 3: Seeder Replacement
1. Create hardcoded ingredient dataset
2. Create hardcoded exercise dataset
3. Update `IngredientSeeder` implementation
4. Update `GlobalExercisesSeeder` implementation
5. Remove CSV files from `database/seeders/csv/`

### Phase 4: Test and Documentation Cleanup
1. Remove TSV-related tests
2. Update seeder tests
3. Remove TSV references from documentation
4. Update development setup instructions

## Rollback Strategy

### Code Preservation
- Tag current codebase before TSV removal
- Document removed functionality for potential future reference
- Preserve CSV data files in version control history

### Restoration Process
If TSV functionality needs to be restored:
1. Revert to pre-removal git tag
2. Cherry-pick any non-TSV improvements made after removal
3. Re-run tests to ensure functionality

## Performance Considerations

### Expected Improvements
1. **Faster Seeding**: Hardcoded data eliminates file I/O and parsing overhead
2. **Reduced Memory Usage**: No CSV file loading or TSV processing in memory
3. **Smaller Codebase**: Removal of ~1000+ lines of TSV-related code
4. **Faster Application Startup**: No TSV service registration or route compilation

### Benchmarking
- Current IngredientSeeder: ~2-5 seconds with CSV processing
- Expected new seeder: <1 second with hardcoded data
- Route registration: Reduced by ~6 routes
- Service container: Reduced by 3 service registrations

## Security Considerations

### Attack Surface Reduction
- Eliminates file upload/processing vulnerabilities
- Removes TSV parsing attack vectors
- Simplifies input validation requirements

### Data Integrity
- Hardcoded data ensures consistent, validated datasets
- Eliminates risk of malformed CSV/TSV files
- Reduces dependency on external data files