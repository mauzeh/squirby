# Calorie Calculation Refactor Implementation Plan

## Objective

The current system stores the calorie count of an ingredient as a separate value. This plan outlines the steps to refactor the application to calculate the calorie count dynamically based on the ingredient's protein, carbohydrate, and fat content.

The formula for calorie calculation will be:

*   **Protein:** 4 calories per gram
*   **Carbohydrates:** 4 calories per gram
*   **Fat:** 9 calories per gram

## Implementation Steps

### 1. Database Migration

*   Create a new database migration to modify the `ingredients` table.
*   The `calories` column will be made `nullable`. This is an interim step to allow for a smooth transition. In the future, this column could be removed entirely.

### 2. Model-level Changes (app/Models/Ingredient.php)

*   Create an accessor method `getCaloriesAttribute()` in the `Ingredient` model.
*   This accessor will calculate the total calories using the formula: `($this->protein * 4) + ($this->carbs * 4) + ($this->fats * 9)`.
*   This will ensure that any time the `calories` attribute is accessed on an `Ingredient` object, it will be the calculated value.

### 3. Controller and Validation Logic (app/Http/Controllers/IngredientController.php)

*   In the `store` and `update` methods of the `IngredientController`, remove the `calories` field from the validation rules.
*   Remove the `calories` field from the data array passed to the `Ingredient::create()` and `Ingredient::update()` methods. The calorie count will no longer be a direct input from the user.

### 4. Service Layer (app/Services/NutritionService.php)

*   The `calculateTotalMacro` method in the `NutritionService` should not require any changes. Laravel's model accessors will ensure that when `$ingredient->calories` is accessed, the calculated value is returned.
*   A review of this service will be conducted to confirm this behavior.

### 5. Testing

*   **Unit Test:** Create a new unit test for the `Ingredient` model. This test will create an ingredient with known protein, carb, and fat values and assert that the `calories` attribute returns the correct calculated value.
*   **Feature Test:** Update the `IngredientManagementTest` to reflect the changes in the UI. The tests that create or update ingredients should be modified to remove the `calories` field from the form submission data.
*   **Regression Testing:** Run the entire test suite to ensure that these changes do not have unintended side effects on other parts of the application.

### 6. Seeder Updates (database/seeders/IngredientSeeder.php)

*   In the `IngredientSeeder`, the `calories` field is currently being populated directly from the `ingredients_from_real_world.csv` file.
*   This will be updated to remove the direct assignment of the `calories` field.
*   The seeder will continue to populate the `protein`, `carbs`, and `fats` fields. The `calories` will be calculated automatically by the `Ingredient` model's accessor.
*   The line `'calories' => (float)($rowData['Calories'] ?? 0),` will be removed from the `Ingredient::create()` call within the seeder.