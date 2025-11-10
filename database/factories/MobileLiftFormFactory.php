<?php

namespace Database\Factories;

use App\Models\MobileLiftForm;
use App\Models\User;
use App\Models\Exercise;
use Illuminate\Database\Eloquent\Factories\Factory;

class MobileLiftFormFactory extends Factory
{
    protected $model = MobileLiftForm::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'exercise_id' => Exercise::factory(),
            'date' => now()->toDateString(),
        ];
    }
}
