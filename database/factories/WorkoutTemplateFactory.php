<?php

namespace Database\Factories;

use App\Models\WorkoutTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkoutTemplateFactory extends Factory
{
    protected $model = WorkoutTemplate::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'is_public' => false,
            'tags' => [],
            'times_used' => 0,
        ];
    }
}
