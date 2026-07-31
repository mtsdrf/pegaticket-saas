<?php

namespace App\Http\Middleware;

use App\Exceptions\CustomerTokenBlacklistedException;
use App\Exceptions\FinalCustomerNotFoundException;
use App\Exceptions\InvalidCustomerTokenException;
use App\Services\APIResponse;
use App\Support\CustomerTokenResolver;
use Closure;
use Illuminate\Http\Request;

/**
 * Paralelo a JwtAccessMiddleware, mas para a identidade FinalCustomer
 * (portal do cliente final) em vez de App\Models\User\User (staff). Réplica
 * deliberada do padrão manual já existente (parseToken/getPayload/checar
 * blacklist por jti/resolver o subject "na mão") em vez de um guard nativo
 * do Laravel — ver justificativa completa em
 * .claude/memory/api-patterns.md ("JWT multi-identidade").
 *
 * A resolução em si vive em App\Support\CustomerTokenResolver,
 * compartilhada com OptionalCustomerJwtMiddleware (Delivery Fase 4) — aqui
 * cada exceção vira um 401 com código específico; lá, qualquer exceção vira
 * "segue anônimo".
 */
class CustomerJwtAccessMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $customer = CustomerTokenResolver::resolve();

            app()->instance('portal_customer', $customer);

            return $next($request);
        } catch (InvalidCustomerTokenException $e) {
            return APIResponse::error(
                __('messages.auth.token_invalid'),
                401,
                'TOKEN_INVALID'
            );
        } catch (CustomerTokenBlacklistedException $e) {
            return APIResponse::error(
                __('messages.auth.token_blacklisted'),
                401,
                'TOKEN_BLACKLISTED'
            );
        } catch (FinalCustomerNotFoundException $e) {
            return APIResponse::error(
                __('messages.auth.unauthenticated'),
                401,
                'UNAUTHENTICATED'
            );
        } catch (\PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException $e) {
            return APIResponse::error(
                __('messages.auth.token_expired'),
                401,
                'TOKEN_EXPIRED'
            );
        } catch (\PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException $e) {
            return APIResponse::error(
                __('messages.auth.token_invalid'),
                401,
                'TOKEN_INVALID'
            );
        } catch (\PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException $e) {
            return APIResponse::error(
                __('messages.auth.unauthenticated'),
                401,
                'UNAUTHENTICATED'
            );
        } catch (\Throwable $e) {
            return APIResponse::error(
                __('messages.auth.unauthenticated'),
                401,
                'UNAUTHENTICATED'
            );
        }
    }
}
