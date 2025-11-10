<?php

namespace App\Providers;

use App\Models\Exercise;
use App\Models\WorkoutTemplate;
use App\Policies\ExercisePolicy;
use App\Policies\WorkoutTemplatePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Exercise::class => ExercisePolicy::class,
        WorkoutTemplate::class => WorkoutTemplatePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}