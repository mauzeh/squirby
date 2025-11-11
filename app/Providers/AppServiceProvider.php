<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use App\Models\MeasurementType;
use Illuminate\Support\Facades\Auth;
use App\Http\View\Composers\ExerciseAliasComposer;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Store logs captured during the request
     */
    protected static $capturedLogs = [];

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
        // 2025-11-09 Temporarily enabled across all environments to troubleshoot discrepancy
        // in recommendations between local and product for 1 user.
        //if (config('app.env') !== 'production') {
            \Illuminate\Support\Facades\DB::enableQueryLog();
        //}

        // Capture logs for admin users and when impersonating
        Log::listen(function ($log) {
            if (Auth::check() && (Auth::user()->hasRole('Admin') || session()->has('impersonator_id'))) {
                self::$capturedLogs[] = [
                    'level' => $log->level,
                    'message' => $log->message,
                    'context' => $log->context,
                ];
            }
        });

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
                // Provide menu service to the view
                $menuService = app(\App\Services\MenuService::class);
                $view->with('menuService', $menuService);
            }
            
            // Show database info and git log for admin users or when impersonating
            $isAdmin = Auth::check() && Auth::user()->hasRole('Admin');
            if ($isAdmin || session()->has('impersonator_id')) {
                // Git log
                try {
                    $gitBranch = trim(shell_exec('git rev-parse --abbrev-ref HEAD'));
                    $gitLog = shell_exec('git log -n 25 --pretty=format:"%h - %s (%cr)"');
                    $view->with('gitBranch', $gitBranch);
                    $view->with('gitLog', $gitLog);
                } catch (\Exception $e) {
                    $view->with('gitBranch', 'unknown');
                    $view->with('gitLog', 'Could not retrieve git log.');
                }
                
                // Query log
                $queries = \Illuminate\Support\Facades\DB::getQueryLog();
                $queryCount = count($queries);
                $dbConnection = config('database.default');
                $dbDriver = config("database.connections.{$dbConnection}.driver");
                
                $view->with('queryCount', $queryCount);
                $view->with('queries', $queries);
                $view->with('dbConnection', $dbConnection);
                $view->with('dbDriver', $dbDriver);
                $view->with('showDebugInfo', true);
                
                // Pass captured logs to view
                $view->with('logs', self::$capturedLogs);
            }
        });
    }
}
