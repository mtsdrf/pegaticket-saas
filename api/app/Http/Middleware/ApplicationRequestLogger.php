<?php

namespace App\Http\Middleware;

use App\Services\Logging\ApplicationLogger;
use Closure;
use Illuminate\Http\Request;

class ApplicationRequestLogger
{
    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);

        $response = $next($request);

        $durationMs = round((microtime(true) - $start) * 1000, 2);
        $route = $request->route();

        ApplicationLogger::info('HTTP request handled', [
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'route' => $route?->uri(),
            'raw_path' => $route ? null : $request->path(),
        ]);

        return $response;
    }
}