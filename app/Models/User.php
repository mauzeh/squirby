<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'show_global_exercises',
        'show_extra_weight',
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
            'show_global_exercises' => 'boolean',
            'show_extra_weight' => 'boolean',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string $role): bool
    {
        // Use preloaded roles if available to avoid N+1 queries
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains('name', $role);
        }
        
        return $this->roles()->where('name', $role)->exists();
    }

    public function shouldShowGlobalExercises(): bool
    {
        return $this->show_global_exercises ?? true;
    }

    public function shouldShowExtraWeight(): bool
    {
        return $this->show_extra_weight ?? false;
    }

    public function exercises()
    {
        return $this->hasMany(Exercise::class);
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
}
