<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
            try {
                $gitLog = shell_exec('git log -n 5 --pretty=format:"%h - %s (%cr)"');
                View::share('gitLog', $gitLog);
            } catch (\Exception $e) {
                View::share('gitLog', 'Could not retrieve git log.');
            }
        }
    }
}
