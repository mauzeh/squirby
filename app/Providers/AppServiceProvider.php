<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use App\Models\MeasurementType;
use Illuminate\Support\Facades\Auth;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') !== 'production') {
            \Illuminate\Support\Facades\DB::enableQueryLog();
        }
        
        // Only show git log in development environment, not in testing
        if (config('app.env') === 'local') {
            try {
                $gitLog = shell_exec('git log -n 25 --pretty=format:"%h - %s (%cr)"');
                View::share('gitLog', $gitLog);
            } catch (\Exception $e) {
                View::share('gitLog', 'Could not retrieve git log.');
            }
        }

        View::composer('app', function ($view) {
            if (Auth::check()) {
                $measurementTypes = MeasurementType::where('user_id', auth()->id())->orderBy('name')->get();
                $view->with('measurementTypes', $measurementTypes);
            }
            if (config('app.env') !== 'production') {
                $view->with('queryCount', count(\Illuminate\Support\Facades\DB::getQueryLog()));
            }
        });
    }
}
