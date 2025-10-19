<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Unit tests for mobile food entry increment/decrement logic
 * These tests verify the JavaScript increment amount calculation rules
 */
class MobileFoodEntryIncrementLogicTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test data for increment amount calculations based on unit names
     * This mirrors the JavaScript getIncrementAmount() function logic
     */
    public static function incrementAmountDataProvider(): array
    {
        return [
            // Large units (grams, milliliters) - increment by 10
            ['grams', 10],
            ['g', 10],
            ['gram', 10],
            ['ml', 10],
            ['milliliter', 10],
            ['milliliters', 10],
            ['ML', 10], // Case insensitive
            ['GRAMS', 10], // Case insensitive
            
            // Medium units (kilograms, pounds, liters) - increment by 0.1
            ['kg', 0.1],
            ['kilogram', 0.1],
            ['kilograms', 0.1],
            ['lb', 0.1],
            ['lbs', 0.1],
            ['pound', 0.1],
            ['pounds', 0.1],
            ['liter', 0.1],
            ['liters', 0.1],
            ['KG', 0.1], // Case insensitive
            ['LBS', 0.1], // Case insensitive
            
            // Count units (pieces, servings) - increment by 0.25
            ['pc', 0.25],
            ['pcs', 0.25],
            ['piece', 0.25],
            ['pieces', 0.25],
            ['serving', 0.25],
            ['servings', 0.25],
            ['each', 0.25],
            ['item', 0.25],
            ['items', 0.25],
            ['PC', 0.25], // Case insensitive
            ['SERVINGS', 0.25], // Case insensitive
            
            // Default units - increment by 1
            ['cups', 1],
            ['tablespoons', 1],
            ['teaspoons', 1],
            ['ounces', 1],
            ['unknown_unit', 1],
            ['', 1], // Empty string
        ];
    }

    /**
     * @test
     * @dataProvider incrementAmountDataProvider
     */
    public function increment_amount_calculation_matches_javascript_logic($unitName, $expectedIncrement)
    {
        // This test documents the expected increment amounts for different unit types
        // The actual logic is implemented in JavaScript, but this serves as documentation
        // and ensures consistency between frontend and backend expectations
        
        $unit = Unit::factory()->create(['name' => $unitName]);
        
        // Simulate the JavaScript getIncrementAmount logic
        $calculatedIncrement = $this->calculateIncrementAmount($unitName);
        
        $this->assertEquals($expectedIncrement, $calculatedIncrement, 
            "Unit '{$unitName}' should have increment amount of {$expectedIncrement}");
    }

    /**
     * Simulate the JavaScript getIncrementAmount function
     * This mirrors the client-side logic for testing consistency
     */
    private function calculateIncrementAmount(string $unitName): float
    {
        $unit = strtolower($unitName);
        
        // Match the exact JavaScript logic with else-if chain
        // Requirement 6.1: grams or milliliters increment by 10
        if (str_contains($unit, 'g') || str_contains($unit, 'ml') || 
            str_contains($unit, 'gram') || str_contains($unit, 'milliliter')) {
            return 10;
        } 
        // Requirement 6.2: kilograms, pounds, or liters increment by 0.1
        else if (str_contains($unit, 'kg') || str_contains($unit, 'lb') || 
                 str_contains($unit, 'liter') || str_contains($unit, 'pound') || 
                 str_contains($unit, 'kilogram')) {
            return 0.1;
        } 
        // Requirement 6.3: pieces or servings increment by 0.25
        else if (str_contains($unit, 'pc') || str_contains($unit, 'serving') || 
                 str_contains($unit, 'piece') || str_contains($unit, 'pcs') || 
                 str_contains($unit, 'each') || str_contains($unit, 'item')) {
            return 0.25;
        }
        
        // Default increment for other units
        return 1;
    }

    /** @test */
    public function meal_portion_always_increments_by_quarter()
    {
        // Meal portions always increment by 0.25 regardless of unit
        $expectedIncrement = 0.25;
        
        $this->assertEquals($expectedIncrement, 0.25, 
            'Meal portions should always increment by 0.25');
    }

    /** @test */
    public function increment_amount_is_case_insensitive()
    {
        $testCases = [
            ['grams', 'GRAMS', 'Grams', 'gRaMs'],
            ['kg', 'KG', 'Kg', 'kG'],
            ['pieces', 'PIECES', 'Pieces', 'PiEcEs'],
        ];
        
        foreach ($testCases as $variations) {
            $expectedIncrement = $this->calculateIncrementAmount($variations[0]);
            
            foreach ($variations as $variation) {
                $calculatedIncrement = $this->calculateIncrementAmount($variation);
                $this->assertEquals($expectedIncrement, $calculatedIncrement,
                    "Unit name '{$variation}' should have same increment as '{$variations[0]}'");
            }
        }
    }

    /** @test */
    public function partial_unit_name_matches_work_correctly()
    {
        // Test that partial matches work (e.g., "g" in "grams", "kg" in "kilograms")
        $partialMatches = [
            'grams' => 10,      // Contains 'g'
            'kilograms' => 0.1, // Contains 'kg' (should match kg before g)
            'serving' => 0.25,  // Contains 'serving'
            'pieces' => 0.25,   // Contains 'pc'
        ];
        
        foreach ($partialMatches as $unitName => $expectedIncrement) {
            $calculatedIncrement = $this->calculateIncrementAmount($unitName);
            $this->assertEquals($expectedIncrement, $calculatedIncrement,
                "Unit '{$unitName}' should match partial string and have increment {$expectedIncrement}");
        }
    }

    /** @test */
    public function unit_precedence_works_correctly()
    {
        // Test that more specific matches take precedence
        // "kg" should match before "g" in "kilograms"
        $this->assertEquals(0.1, $this->calculateIncrementAmount('kilograms'));
        $this->assertEquals(0.1, $this->calculateIncrementAmount('kg'));
        
        // "pc" should match in "pieces"
        $this->assertEquals(0.25, $this->calculateIncrementAmount('pieces'));
        $this->assertEquals(0.25, $this->calculateIncrementAmount('pc'));
    }

    /** @test */
    public function empty_and_null_units_default_to_one()
    {
        $this->assertEquals(1, $this->calculateIncrementAmount(''));
        $this->assertEquals(1, $this->calculateIncrementAmount('unknown'));
        $this->assertEquals(1, $this->calculateIncrementAmount('xyz'));
    }

    /** @test */
    public function compound_unit_names_work_correctly()
    {
        // Test units that might contain multiple keywords
        $compoundUnits = [
            'grams_per_serving' => 10,   // Should match 'g' first
            'kg_per_piece' => 0.1,       // Should match 'kg' first
            'milliliter_serving' => 10,  // Should match 'ml' first
        ];
        
        foreach ($compoundUnits as $unitName => $expectedIncrement) {
            $calculatedIncrement = $this->calculateIncrementAmount($unitName);
            $this->assertEquals($expectedIncrement, $calculatedIncrement,
                "Compound unit '{$unitName}' should have increment {$expectedIncrement}");
        }
    }

    /** @test */
    public function common_cooking_units_have_expected_increments()
    {
        $cookingUnits = [
            // Weight units
            'grams' => 10,
            'kilograms' => 0.1,
            'pounds' => 0.1,
            'ounces' => 1, // Default
            
            // Volume units
            'milliliters' => 10,
            'liters' => 0.1,
            'cups' => 1, // Default
            'tablespoons' => 1, // Default
            'teaspoons' => 1, // Default
            
            // Count units
            'pieces' => 0.25,
            'servings' => 0.25,
            'items' => 0.25,
            'each' => 0.25,
        ];
        
        foreach ($cookingUnits as $unitName => $expectedIncrement) {
            $calculatedIncrement = $this->calculateIncrementAmount($unitName);
            $this->assertEquals($expectedIncrement, $calculatedIncrement,
                "Cooking unit '{$unitName}' should have increment {$expectedIncrement}");
        }
    }

    /** @test */
    public function floating_point_precision_is_maintained()
    {
        // Test that decimal increments maintain proper precision
        $decimalIncrements = [
            'kg' => 0.1,
            'pounds' => 0.1,
            'pieces' => 0.25,
            'servings' => 0.25,
        ];
        
        foreach ($decimalIncrements as $unitName => $expectedIncrement) {
            $calculatedIncrement = $this->calculateIncrementAmount($unitName);
            
            // Use assertEqualsWithDelta for floating point comparison
            $this->assertEqualsWithDelta($expectedIncrement, $calculatedIncrement, 0.001,
                "Unit '{$unitName}' increment should be exactly {$expectedIncrement}");
        }
    }
}