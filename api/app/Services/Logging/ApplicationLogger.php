<?php

namespace App\Services\Logging;

use App\Support\AuthContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Throwable;

class ApplicationLogger
{
    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    public static function exception(Throwable $e, array $context = []): void
    {
        self::write('error', $e->getMessage(), array_merge($context, [
            // O ecossistema PSR-3 e o tracker de exceções do Laravel em teste
            // esperam o Throwable bruto em `exception`.
            'exception' => $e,
            'exception_data' => [
                'class' => get_class($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ],
        ]));
    }

    protected static function write(string $level, string $message, array $context = []): void
    {
        $route = Request::route();

        $baseContext = [
            'request_id' => Request::header('X-Request-Id'),
            'user_id' => AuthContext::safeUserId(),
            'route' => $route?->uri(),
            'raw_path' => $route ? null : Request::path(),
            'method' => Request::method(),
            'ip' => Request::ip(),
            'env' => app()->environment(),
        ];

        Log::channel('application')->{$level}(
            $message,
            array_merge($baseContext, self::sanitize($context))
        );
    }

    protected static function sanitize(array $context): array
    {
        $sensitiveKeys = [
            'password',
            'password_confirmation',
            'token',
            'access_token',
            'refresh_token',
            'authorization',
            'webhook_secret',
            'client_secret',
        ];

        foreach ($sensitiveKeys as $key) {
            if (array_key_exists($key, $context)) {
                $context[$key] = '***';
            }
        }

        if (isset($context['exception_data']) && is_array($context['exception_data'])) {
            foreach ($sensitiveKeys as $key) {
                if (array_key_exists($key, $context['exception_data'])) {
                    $context['exception_data'][$key] = '***';
                }
            }
        }

        return $context;
    }
}
