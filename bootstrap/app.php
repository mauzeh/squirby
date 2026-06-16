<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Illuminate\Support\Facades\Route::prefix('api/sync')
                ->middleware('api')
                ->group(base_path('routes/sync.php'));
        }
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\IsAdmin::class,
            'device-id' => \App\Sync\Middleware\EnsureDeviceId::class,
            'log-sync-request' => \App\Sync\Middleware\LogSyncRequest::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\EnableQueryLogForAdmin::class,
            \App\Http\Middleware\LogActivity::class,
        ]);
    })
    ->withCommands([
        \App\Sync\Commands\PurgeSyncLogs::class,
        \App\Sync\Commands\ReplayFailedRequests::class,
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/sync/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->validator->errors()->first(),
                ], 422);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/sync/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage() ?: 'Unauthenticated.',
                ], 401);
            }
        });

        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/sync/*')) {
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                    $statusCode = $e->getStatusCode();
                    $message = $e->getMessage();
                    
                    if ($statusCode === 429) {
                        $headers = $e->getHeaders();
                        $seconds = $headers['Retry-After'] ?? 60;
                        return response()->json([
                            'status' => 'error',
                            'message' => "Too many requests. Try again in {$seconds} seconds.",
                        ], 429, $headers);
                    }
                    
                    if ($statusCode === 404) {
                        return response()->json([
                            'status' => 'error',
                            'message' => $message ?: 'Not found.',
                        ], 404);
                    }
                    
                    return response()->json([
                        'status' => 'error',
                        'message' => $message,
                    ], $statusCode, $e->getHeaders());
                }

                return response()->json([
                    'status' => 'error',
                    'message' => 'Internal server error',
                ], 500);
            }
        });
    })->create();
