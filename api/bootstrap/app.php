<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenBlacklistedException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use App\Services\Logging\ApplicationLogger;

return Application::configure(basePath: dirname(__DIR__))
    // Application::configure() habilita auto-discovery de eventos por padrão
    // (->withEvents(), registrando Illuminate\Foundation\Support\Providers\
    // EventServiceProvider "cru" além do nosso App\Providers\
    // EventServiceProvider). Esse provider cru tem shouldDiscoverEvents()
    // sempre true (checa get_class($this) === self::class, que bate na
    // instância exata da classe base) e escaneia app/Listeners inteiro,
    // registrando de novo (formato "Classe@handle") TODO listener já
    // registrado manualmente no nosso EventServiceProvider — cada evento do
    // projeto disparava cada listener 2x (achado ao escrever teste com
    // Mockery ->once() para o Web Push, roadmap Delivery Fase 4: a
    // asserção "called exactly 1 times but called 2 times" expôs um bug
    // sistêmico que já afetava TODO Event/Listener do projeto, inclusive
    // toda auditoria — só não quebrava testes existentes porque nenhum
    // usava assertDatabaseCount/expectativa de chamada única). Desabilitar
    // a descoberta automática mantém só o nosso $listen manual ativo.
    ->withEvents(discover: false)
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Globais (todas as requisições)
        $middleware->append([
            \Illuminate\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\ApiHardening::class,
            \App\Http\Middleware\RequestId::class,
            \App\Http\Middleware\ApplicationRequestLogger::class,
            \App\Http\Middleware\SetLocale::class,
        ]);

        // Aliases (para usar em rotas)
        $middleware->alias([
            'jwt' => \App\Http\Middleware\JwtAccessMiddleware::class,
            'perm' => \App\Http\Middleware\CheckPermission::class,
            'throttle' => \App\Http\Middleware\ThrottleRequests::class,
            'tenant' => \App\Http\Middleware\ResolveTenant::class,
            'tenant.owner' => \App\Http\Middleware\EnsureTenantOwner::class,
            'customer.jwt' => \App\Http\Middleware\CustomerJwtAccessMiddleware::class,
            'customer.jwt.optional' => \App\Http\Middleware\OptionalCustomerJwtMiddleware::class,
            'accounting.jwt' => \App\Http\Middleware\AccountingJwtAccessMiddleware::class,
            'accounting.tenant' => \App\Http\Middleware\ResolveAccountingTenant::class,
            'accounting.scope' => \App\Http\Middleware\EnsureAccountingScope::class,
            'api.key' => \App\Http\Middleware\ApiKeyAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // JWT Exceptions (esperadas)
        $exceptions->render(function (TokenBlacklistedException $e, Request $request) {
            ApplicationLogger::warning('JWT token blacklisted');

            return \App\Services\APIResponse::error(
                __('messages.auth.token_blacklisted'),
                401,
                'TOKEN_BLACKLISTED'
            );
        });

        $exceptions->render(function (TokenExpiredException $e, Request $request) {
            ApplicationLogger::warning('JWT token expired');

            return \App\Services\APIResponse::error(
                __('messages.auth.token_expired'),
                401,
                'TOKEN_EXPIRED'
            );
        });

        $exceptions->render(function (TokenInvalidException $e, Request $request) {
            ApplicationLogger::warning('JWT token invalid');

            return \App\Services\APIResponse::error(
                __('messages.auth.token_invalid'),
                401,
                'TOKEN_INVALID'
            );
        });

        $exceptions->render(function (JWTException $e, Request $request) {
            ApplicationLogger::warning('JWT unauthenticated', [
                'exception' => $e->getMessage(),
            ]);

            return \App\Services\APIResponse::error(
                __('messages.auth.unauthenticated'),
                401,
                'UNAUTHENTICATED'
            );
        });

        // Validation Exception (esperada)
        $exceptions->render(function (ValidationException $e, Request $request) {
            ApplicationLogger::warning('Validation failed', [
                'errors' => $e->errors(),
            ]);

            return \App\Services\APIResponse::error(
                __('messages.validation.failed'),
                422,
                'VALIDATION_ERROR',
                $e->errors()
            );
        });

        // Authentication Exception
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            ApplicationLogger::warning('Authentication failed');

            return \App\Services\APIResponse::error(
                __('messages.auth.unauthenticated'),
                401,
                'UNAUTHENTICATED'
            );
        });

        // Model not found (ex.: recurso de outro tenant resolvido por uuid
        // interno e forçado via URL). Sem este bloco, alguns fluxos acabam
        // caindo no handler genérico e virando 500 indevido.
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            ApplicationLogger::warning('Model not found');

            return \App\Services\APIResponse::error(
                __('messages.http.not_found'),
                404,
                'NOT_FOUND'
            );
        });

        // Not Found Exception
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            ApplicationLogger::warning('Route not found');

            return \App\Services\APIResponse::error(
                __('messages.http.not_found'),
                404,
                'NOT_FOUND'
            );
        });

        // Generic Exception (SEMPRE POR ÚLTIMO)
        $exceptions->render(function (\Throwable $e, Request $request) {
            ApplicationLogger::exception($e);

            return \App\Services\APIResponse::error(
                __('messages.http.server_error'),
                500,
                'INTERNAL_SERVER_ERROR'
            );
        });
    })
    ->create();
