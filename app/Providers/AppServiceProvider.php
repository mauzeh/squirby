<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use App\Models\MeasurementType;
use Illuminate\Support\Facades\Auth;
use App\Http\View\Composers\ExerciseAliasComposer;

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
        // Enable query log for non-production environments
        if (config('app.env') !== 'production') {
            \Illuminate\Support\Facades\DB::enableQueryLog();
        }
        
        // Only show git log in development environment, not in testing
        if (config('app.env') === 'local') {
            try {
                $gitBranch = trim(shell_exec('git rev-parse --abbrev-ref HEAD'));
                $gitLog = shell_exec('git log -n 25 --pretty=format:"%h - %s (%cr)"');
                View::share('gitBranch', $gitBranch);
                View::share('gitLog', $gitLog);
            } catch (\Exception $e) {
                View::share('gitBranch', 'unknown');
                View::share('gitLog', 'Could not retrieve git log.');
            }
        }

        // Register view composer for exercise alias display
        View::composer([
            'exercises.index',
            'exercises.logs',
            'lift-logs.*',
            'programs.*',
            'mobile-entry.*',
            'components.top-exercises-buttons',
            'components.lift-log-form',
            'components.exercise-form',
        ], ExerciseAliasComposer::class);

        View::composer('app', function ($view) {
            if (Auth::check()) {
                $measurementTypes = MeasurementType::where('user_id', auth()->id())->orderBy('name')->get();
                $view->with('measurementTypes', $measurementTypes);
            }
            
            // Show database info in non-production environments OR for admin users
            $isAdmin = Auth::check() && Auth::user()->hasRole('Admin');
            if (config('app.env') !== 'production' || $isAdmin) {
                $queryCount = count(\Illuminate\Support\Facades\DB::getQueryLog());
                $dbConnection = config('database.default');
                $dbDriver = config("database.connections.{$dbConnection}.driver");
                
                $view->with('queryCount', $queryCount);
                $view->with('dbConnection', $dbConnection);
                $view->with('dbDriver', $dbDriver);
                $view->with('showDebugInfo', true);
            }
        });
    }
}
