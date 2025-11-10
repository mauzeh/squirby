<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\MobileLiftForm;
use App\Models\User;
use App\Models\Exercise;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MobileLiftFormTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_belongs_to_a_user()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create();
        $form = MobileLiftForm::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
        ]);

        $this->assertInstanceOf(User::class, $form->user);
        $this->assertEquals($user->id, $form->user->id);
    }

    /** @test */
    public function it_belongs_to_an_exercise()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['title' => 'Bench Press']);
        $form = MobileLiftForm::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
        ]);

        $this->assertInstanceOf(Exercise::class, $form->exercise);
        $this->assertEquals($exercise->id, $form->exercise->id);
        $this->assertEquals('Bench Press', $form->exercise->title);
    }

    /** @test */
    public function scope_for_user_and_date_filters_by_user_and_date()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $exercise = Exercise::factory()->create();
        
        $targetDate = Carbon::parse('2024-01-15');
        $otherDate = Carbon::parse('2024-01-16');
        
        // Create forms for different users and dates
        $targetForm = MobileLiftForm::factory()->create([
            'user_id' => $user1->id,
            'exercise_id' => $exercise->id,
            'date' => $targetDate,
        ]);
        
        MobileLiftForm::factory()->create([
            'user_id' => $user2->id,
            'exercise_id' => $exercise->id,
            'date' => $targetDate,
        ]);
        
        MobileLiftForm::factory()->create([
            'user_id' => $user1->id,
            'exercise_id' => $exercise->id,
            'date' => $otherDate,
        ]);
        
        $results = MobileLiftForm::forUserAndDate($user1->id, $targetDate)->get();
        
        $this->assertCount(1, $results);
        $this->assertEquals($targetForm->id, $results->first()->id);
    }

    /** @test */
    public function scope_for_user_and_date_returns_empty_when_no_matches()
    {
        $user = User::factory()->create();
        $date = Carbon::parse('2024-01-15');
        
        $results = MobileLiftForm::forUserAndDate($user->id, $date)->get();
        
        $this->assertCount(0, $results);
    }

    /** @test */
    public function date_field_is_cast_to_date()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create();
        $form = MobileLiftForm::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => '2024-01-15',
        ]);

        $this->assertInstanceOf(Carbon::class, $form->date);
        $this->assertEquals('2024-01-15', $form->date->toDateString());
    }

    /** @test */
    public function fillable_fields_can_be_mass_assigned()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create();
        
        $form = MobileLiftForm::create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => '2024-01-15',
        ]);

        $this->assertDatabaseHas('mobile_lift_forms', [
            'id' => $form->id,
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
        ]);
        
        $this->assertEquals('2024-01-15', $form->date->toDateString());
    }

    /** @test */
    public function unique_constraint_prevents_duplicate_forms()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create();
        $date = '2024-01-15';
        
        MobileLiftForm::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => $date,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        
        MobileLiftForm::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => $date,
        ]);
    }

    /** @test */
    public function same_exercise_can_be_added_for_different_dates()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create();
        
        $form1 = MobileLiftForm::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => '2024-01-15',
        ]);
        
        $form2 = MobileLiftForm::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => '2024-01-16',
        ]);

        $this->assertNotEquals($form1->id, $form2->id);
        $this->assertDatabaseCount('mobile_lift_forms', 2);
    }

    /** @test */
    public function same_exercise_can_be_added_for_different_users()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $exercise = Exercise::factory()->create();
        $date = '2024-01-15';
        
        $form1 = MobileLiftForm::factory()->create([
            'user_id' => $user1->id,
            'exercise_id' => $exercise->id,
            'date' => $date,
        ]);
        
        $form2 = MobileLiftForm::factory()->create([
            'user_id' => $user2->id,
            'exercise_id' => $exercise->id,
            'date' => $date,
        ]);

        $this->assertNotEquals($form1->id, $form2->id);
        $this->assertDatabaseCount('mobile_lift_forms', 2);
    }

    /** @test */
    public function form_is_deleted_when_user_is_deleted()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create();
        $form = MobileLiftForm::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
        ]);

        $this->assertDatabaseHas('mobile_lift_forms', ['id' => $form->id]);
        
        $user->delete();
        
        $this->assertDatabaseMissing('mobile_lift_forms', ['id' => $form->id]);
    }

    /** @test */
    public function form_is_deleted_when_exercise_is_deleted()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create();
        $form = MobileLiftForm::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
        ]);

        $this->assertDatabaseHas('mobile_lift_forms', ['id' => $form->id]);
        
        $exercise->delete();
        
        $this->assertDatabaseMissing('mobile_lift_forms', ['id' => $form->id]);
    }
}
