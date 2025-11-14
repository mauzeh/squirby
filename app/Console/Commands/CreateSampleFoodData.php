<?php

namespace App\Console\Commands;

use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Console\Command;

class CreateSampleFoodData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'food:create-samples {user_id? : The user ID to create samples for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create 50 common ingredients and 5 sample meals for a user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('user_id');

        if (!$userId) {
            $users = User::withCount('ingredients')->get();
            
            if ($users->isEmpty()) {
                $this->error('No users found in the database.');
                return 1;
            }

            $this->info('Available users:');
            $this->table(
                ['ID', 'Name', 'Email', 'Existing Ingredients'],
                $users->map(fn ($user) => [$user->id, $user->name, $user->email, $user->ingredients_count])
            );

            $userId = $this->ask('Please enter the ID of the user to create samples for');
        }
        
        $user = User::find($userId);
        if (!$user) {
            $this->error("User with ID {$userId} not found.");
            return 1;
        }

        $this->info("Creating sample food data for {$user->name} ({$user->email})...");

        // Check if user already has ingredients
        if ($user->ingredients()->exists()) {
            if (!$this->confirm('User already has ingredients. Continue anyway?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        // Get common units
        $gramUnit = Unit::where('abbreviation', 'g')->first();
        $cupUnit = Unit::where('abbreviation', 'cup')->first();
        $tbspUnit = Unit::where('abbreviation', 'tbsp')->first();
        $pieceUnit = Unit::where('abbreviation', 'pc')->first();
        $servingUnit = Unit::where('abbreviation', 'servings')->first();

        if (!$gramUnit || !$cupUnit || !$tbspUnit || !$pieceUnit || !$servingUnit) {
            $this->error('Required units not found. Please run the UnitSeeder first.');
            return 1;
        }

        $this->info('Creating 50 common ingredients...');
        $ingredients = $this->createIngredients($user, $gramUnit, $cupUnit, $tbspUnit, $pieceUnit, $servingUnit);
        
        $this->info('Creating 5 sample meals...');
        $this->createSampleMeals($user, $ingredients);

        $this->info('✅ Sample food data created successfully!');
        $this->info("Created {$ingredients->count()} ingredients and 5 meals for {$user->name}");
        
        return 0;
    }

    private function createIngredients($user, $gramUnit, $cupUnit, $tbspUnit, $pieceUnit, $servingUnit)
    {
        $ingredientsData = [
            // Proteins
            ['name' => 'Chicken Breast', 'protein' => 31, 'carbs' => 0, 'fats' => 3.6, 'base_quantity' => 100, 'base_unit_id' => $gramUnit->id, 'cost_per_unit' => 0.08],
            ['name' => 'Ground Beef (85% lean)', 'protein' => 25, 'carbs' => 0, 'fats' => 15, 'base_quantity' => 100, 'base_unit_id' => $gramUnit->id, 'cost_per_unit' => 0.10],
            ['name' => 'Salmon Fillet', 'protein' => 25, 'carbs' => 0, 'fats' => 14, 'base_quantity' => 100, 'base_unit_id' => $gramUnit->id, 'cost_per_unit' => 0.15],
            ['name' => 'Eggs', 'protein' => 6, 'carbs' => 0.6, 'fats' => 5, 'base_quantity' => 1, 'base_unit_id' => $pieceUnit->id, 'cost_per_unit' => 0.25],
            ['name' => 'Greek Yogurt (Plain)', 'protein' => 10, 'carbs' => 4, 'fats' => 0, 'base_quantity' => 100, 'base_unit_id' => $gramUnit->id, 'cost_per_unit' => 0.06],
            ['name' => 'Tuna (Canned in Water)', 'protein' => 25, 'carbs' => 0, 'fats' => 1, 'base_quantity' => 100, 'base_unit_id' => $gramUnit->id, 'cost_per_unit' => 0.12],
            ['name' => 'Tofu (Firm)', 'protein' => 8, 'carbs' => 2, 'fats' => 4, 'base_quantity' => 100, 'base_unit_id' => $gramUnit->id, 'cost_per_unit' => 0.04],
            ['name' => 'Black Beans', 'protein' => 9, 'carbs' => 23, 'fats' => 0.5, 'fiber' => 9, 'base_quantity' => 100, 'base_unit_id' => $gramUnit->id, 'cost_per_unit' => 0.03],
            
            // Carbohydrates
            ['name' => 'Brown Rice (Cooked)', 'protein' => 2.6, 'carbs' => 23, 'fats' => 0.9, 'fiber' => 1.8, 'base_quantity' => 100, 'base_unit_id' => $gramUnit->id, 'cost_per_unit' => 0.02],
            ['name' => 'White Rice (Cooked)', 'protein' => 2.7, 'carbs' => 28, 'fats' => 0.3, 'base_quantity' => 100, 'base_unit_id' => $gramUnit->id, 'cost_per_unit' => 0.015],
            ['name' => 'Quinoa (Cooked)', 'protein' => 4.4, 'carbs' => 22, 'fats' => 1.9, 'fiber' => 2.8, 'base_quantity' => 100, 'base_unit_id' => $gramUnit->id, 'cost_per_unit' => 0.05],
            ['name' => 'Oats (Rolled)', 'protein' => 17, 'carbs' => 66, 'fats' => 7, 'fiber' => 11, 'base_quantity' => 100, 'base_unit_id' => $gramUnit->id, 'cost_per_unit' => 0.03],
            ['name' => 'Whole Wheat Bread', 'protein' => 9, 'carbs' => 43, 'fats' => 4, 'fiber' => 6, 'base_quantity' => 1, 'base_unit_id' => $pieceUnit->id, 'cost_per_unit' => 0.15],
            ['name' => 'Sweet Potato', 'protein' => 2, 'carbs' => 20, 'fats' => 0.1, 'fiber' => 3, 'base_quantity' => 100, 'base_unit_id' => $gramUnit->id, 'cost_per_unit' => 0.02],
            ['name' => 'Banana', 'protein' => 1.1, 'carbs' => 23, 'fats' => 0.3, 'fiber' => 2.6, 'potassium' => 358, 'base_quantity' => 1, 'base_unit_id' => $pieceUnit->id, 'cost_per_unit' => 0.30],
            
            // Vegetables
            ['name' => 'Broccoli', 'protein' => 2.8, 'carbs' => 7, 'fats' => 0.4, 'fiber' => 2.6, 'base_quantity' => 100, 'base_unit_id' => $gramUnit->id, 'cost_per_unit' => 0.025],
            ['name' => 'Spinach', 'protein' => 2.9, 'carbs' => 3.6, 'fats' => 0.4, 'fiber' => 2.2, 'iron' => 2.7, 'base_quantity' => 100, 'base_unit_id' => $gramUnit->id, 'cost_per_unit' => 0.03],
            ['name' => 'Bell Pepper (Red)', 'protein' => 1, 'carbs' => 7, 'fats' => 0.3, 'fiber' => 2.5, 'base_quantity' => 100, 'base_unit_id' => $gramUnit->id, 'cost_per_unit' => 0.04],
            ['name' => 'Carrots', 'protein' => 0.9, 'carbs' => 10, 'fats' => 0.2, 'fiber' => 2.8, 'base_quantity' => 100, 'base_unit_id' => $gramUnit->id, 'cost_per_unit' => 0.02],
            ['name' => 'Tomatoes', 'protein' => 0.9, 'carbs' => 3.9, 'fats' => 0.2, 'fiber' => 1.2, 'base_quantity' => 100, 'base_unit_id' => $gramUnit->id, 'cost_per_unit' => 0.03],
            ['name' => 'Cucumber', 'protein' => 0.7, 'carbs' => 3.6, 'fats' => 0.1, 'fiber' => 0.5, 'base_quantity' => 100, 'base_unit_id' => $gramUnit->id, 'cost_per_unit' => 0.015],
            ['name' => 'Onion', 'protein' => 1.1, 'carbs' => 9.3, 'fats' => 0.1, 'fiber' => 1.7, 'base_quantity' => 100, 'base_unit_id' => $gramUnit->id, 'cost_per_unit' => 0.02],
            ['name' => 'Garlic', 'protein' => 6.4, 'carbs' => 33, 'fats' => 0.5, 'fiber' => 2.1, 'base_quantity' => 1, 'base_unit_id' => $pieceUnit->id, 'cost_per_unit' => 0.05],
            
            // Fruits
            ['name' => 'Apple', 'protein' => 0.3, 'carbs' => 14, 'fats' => 0.2, 'fiber' => 2.4, 'base_quantity' => 1, 'base_unit_id' => $pieceUnit->id, 'cost_per_unit' => 0.50],
            ['name' => 'Orange', 'protein' => 0.9, 'carbs' => 12, 'fats' => 0.1, 'fiber' => 2.4, 'base_quantity' => 1, 'base_unit_id' => $pieceUnit->id, 'cost_per_unit' => 0.40],
            ['name' => 'Strawberries', 'protein' => 0.7, 'carbs' => 8, 'fats' => 0.3, 'fiber' => 2, 'base_quantity' => 100, 'base_unit_id' => $gramUnit->id, 'cost_per_unit' => 0.06],
            ['name' => 'Blueberries', 'protein' => 0.7, 'carbs' => 14, 'fats' => 0.3, 'fiber' => 2.4, 'base_quantity' => 100, 'base_unit_id' => $gramUnit->id, 'cost_per_unit' => 0.08],
            ['name' => 'Avocado', 'protein' => 2, 'carbs' => 9, 'fats' => 15, 'fiber' => 7, 'potassium' => 485, 'base_quantity' => 1, 'base_unit_id' => $pieceUnit->id, 'cost_per_unit' => 1.50],
            
            // Fats and Oils
            ['name' => 'Olive Oil', 'protein' => 0, 'carbs' => 0, 'fats' => 14, 'base_quantity' => 1, 'base_unit_id' => $tbspUnit->id, 'cost_per_unit' => 0.20],
            ['name' => 'Butter', 'protein' => 0.1, 'carbs' => 0, 'fats' => 11, 'base_quantity' => 1, 'base_unit_id' => $tbspUnit->id, 'cost_per_unit' => 0.15],
            ['name' => 'Almonds', 'protein' => 21, 'carbs' => 22, 'fats' => 50, 'fiber' => 12, 'base_quantity' => 100, 'base_unit_id' => $gramUnit->id, 'cost_per_unit' => 0.12],
            ['name' => 'Walnuts', 'protein' => 15, 'carbs' => 14, 'fats' => 65, 'fiber' => 7, 'base_quantity' => 100, 'base_unit_id' => $gramUnit->id, 'cost_per_unit' => 0.15],
            ['name' => 'Peanut Butter', 'protein' => 25, 'carbs' => 20, 'fats' => 50, 'fiber' => 6, 'base_quantity' => 2, 'base_unit_id' => $tbspUnit->id, 'cost_per_unit' => 0.25],
            
            // Dairy
            ['name' => 'Milk (2%)', 'protein' => 3.4, 'carbs' => 5, 'fats' => 2, 'calcium' => 125, 'base_quantity' => 1, 'base_unit_id' => $cupUnit->id, 'cost_per_unit' => 0.60],
            ['name' => 'Cheddar Cheese', 'protein' => 25, 'carbs' => 1, 'fats' => 33, 'calcium' => 721, 'base_quantity' => 100, 'base_unit_id' => $gramUnit->id, 'cost_per_unit' => 0.08],
            ['name' => 'Cottage Cheese', 'protein' => 11, 'carbs' => 3.4, 'fats' => 4.3, 'base_quantity' => 100, 'base_unit_id' => $gramUnit->id, 'cost_per_unit' => 0.05],
            
            // Grains and Legumes
            ['name' => 'Lentils (Cooked)', 'protein' => 9, 'carbs' => 20, 'fats' => 0.4, 'fiber' => 8, 'iron' => 3.3, 'base_quantity' => 100, 'base_unit_id' => $gramUnit->id, 'cost_per_unit' => 0.025],
            ['name' => 'Chickpeas (Cooked)', 'protein' => 8, 'carbs' => 27, 'fats' => 2.6, 'fiber' => 8, 'base_quantity' => 100, 'base_unit_id' => $gramUnit->id, 'cost_per_unit' => 0.03],
            ['name' => 'Pasta (Whole Wheat)', 'protein' => 5, 'carbs' => 25, 'fats' => 1.1, 'fiber' => 4, 'base_quantity' => 100, 'base_unit_id' => $gramUnit->id, 'cost_per_unit' => 0.02],
            
            // Seasonings and Condiments
            ['name' => 'Salt', 'protein' => 0, 'carbs' => 0, 'fats' => 0, 'sodium' => 38758, 'base_quantity' => 1, 'base_unit_id' => $tbspUnit->id, 'cost_per_unit' => 0.01],
            ['name' => 'Black Pepper', 'protein' => 10.4, 'carbs' => 64, 'fats' => 3.3, 'base_quantity' => 1, 'base_unit_id' => $tbspUnit->id, 'cost_per_unit' => 0.05],
            ['name' => 'Lemon Juice', 'protein' => 0.4, 'carbs' => 2.5, 'fats' => 0.2, 'base_quantity' => 1, 'base_unit_id' => $tbspUnit->id, 'cost_per_unit' => 0.03],
            
            // Beverages
            ['name' => 'Coffee (Black)', 'protein' => 0.3, 'carbs' => 0, 'fats' => 0, 'caffeine' => 95, 'base_quantity' => 1, 'base_unit_id' => $cupUnit->id, 'cost_per_unit' => 0.15],
            ['name' => 'Green Tea', 'protein' => 0, 'carbs' => 0, 'fats' => 0, 'caffeine' => 25, 'base_quantity' => 1, 'base_unit_id' => $cupUnit->id, 'cost_per_unit' => 0.10],
            
            // Snacks
            ['name' => 'Dark Chocolate (70%)', 'protein' => 8, 'carbs' => 46, 'fats' => 43, 'fiber' => 11, 'caffeine' => 80, 'base_quantity' => 100, 'base_unit_id' => $gramUnit->id, 'cost_per_unit' => 0.10],
            ['name' => 'Popcorn (Air-popped)', 'protein' => 12, 'carbs' => 78, 'fats' => 4, 'fiber' => 15, 'base_quantity' => 100, 'base_unit_id' => $gramUnit->id, 'cost_per_unit' => 0.04],
            
            // Additional Common Items
            ['name' => 'Honey', 'protein' => 0.3, 'carbs' => 17, 'fats' => 0, 'added_sugars' => 17, 'base_quantity' => 1, 'base_unit_id' => $tbspUnit->id, 'cost_per_unit' => 0.12],
            ['name' => 'Coconut Oil', 'protein' => 0, 'carbs' => 0, 'fats' => 14, 'base_quantity' => 1, 'base_unit_id' => $tbspUnit->id, 'cost_per_unit' => 0.18],
            ['name' => 'Chia Seeds', 'protein' => 17, 'carbs' => 42, 'fats' => 31, 'fiber' => 34, 'base_quantity' => 100, 'base_unit_id' => $gramUnit->id, 'cost_per_unit' => 0.20],
            ['name' => 'Greek Feta Cheese', 'protein' => 14, 'carbs' => 4, 'fats' => 21, 'sodium' => 1116, 'base_quantity' => 100, 'base_unit_id' => $gramUnit->id, 'cost_per_unit' => 0.09],
        ];

        $createdIngredients = collect();
        
        foreach ($ingredientsData as $data) {
            $data['user_id'] = $user->id;
            $ingredient = Ingredient::create($data);
            $createdIngredients->push($ingredient);
            $this->line("Created: {$ingredient->name}");
        }

        return $createdIngredients;
    }

    private function createSampleMeals($user, $ingredients)
    {
        $mealsData = [
            [
                'name' => 'Protein Power Bowl',
                'comments' => 'High-protein meal with chicken, quinoa, and vegetables',
                'ingredients' => [
                    'Chicken Breast' => 150,
                    'Quinoa (Cooked)' => 100,
                    'Broccoli' => 80,
                    'Olive Oil' => 1,
                ]
            ],
            [
                'name' => 'Mediterranean Breakfast',
                'comments' => 'Greek yogurt with nuts and honey',
                'ingredients' => [
                    'Greek Yogurt (Plain)' => 200,
                    'Almonds' => 30,
                    'Honey' => 1,
                    'Blueberries' => 50,
                ]
            ],
            [
                'name' => 'Veggie Stir Fry',
                'comments' => 'Colorful vegetable stir fry with tofu',
                'ingredients' => [
                    'Tofu (Firm)' => 120,
                    'Bell Pepper (Red)' => 100,
                    'Broccoli' => 100,
                    'Carrots' => 80,
                    'Brown Rice (Cooked)' => 150,
                    'Olive Oil' => 1,
                    'Garlic' => 2,
                ]
            ],
            [
                'name' => 'Salmon & Sweet Potato',
                'comments' => 'Baked salmon with roasted sweet potato and spinach',
                'ingredients' => [
                    'Salmon Fillet' => 140,
                    'Sweet Potato' => 200,
                    'Spinach' => 100,
                    'Olive Oil' => 1,
                    'Lemon Juice' => 1,
                ]
            ],
            [
                'name' => 'Hearty Breakfast',
                'comments' => 'Scrambled eggs with avocado toast',
                'ingredients' => [
                    'Eggs' => 2,
                    'Whole Wheat Bread' => 2,
                    'Avocado' => 0.5,
                    'Butter' => 1,
                    'Tomatoes' => 50,
                ]
            ],
        ];

        foreach ($mealsData as $mealData) {
            $meal = Meal::create([
                'name' => $mealData['name'],
                'comments' => $mealData['comments'],
                'user_id' => $user->id,
            ]);

            foreach ($mealData['ingredients'] as $ingredientName => $quantity) {
                $ingredient = $ingredients->firstWhere('name', $ingredientName);
                if ($ingredient) {
                    $meal->ingredients()->attach($ingredient->id, ['quantity' => $quantity]);
                }
            }

            $this->line("Created meal: {$meal->name}");
        }
    }
}