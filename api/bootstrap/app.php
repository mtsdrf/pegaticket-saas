<?php

use App\Http\Middleware\AdaptiveThrottleMiddleware;
use App\Http\Middleware\ApiHardening;
use App\Http\Middleware\ApplicationRequestLogger;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CustomerJwtAccessMiddleware;
use App\Http\Middleware\EnsureTenantOwner;
use App\Http\Middleware\JwtAccessMiddleware;
use App\Http\Middleware\OptionalCustomerJwtMiddleware;
use App\Http\Middleware\RequestId;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\ThrottleRequests;
use App\Services\APIResponse;
use App\Services\Logging\ApplicationLogger;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenBlacklistedException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Globais (todas as requisições)
        $middleware->append([
            HandleCors::class,
            ApiHardening::class,
            RequestId::class,
            ApplicationRequestLogger::class,
            SetLocale::class,
        ]);

        // Aliases (para usar em rotas)
        $middleware->alias([
            'jwt' => JwtAccessMiddleware::class,
            'perm' => CheckPermission::class,
            'throttle' => ThrottleRequests::class,
            'adaptive.throttle' => AdaptiveThrottleMiddleware::class,
            'tenant' => ResolveTenant::class,
            'tenant.owner' => EnsureTenantOwner::class,
            'customer.jwt' => CustomerJwtAccessMiddleware::class,
            'customer.jwt.optional' => OptionalCustomerJwtMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // JWT Exceptions (esperadas)
        $exceptions->render(function (TokenBlacklistedException $e, Request $request) {
            ApplicationLogger::warning('JWT token blacklisted');

            return APIResponse::error(
                __('messages.auth.token_blacklisted'),
                401,
                'TOKEN_BLACKLISTED'
            );
        });

        $exceptions->render(function (TokenExpiredException $e, Request $request) {
            ApplicationLogger::warning('JWT token expired');

            return APIResponse::error(
                __('messages.auth.token_expired'),
                401,
                'TOKEN_EXPIRED'
            );
        });

        $exceptions->render(function (TokenInvalidException $e, Request $request) {
            ApplicationLogger::warning('JWT token invalid');

            return APIResponse::error(
                __('messages.auth.token_invalid'),
                401,
                'TOKEN_INVALID'
            );
        });

        $exceptions->render(function (JWTException $e, Request $request) {
            ApplicationLogger::warning('JWT unauthenticated', [
                'exception' => $e->getMessage(),
            ]);

            return APIResponse::error(
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

            return APIResponse::error(
                __('messages.validation.failed'),
                422,
                'VALIDATION_ERROR',
                $e->errors()
            );
        });

        // Authentication Exception
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            ApplicationLogger::warning('Authentication failed');

            return APIResponse::error(
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

            return APIResponse::error(
                __('messages.http.not_found'),
                404,
                'NOT_FOUND'
            );
        });

        // Not Found Exception
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            ApplicationLogger::warning('Route not found');

            return APIResponse::error(
                __('messages.http.not_found'),
                404,
                'NOT_FOUND'
            );
        });

        // HttpException genérica (abort($code, $message) sem Exception
        // dedicada) — achado ao cobrir o checkout de ingresso com hold
        // expirado (roadmap PegaTicket 5.9/5.10, 2026-08-01):
        // StorefrontHoldService/StorefrontCheckoutService (e outros
        // services que usam abort(422, ...) direto, ex: EventService,
        // SeatService, TicketBatchService) não têm Exception própria pra
        // esses guards, e sem este handler o Throwable genérico abaixo
        // capturava a HttpException ANTES do Symfony conseguir preservar o
        // status code, sempre respondendo 500/"erro interno" mesmo para
        // guard de validação (422/409/...) — cliente nunca via a mensagem
        // real. Preserva status code e mensagem (sempre um __('messages...')
        // controlado pelo próprio backend, nunca input cru do cliente).
        $exceptions->render(function (HttpException $e, Request $request) {
            return APIResponse::error(
                $e->getMessage() ?: __('messages.http.server_error'),
                $e->getStatusCode(),
                'HTTP_ERROR'
            );
        });

        // Generic Exception (SEMPRE POR ÚLTIMO)
        $exceptions->render(function (Throwable $e, Request $request) {
            ApplicationLogger::exception($e);

            return APIResponse::error(
                __('messages.http.server_error'),
                500,
                'INTERNAL_SERVER_ERROR'
            );
        });
    })
    ->create();
