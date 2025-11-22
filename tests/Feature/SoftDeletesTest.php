<?php

namespace Tests\Feature;

use App\Models\BodyLog;
use App\Models\Exercise;
use App\Models\FoodLog;
use App\Models\Ingredient;
use App\Models\LiftLog;
use App\Models\Meal;
use App\Models\MeasurementType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoftDeletesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_soft_deleted(): void
    {
        $user = User::factory()->create();
        $user->delete();
        $this->assertSoftDeleted($user);
    }

    public function test_exercise_can_be_soft_deleted(): void
    {
        $exercise = Exercise::factory()->create();
        $exercise->delete();
        $this->assertSoftDeleted($exercise);
    }

    public function test_lift_log_can_be_soft_deleted(): void
    {
        $liftLog = LiftLog::factory()->create();
        $liftLog->delete();
        $this->assertSoftDeleted($liftLog);
    }

    public function test_body_log_can_be_soft_deleted(): void
    {
        $bodyLog = BodyLog::factory()->create();
        $bodyLog->delete();
        $this->assertSoftDeleted($bodyLog);
    }

    public function test_food_log_can_be_soft_deleted(): void
    {
        $foodLog = FoodLog::factory()->create();
        $foodLog->delete();
        $this->assertSoftDeleted($foodLog);
    }

    public function test_meal_can_be_soft_deleted(): void
    {
        $meal = Meal::factory()->create();
        $meal->delete();
        $this->assertSoftDeleted($meal);
    }

    public function test_ingredient_can_be_soft_deleted(): void
    {
        $ingredient = Ingredient::factory()->create();
        $ingredient->delete();
        $this->assertSoftDeleted($ingredient);
    }

    public function test_measurement_type_can_be_soft_deleted(): void
    {
        $measurementType = MeasurementType::factory()->create();
        $measurementType->delete();
        $this->assertSoftDeleted($measurementType);
    }
}
