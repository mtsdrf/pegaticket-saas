<?php

namespace App\Http\Middleware;

use App\Models\Auth\TokenBlacklist;
use App\Models\User\User;
use App\Services\APIResponse;
use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class JwtAccessMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $token = JWTAuth::parseToken();
            $payload = $token->getPayload();

            // Desde a Fase 5.2 (portal do cliente final) existe um segundo
            // JWTSubject (FinalCustomer) usando o MESMO segredo/manager.
            // `lock_subject=true` (config/jwt.php) já embute a claim `prv`
            // (hash da classe do subject) em todo token emitido — sem essa
            // checagem, um token de FinalCustomer com `sub` numericamente
            // igual a um `users.id` real seria autenticado aqui como esse
            // usuário STAFF (onceUsingId não valida o model de origem).
            if (!$token->checkSubjectModel(User::class)) {
                return APIResponse::error(
                    __('messages.auth.token_invalid'),
                    401,
                    'TOKEN_INVALID'
                );
            }

            $jti = (string) $payload->get('jti');

            if (!$jti) {
                return APIResponse::error(
                    __('messages.auth.token_invalid'),
                    401,
                    'TOKEN_INVALID'
                );
            }

            $blacklistEntry = TokenBlacklist::where('jti', $jti)
                ->where('expires_at', '>', now())
                ->first();

            if ($blacklistEntry) {
                return APIResponse::error(
                    __('messages.auth.token_blacklisted'),
                    401,
                    'TOKEN_BLACKLISTED'
                );
            }

            $user = $token->authenticate();

            if (!$user || !$user->is_active) {
                return APIResponse::error(
                    __('messages.auth.unauthenticated'),
                    401,
                    'UNAUTHENTICATED'
                );
            }

            return $next($request);

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