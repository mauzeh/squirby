<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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
            ];

            foreach ($exercises as $exercise) {
                $user->exercises()->create($exercise);
            }

            $measurementTypes = [
                ['name' => 'Bodyweight', 'default_unit' => 'kg'],
                ['name' => 'Waist', 'default_unit' => 'cm'],
            ];

            foreach ($measurementTypes as $measurementType) {
                $user->measurementTypes()->create($measurementType);
            }
        });
    }

    public function measurementTypes()
    {
        return $this->hasMany(MeasurementType::class);
    }
}
