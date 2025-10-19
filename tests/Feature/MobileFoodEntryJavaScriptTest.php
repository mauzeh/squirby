<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * JavaScript functionality tests for mobile food entry
 * These tests verify client-side behavior including increment/decrement buttons,
 * base quantity defaults, and form validation
 */
class MobileFoodEntryJavaScriptTest extends DuskTestCase
{
    use RefreshDatabase;

    protected $user;
    protected $ingredients;
    protected $meal;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        
        // Create units for different increment testing
        $gramsUnit = Unit::factory()->create(['name' => 'grams']);
        $kgUnit = Unit::factory()->create(['name' => 'kg']);
        $piecesUnit = Unit::factory()->create(['name' => 'pieces']);
        $mlUnit = Unit::factory()->create(['name' => 'ml']);
        
        // Create ingredients with different units and base quantities
        $this->ingredients = [
            'grams' => Ingredient::factory()->create([
                'user_id' => $this->user->id,
                'base_unit_id' => $gramsUnit->id,
                'base_quantity' => 250,
                'name' => 'Flour',
            ]),
            'kg' => Ingredient::factory()->create([
                'user_id' => $this->user->id,
                'base_unit_id' => $kgUnit->id,
                'base_quantity' => 1.5,
                'name' => 'Chicken Breast',
            ]),
            'pieces' => Ingredient::factory()->create([
                'user_id' => $this->user->id,
                'base_unit_id' => $piecesUnit->id,
                'base_quantity' => 2,
                'name' => 'Apples',
            ]),
            'ml' => Ingredient::factory()->create([
                'user_id' => $this->user->id,
                'base_unit_id' => $mlUnit->id,
                'base_quantity' => 500,
                'name' => 'Milk',
            ]),
        ];
        
        $this->meal = Meal::factory()->create(['user_id' => $this->user->id, 'name' => 'Test Meal']);
        $this->meal->ingredients()->attach($this->ingredients['grams']->id, ['quantity' => 100]);
    }

    /** @test */
    public function ingredient_selection_sets_base_quantity_as_default()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('food-logs.mobile-entry'))
                    ->click('#add-food-button')
                    ->waitFor('.food-list-item')
                    ->click('[data-name="Flour"]')
                    ->waitFor('#ingredient-fields')
                    ->assertInputValue('#quantity', '250'); // Should use base_quantity
        });
    }

    /** @test */
    public function meal_selection_sets_default_portion_to_one()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('food-logs.mobile-entry'))
                    ->click('#add-food-button')
                    ->waitFor('.food-list-item')
                    ->click('[data-name="Test Meal"]')
                    ->waitFor('#meal-fields')
                    ->assertInputValue('#portion', '1'); // Should always be 1 for meals
        });
    }

    /** @test */
    public function increment_button_increases_quantity_by_correct_amount_for_grams()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('food-logs.mobile-entry'))
                    ->click('#add-food-button')
                    ->waitFor('.food-list-item')
                    ->click('[data-name="Flour"]') // grams unit
                    ->waitFor('#ingredient-fields')
                    ->assertInputValue('#quantity', '250')
                    ->click('[data-target="quantity"].increment-button')
                    ->assertInputValue('#quantity', '260') // Should increment by 10 for grams
                    ->click('[data-target="quantity"].increment-button')
                    ->assertInputValue('#quantity', '270');
        });
    }

    /** @test */
    public function decrement_button_decreases_quantity_by_correct_amount_for_grams()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('food-logs.mobile-entry'))
                    ->click('#add-food-button')
                    ->waitFor('.food-list-item')
                    ->click('[data-name="Flour"]') // grams unit
                    ->waitFor('#ingredient-fields')
                    ->assertInputValue('#quantity', '250')
                    ->click('[data-target="quantity"].decrement-button')
                    ->assertInputValue('#quantity', '240') // Should decrement by 10 for grams
                    ->click('[data-target="quantity"].decrement-button')
                    ->assertInputValue('#quantity', '230');
        });
    }

    /** @test */
    public function increment_button_increases_quantity_by_correct_amount_for_kg()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('food-logs.mobile-entry'))
                    ->click('#add-food-button')
                    ->waitFor('.food-list-item')
                    ->click('[data-name="Chicken Breast"]') // kg unit
                    ->waitFor('#ingredient-fields')
                    ->assertInputValue('#quantity', '1.5')
                    ->click('[data-target="quantity"].increment-button')
                    ->assertInputValue('#quantity', '1.6') // Should increment by 0.1 for kg
                    ->click('[data-target="quantity"].increment-button')
                    ->assertInputValue('#quantity', '1.7');
        });
    }

    /** @test */
    public function increment_button_increases_quantity_by_correct_amount_for_pieces()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('food-logs.mobile-entry'))
                    ->click('#add-food-button')
                    ->waitFor('.food-list-item')
                    ->click('[data-name="Apples"]') // pieces unit
                    ->waitFor('#ingredient-fields')
                    ->assertInputValue('#quantity', '2')
                    ->click('[data-target="quantity"].increment-button')
                    ->assertInputValue('#quantity', '2.25') // Should increment by 0.25 for pieces
                    ->click('[data-target="quantity"].increment-button')
                    ->assertInputValue('#quantity', '2.5');
        });
    }

    /** @test */
    public function increment_button_increases_quantity_by_correct_amount_for_ml()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('food-logs.mobile-entry'))
                    ->click('#add-food-button')
                    ->waitFor('.food-list-item')
                    ->click('[data-name="Milk"]') // ml unit
                    ->waitFor('#ingredient-fields')
                    ->assertInputValue('#quantity', '500')
                    ->click('[data-target="quantity"].increment-button')
                    ->assertInputValue('#quantity', '510') // Should increment by 10 for ml
                    ->click('[data-target="quantity"].increment-button')
                    ->assertInputValue('#quantity', '520');
        });
    }

    /** @test */
    public function meal_portion_increments_by_quarter()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('food-logs.mobile-entry'))
                    ->click('#add-food-button')
                    ->waitFor('.food-list-item')
                    ->click('[data-name="Test Meal"]')
                    ->waitFor('#meal-fields')
                    ->assertInputValue('#portion', '1')
                    ->click('[data-target="portion"].increment-button')
                    ->assertInputValue('#portion', '1.25') // Should increment by 0.25 for portions
                    ->click('[data-target="portion"].increment-button')
                    ->assertInputValue('#portion', '1.5');
        });
    }

    /** @test */
    public function decrement_button_stops_at_zero()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('food-logs.mobile-entry'))
                    ->click('#add-food-button')
                    ->waitFor('.food-list-item')
                    ->click('[data-name="Apples"]') // pieces unit, base_quantity = 2
                    ->waitFor('#ingredient-fields')
                    ->assertInputValue('#quantity', '2')
                    // Decrement multiple times to test zero boundary
                    ->click('[data-target="quantity"].decrement-button') // 2 - 0.25 = 1.75
                    ->assertInputValue('#quantity', '1.75')
                    ->click('[data-target="quantity"].decrement-button') // 1.75 - 0.25 = 1.5
                    ->assertInputValue('#quantity', '1.5')
                    ->click('[data-target="quantity"].decrement-button') // 1.5 - 0.25 = 1.25
                    ->assertInputValue('#quantity', '1.25')
                    ->click('[data-target="quantity"].decrement-button') // 1.25 - 0.25 = 1
                    ->assertInputValue('#quantity', '1')
                    ->click('[data-target="quantity"].decrement-button') // 1 - 0.25 = 0.75
                    ->assertInputValue('#quantity', '0.75')
                    ->click('[data-target="quantity"].decrement-button') // 0.75 - 0.25 = 0.5
                    ->assertInputValue('#quantity', '0.5')
                    ->click('[data-target="quantity"].decrement-button') // 0.5 - 0.25 = 0.25
                    ->assertInputValue('#quantity', '0.25')
                    ->click('[data-target="quantity"].decrement-button') // 0.25 - 0.25 = 0
                    ->assertInputValue('#quantity', '0')
                    ->click('[data-target="quantity"].decrement-button') // Should stay at 0
                    ->assertInputValue('#quantity', '0');
        });
    }

    /** @test */
    public function unit_display_updates_when_ingredient_selected()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('food-logs.mobile-entry'))
                    ->click('#add-food-button')
                    ->waitFor('.food-list-item')
                    ->click('[data-name="Flour"]') // grams unit
                    ->waitFor('#ingredient-fields')
                    ->assertSeeIn('#ingredient-unit', 'grams');
        });
    }

    /** @test */
    public function selected_food_display_updates_correctly()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('food-logs.mobile-entry'))
                    ->click('#add-food-button')
                    ->waitFor('.food-list-item')
                    ->click('[data-name="Flour"]')
                    ->waitFor('#ingredient-fields')
                    ->assertSeeIn('#selected-food-name', 'Flour')
                    ->assertSeeIn('#selected-food-type-label', 'Ingredient');
        });
    }

    /** @test */
    public function cancel_button_hides_form_and_shows_add_button()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('food-logs.mobile-entry'))
                    ->click('#add-food-button')
                    ->waitFor('.food-list-item')
                    ->click('[data-name="Flour"]')
                    ->waitFor('#ingredient-fields')
                    ->assertVisible('#logging-form-container')
                    ->assertMissing('#add-food-button')
                    ->click('#cancel-logging')
                    ->waitUntilMissing('#logging-form-container')
                    ->assertVisible('#add-food-button');
        });
    }

    /** @test */
    public function form_validation_prevents_submission_with_invalid_data()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('food-logs.mobile-entry'))
                    ->click('#add-food-button')
                    ->waitFor('.food-list-item')
                    ->click('[data-name="Flour"]')
                    ->waitFor('#ingredient-fields')
                    ->clear('#quantity')
                    ->type('#quantity', '-5') // Invalid negative quantity
                    ->click('#submit-button')
                    ->waitFor('#validation-errors')
                    ->assertSeeIn('#validation-errors', 'Please enter a positive number.');
        });
    }

    /** @test */
    public function manual_input_validation_shows_error_for_negative_values()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('food-logs.mobile-entry'))
                    ->click('#add-food-button')
                    ->waitFor('.food-list-item')
                    ->click('[data-name="Flour"]')
                    ->waitFor('#ingredient-fields')
                    ->clear('#quantity')
                    ->type('#quantity', '-10')
                    ->assertAttribute('#quantity', 'class', 'large-input input-error');
        });
    }

    /** @test */
    public function input_blur_resets_invalid_values_to_default()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('food-logs.mobile-entry'))
                    ->click('#add-food-button')
                    ->waitFor('.food-list-item')
                    ->click('[data-name="Flour"]')
                    ->waitFor('#ingredient-fields')
                    ->clear('#quantity')
                    ->type('#quantity', '0') // Invalid zero quantity
                    ->click('#ingredient-notes') // Trigger blur
                    ->assertInputValue('#quantity', '1'); // Should reset to 1
        });
    }

    /** @test */
    public function floating_point_precision_is_handled_correctly()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('food-logs.mobile-entry'))
                    ->click('#add-food-button')
                    ->waitFor('.food-list-item')
                    ->click('[data-name="Apples"]') // pieces unit, increments by 0.25
                    ->waitFor('#ingredient-fields')
                    ->clear('#quantity')
                    ->type('#quantity', '0.1')
                    ->click('[data-target="quantity"].increment-button') // 0.1 + 0.25 = 0.35
                    ->assertInputValue('#quantity', '0.35')
                    ->click('[data-target="quantity"].increment-button') // 0.35 + 0.25 = 0.6
                    ->assertInputValue('#quantity', '0.6')
                    ->click('[data-target="quantity"].increment-button') // 0.6 + 0.25 = 0.85
                    ->assertInputValue('#quantity', '0.85');
        });
    }

    /** @test */
    public function ingredient_with_null_base_quantity_defaults_to_one()
    {
        // Create ingredient with null base_quantity
        $nullBaseIngredient = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => Unit::factory()->create(['name' => 'servings'])->id,
            'base_quantity' => null,
            'name' => 'Null Base Ingredient',
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('food-logs.mobile-entry'))
                    ->click('#add-food-button')
                    ->waitFor('.food-list-item')
                    ->click('[data-name="Null Base Ingredient"]')
                    ->waitFor('#ingredient-fields')
                    ->assertInputValue('#quantity', '1'); // Should default to 1 when base_quantity is null
        });
    }

    /** @test */
    public function ingredient_with_zero_base_quantity_uses_zero_as_default()
    {
        // Create ingredient with zero base_quantity
        $zeroBaseIngredient = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'base_unit_id' => Unit::factory()->create(['name' => 'servings'])->id,
            'base_quantity' => 0,
            'name' => 'Zero Base Ingredient',
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('food-logs.mobile-entry'))
                    ->click('#add-food-button')
                    ->waitFor('.food-list-item')
                    ->click('[data-name="Zero Base Ingredient"]')
                    ->waitFor('#ingredient-fields')
                    ->assertInputValue('#quantity', '0'); // Should use 0 when base_quantity is 0
        });
    }

    /** @test */
    public function successful_form_submission_redirects_and_shows_success_message()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('food-logs.mobile-entry'))
                    ->click('#add-food-button')
                    ->waitFor('.food-list-item')
                    ->click('[data-name="Flour"]')
                    ->waitFor('#ingredient-fields')
                    ->type('#ingredient-notes', 'Test note from browser')
                    ->click('#submit-button')
                    ->waitForLocation(route('food-logs.mobile-entry'))
                    ->assertSee('Ingredient logged successfully!')
                    ->assertSee('Test note from browser');
        });
    }

    /** @test */
    public function form_fields_toggle_correctly_between_ingredient_and_meal()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit(route('food-logs.mobile-entry'))
                    ->click('#add-food-button')
                    ->waitFor('.food-list-item')
                    // Select ingredient first
                    ->click('[data-name="Flour"]')
                    ->waitFor('#ingredient-fields')
                    ->assertVisible('#ingredient-fields')
                    ->assertMissing('#meal-fields')
                    ->click('#cancel-logging')
                    ->waitUntilMissing('#logging-form-container')
                    // Now select meal
                    ->click('#add-food-button')
                    ->waitFor('.food-list-item')
                    ->click('[data-name="Test Meal"]')
                    ->waitFor('#meal-fields')
                    ->assertVisible('#meal-fields')
                    ->assertMissing('#ingredient-fields');
        });
    }
}