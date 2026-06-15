<?php

namespace App\Sync\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogSyncRequest
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            Log::channel('sync_requests')->info(json_encode([
                'ts' => now()->toIso8601String(),
                'ip' => $request->ip(),
                'method' => $request->method(),
                'path' => $request->path(),
                'query' => $request->query(),
                'headers' => $request->headers->all(),
                'body' => $request->all(),
            ]));
        } catch (\Throwable $e) {
            // Don't block the request
        }

        return $next($request);
    }
}
