<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\User;
use App\Models\Unit;
use App\Services\SampleFoodDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SampleFoodDataServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SampleFoodDataService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SampleFoodDataService();
    }

    /** @test */
    public function it_creates_50_ingredients_and_5_meals()
    {
        $this->seedUnits();
        $user = User::factory()->create();

        $result = $this->service->createSampleData($user);

        $this->assertCount(50, $result['ingredients']);
        $this->assertCount(5, $result['meals']);
        
        // Verify ingredients are in database
        $this->assertEquals(50, $user->ingredients()->count());
        $this->assertEquals(5, $user->meals()->count());
    }

    /** @test */
    public function it_creates_ingredients_with_correct_nutritional_data()
    {
        $this->seedUnits();
        $user = User::factory()->create();

        $result = $this->service->createSampleData($user);

        $chickenBreast = $result['ingredients']->firstWhere('name', 'Chicken Breast');
        
        $this->assertNotNull($chickenBreast);
        $this->assertEquals(31, $chickenBreast->protein);
        $this->assertEquals(0, $chickenBreast->carbs);
        $this->assertEquals(3.6, $chickenBreast->fats);
        $this->assertEquals(100, $chickenBreast->base_quantity);
    }

    /** @test */
    public function it_creates_meals_with_attached_ingredients()
    {
        $this->seedUnits();
        $user = User::factory()->create();

        $result = $this->service->createSampleData($user);

        $proteinBowl = $result['meals']->firstWhere('name', 'Protein Power Bowl');
        
        $this->assertNotNull($proteinBowl);
        $this->assertGreaterThan(0, $proteinBowl->ingredients()->count());
        
        // Check specific ingredient is attached with correct quantity
        $chickenBreast = $proteinBowl->ingredients()->where('name', 'Chicken Breast')->first();
        $this->assertNotNull($chickenBreast);
        $this->assertEquals(150, $chickenBreast->pivot->quantity);
    }

    /** @test */
    public function it_throws_exception_when_units_are_missing()
    {
        $user = User::factory()->create();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Required units not found');

        $this->service->createSampleData($user);
    }

    /** @test */
    public function it_creates_data_only_for_specified_user()
    {
        $this->seedUnits();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->service->createSampleData($user1);

        $this->assertEquals(50, $user1->ingredients()->count());
        $this->assertEquals(5, $user1->meals()->count());
        $this->assertEquals(0, $user2->ingredients()->count());
        $this->assertEquals(0, $user2->meals()->count());
    }

    /**
     * Seed the required units for testing
     */
    protected function seedUnits(): void
    {
        Unit::create(['name' => 'Gram', 'abbreviation' => 'g', 'conversion_factor' => 1]);
        Unit::create(['name' => 'Cup', 'abbreviation' => 'cup', 'conversion_factor' => 236.588]);
        Unit::create(['name' => 'Tablespoon', 'abbreviation' => 'tbsp', 'conversion_factor' => 14.787]);
        Unit::create(['name' => 'Piece', 'abbreviation' => 'pc', 'conversion_factor' => 1]);
        Unit::create(['name' => 'Servings', 'abbreviation' => 'servings', 'conversion_factor' => 1]);
    }
}
