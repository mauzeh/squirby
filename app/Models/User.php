<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'show_global_exercises', 'show_extra_weight', 'prefill_suggested_values', 'metrics_first_logging_flow'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

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
        'prefill_suggested_values',
        'metrics_first_logging_flow',
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
            'prefill_suggested_values' => 'boolean',
            'metrics_first_logging_flow' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($user) {
            // Soft delete all associated records when user is soft-deleted
            
            // Soft delete exercise aliases
            $user->exerciseAliases()->each(function ($alias) {
                $alias->delete();
            });
            
            // Soft delete user's exercises (not global exercises)
            $user->exercises()->each(function ($exercise) {
                $exercise->delete();
            });
            
            // Soft delete lift logs
            $user->liftLogs()->each(function ($liftLog) {
                $liftLog->delete();
            });
            
            // Soft delete other user-specific data
            $user->measurementTypes()->each(function ($measurementType) {
                $measurementType->delete();
            });
            
            $user->ingredients()->each(function ($ingredient) {
                $ingredient->delete();
            });
            
            $user->meals()->each(function ($meal) {
                $meal->delete();
            });
            
            $user->foodLogs()->each(function ($foodLog) {
                $foodLog->delete();
            });
            
            $user->bodyLogs()->each(function ($bodyLog) {
                $bodyLog->delete();
            });
        });
        
        static::restoring(function ($user) {
            // When restoring a user, also restore their associated data
            
            // Restore exercise aliases
            \App\Models\ExerciseAlias::onlyTrashed()
                ->where('user_id', $user->id)
                ->each(function ($alias) {
                    $alias->restore();
                });
            
            // Restore user's exercises
            \App\Models\Exercise::onlyTrashed()
                ->where('user_id', $user->id)
                ->each(function ($exercise) {
                    $exercise->restore();
                });
            
            // Restore lift logs
            \App\Models\LiftLog::onlyTrashed()
                ->where('user_id', $user->id)
                ->each(function ($liftLog) {
                    $liftLog->restore();
                });
            
            // Restore other user-specific data
            if (class_exists('\App\Models\MeasurementType')) {
                \App\Models\MeasurementType::onlyTrashed()
                    ->where('user_id', $user->id)
                    ->each(function ($measurementType) {
                        $measurementType->restore();
                    });
            }
            
            if (class_exists('\App\Models\Ingredient')) {
                \App\Models\Ingredient::onlyTrashed()
                    ->where('user_id', $user->id)
                    ->each(function ($ingredient) {
                        $ingredient->restore();
                    });
            }
            
            if (class_exists('\App\Models\Meal')) {
                \App\Models\Meal::onlyTrashed()
                    ->where('user_id', $user->id)
                    ->each(function ($meal) {
                        $meal->restore();
                    });
            }
            
            if (class_exists('\App\Models\FoodLog')) {
                \App\Models\FoodLog::onlyTrashed()
                    ->where('user_id', $user->id)
                    ->each(function ($foodLog) {
                        $foodLog->restore();
                    });
            }
            
            if (class_exists('\App\Models\BodyLog')) {
                \App\Models\BodyLog::onlyTrashed()
                    ->where('user_id', $user->id)
                    ->each(function ($bodyLog) {
                        $bodyLog->restore();
                    });
            }
        });
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

    public function shouldPrefillSuggestedValues(): bool
    {
        return $this->prefill_suggested_values ?? true;
    }



    public function shouldUseMetricsFirstLoggingFlow(): bool
    {
        return $this->metrics_first_logging_flow ?? false;
    }

    public function exercises()
    {
        return $this->hasMany(Exercise::class);
    }

    public function exerciseAliases()
    {
        return $this->hasMany(ExerciseAlias::class);
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
