<?php

namespace App\Http\Middleware;

use App\Services\APIResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ThrottleRequests
{
    public function handle(Request $request, Closure $next, int $maxAttempts = 60, int $decayMinutes = 1, ?string $prefix = null): Response
    {
        $key = $this->resolveRequestSignature($request, $prefix);
        $attemptsKey = $key . ':attempts';
        $expiresKey = $key . ':expires';

        // Pega dados do cache
        $attempts = Cache::get($attemptsKey, 0);
        $expiresAt = Cache::get($expiresKey);

        // Se expirou, reseta
        if ($expiresAt && now()->timestamp > $expiresAt) {
            Cache::forget($attemptsKey);
            Cache::forget($expiresKey);
            $attempts = 0;
            $expiresAt = null;
        }

        // Se excedeu limite
        if ($attempts >= $maxAttempts) {
            $retryAfter = $expiresAt ? ($expiresAt - now()->timestamp) : ($decayMinutes * 60);
            return $this->buildResponse($maxAttempts, $retryAfter);
        }

        // Incrementa tentativas
        if (!$expiresAt) {
            $expiresAt = now()->addMinutes($decayMinutes)->timestamp;
            Cache::put($expiresKey, $expiresAt, now()->addMinutes($decayMinutes));
        }

        Cache::put($attemptsKey, $attempts + 1, now()->addMinutes($decayMinutes));

        $response = $next($request);

        return $this->addHeaders(
            $response,
            $maxAttempts,
            max(0, $maxAttempts - ($attempts + 1)),
            $expiresAt - now()->timestamp
        );
    }

    protected function resolveRequestSignature(Request $request, ?string $prefix): string
    {
        $prefix = $prefix ?? 'throttle';

        $signature = implode('|', [
            $request->ip(),
            $request->path(),
            $request->method(),
        ]);

        return $prefix . ':' . sha1($signature);
    }

    protected function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts, int $retryAfter): Response
    {
        $response->headers->add([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
            'X-RateLimit-Reset' => now()->addSeconds($retryAfter)->timestamp,
        ]);

        return $response;
    }

    protected function buildResponse(int $maxAttempts, int $retryAfter): Response
    {
        return APIResponse::error(
            __('messages.throttle.too_many_attempts', [
                'seconds' => $retryAfter,
                'minutes' => ceil($retryAfter / 60),
            ]),
            429,
            'TOO_MANY_REQUESTS',
            [],
            [
                'retry_after_seconds' => $retryAfter,
                'retry_after' => now()->addSeconds($retryAfter)->toIso8601String(),
            ]
        )->header('Retry-After', $retryAfter)
            ->header('X-RateLimit-Limit', $maxAttempts)
            ->header('X-RateLimit-Remaining', 0)
            ->header('X-RateLimit-Reset', now()->addSeconds($retryAfter)->timestamp);
    }
}