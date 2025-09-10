<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    public function exercises()
    {
        return $this->hasMany(Exercise::class);
    }

    protected static function booted()
    {
        static::created(function ($user) {
            $exercises = [
                ['title' => 'Back Squat', 'description' => 'A compound exercise that targets the muscles of the legs and core.'],
                ['title' => 'Bench Press', 'description' => 'A compound exercise that targets the muscles of the upper body, including the chest, shoulders, and triceps.'],
                ['title' => 'Deadlift', 'description' => 'A compound exercise that targets the muscles of the back, legs, and grip.'],
                ['title' => 'Strict Press', 'description' => 'A compound exercise that targets the shoulders and triceps.'],
                ['title' => 'Power Clean', 'description' => 'An explosive deadlift.'],
                ['title' => 'Half-Kneeling DB Press', 'description' => 'A unilateral exercise that targets the shoulders and core.'],
                ['title' => 'Cyclist Squat (Barbell, Front Rack)', 'description' => 'A squat variation that emphasizes the quadriceps by elevating the heels, performed with a barbell in the front rack position.'],
                ['title' => 'Chin-Ups', 'description' => 'A bodyweight pulling exercise.', 'is_bodyweight' => true],
                ['title' => 'Zombie Squats', 'description' => 'Front-loaded squat variation focusing on core stability and upright posture.'],
                ['title' => 'Pendlay Rows', 'description' => 'Barbell row variation starting from the floor with strict form.'],
                ['title' => 'Romanian Deadlifts', 'description' => 'Hip-hinge movement targeting hamstrings and glutes.'],
                ['title' => 'Plank', 'description' => 'Isometric core exercise for stability and strength.', 'is_bodyweight' => true],
                ['title' => 'Overhead Press', 'description' => 'Standing shoulder press with barbell.'],
                ['title' => 'Lat Pulldowns', 'description' => 'Cable exercise targeting the latissimus dorsi.'],
                ['title' => 'Dumbbell Incline Press', 'description' => 'Upper chest focused pressing movement.'],
                ['title' => 'Face Pulls', 'description' => 'Cable exercise for rear deltoids and upper back health.'],
                ['title' => 'Bicep Curls', 'description' => 'Isolation exercise for bicep development.'],
                ['title' => 'Conventional Deadlift', 'description' => 'Hip-hinge movement lifting from the floor.'],
                ['title' => 'Glute-Ham Raises', 'description' => 'Posterior chain exercise targeting hamstrings and glutes.', 'is_bodyweight' => true],
                ['title' => 'Dumbbell Rows', 'description' => 'Unilateral rowing movement for back development.'],
                ['title' => 'Hanging Leg Raises', 'description' => 'Core exercise performed hanging from a bar.', 'is_bodyweight' => true],
            ];

            foreach ($exercises as $exercise) {
                $user->exercises()->create($exercise);
            }

            $measurementTypes = [
                ['name' => 'Bodyweight', 'default_unit' => 'lbs'],
                ['name' => 'Waist', 'default_unit' => 'cm'],
            ];

            foreach ($measurementTypes as $measurementType) {
                $user->measurementTypes()->create($measurementType);
            }

            $gramUnit = Unit::firstOrCreate(
                ['name' => 'Gram', 'abbreviation' => 'g'],
                ['conversion_factor' => 1]
            );

            $milliliterUnit = Unit::firstOrCreate(
                ['name' => 'Milliliter', 'abbreviation' => 'ml'],
                ['conversion_factor' => 1]
            );

            $pieceUnit = Unit::firstOrCreate(
                ['name' => 'Piece', 'abbreviation' => 'pc'],
                ['conversion_factor' => 1]
            );

            $ingredients = [
                [
                    'name' => 'Chicken Breast',
                    'base_quantity' => 100,
                    'protein' => 31,
                    'carbs' => 0,
                    'added_sugars' => 0,
                    'fats' => 3.6,
                    'sodium' => 0,
                    'iron' => 0,
                    'potassium' => 0,
                    'fiber' => 0,
                    'calcium' => 0,
                    'caffeine' => 0,
                    'base_unit_id' => $gramUnit->id,
                ],
                [
                    'name' => 'Rice (dry, brown)',
                    'base_quantity' => 45,
                    'protein' => 4,
                    'carbs' => 34,
                    'added_sugars' => 0,
                    'fats' => 1.5,
                    'sodium' => 0,
                    'iron' => 0,
                    'potassium' => 0,
                    'fiber' => 0,
                    'calcium' => 0,
                    'caffeine' => 0,
                    'base_unit_id' => $gramUnit->id,
                ],
                [
                    'name' => 'Broccoli (raw)',
                    'base_quantity' => 100,
                    'protein' => 2.8,
                    'carbs' => 6.6,
                    'added_sugars' => 0,
                    'fats' => 0.4,
                    'sodium' => 0,
                    'iron' => 0,
                    'potassium' => 0,
                    'fiber' => 0,
                    'calcium' => 0,
                    'caffeine' => 0,
                    'base_unit_id' => $gramUnit->id,
                ],
                [
                    'name' => 'Olive Oil',
                    'base_quantity' => 15,
                    'protein' => 0,
                    'carbs' => 0,
                    'added_sugars' => 0,
                    'fats' => 14,
                    'sodium' => 0,
                    'iron' => 0,
                    'potassium' => 0,
                    'fiber' => 0,
                    'calcium' => 0,
                    'caffeine' => 0,
                    'base_unit_id' => $milliliterUnit->id,
                ],
                [
                    'name' => 'Egg (whole, large)',
                    'base_quantity' => 1,
                    'protein' => 6,
                    'carbs' => 0.6,
                    'added_sugars' => 0,
                    'fats' => 5,
                    'sodium' => 0,
                    'iron' => 0,
                    'potassium' => 0,
                    'fiber' => 0,
                    'calcium' => 0,
                    'caffeine' => 0,
                    'base_unit_id' => $pieceUnit->id,
                ],
            ];

            foreach ($ingredients as $ingredient) {
                $user->ingredients()->create($ingredient);
            }

            // Create a sample meal
            $sampleMeal = $user->meals()->create([
                'name' => 'Chicken, Rice & Broccoli',
                'comments' => 'A balanced meal with protein, carbs, and vegetables.',
            ]);

            // Attach ingredients to the sample meal
            $chickenBreast = $user->ingredients()->where('name', 'Chicken Breast')->first();
            $rice = $user->ingredients()->where('name', 'Rice (dry, brown)')->first();
            $broccoli = $user->ingredients()->where('name', 'Broccoli (raw)')->first();
            $oliveOil = $user->ingredients()->where('name', 'Olive Oil')->first();

            if ($chickenBreast) {
                $sampleMeal->ingredients()->attach($chickenBreast->id, ['quantity' => 150]);
            }
            if ($rice) {
                $sampleMeal->ingredients()->attach($rice->id, ['quantity' => 100]);
            }
            if ($broccoli) {
                $sampleMeal->ingredients()->attach($broccoli->id, ['quantity' => 200]);
            }
            if ($oliveOil) {
                $sampleMeal->ingredients()->attach($oliveOil->id, ['quantity' => 10]);
            }
        });
    }

    public function measurementTypes()
    {
        return $this->hasMany(MeasurementType::class);
    }

    public function ingredients()
    {
        return $this->hasMany(Ingredient::class);
    }

    public function meals()
    {
        return $this->hasMany(Meal::class);
    }

    public function foodLogs()
    {
        return $this->hasMany(FoodLog::class);
    }

    public function bodyLogs()
    {
        return $this->hasMany(BodyLog::class);
    }

    public function liftLogs()
    {
        return $this->hasMany(LiftLog::class);
    }

    public function workoutPrograms()
    {
        return $this->hasMany(WorkoutProgram::class);
    }
}
