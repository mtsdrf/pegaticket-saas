<?php

namespace App\Http\Middleware;

use App\Exceptions\AccountingOfficeNotFoundException;
use App\Exceptions\CustomerTokenBlacklistedException;
use App\Exceptions\InvalidAccountingTokenException;
use App\Services\APIResponse;
use App\Support\AccountingTokenResolver;
use Closure;
use Illuminate\Http\Request;

/**
 * Paralelo a CustomerJwtAccessMiddleware, mas para a identidade
 * AccountingOffice (módulo do contador). Réplica do padrão manual de JWT
 * multi-identidade já estabelecido no projeto — ver
 * .claude/memory/api-patterns.md. Popula `accounting_office` no container.
 */
class AccountingJwtAccessMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $office = AccountingTokenResolver::resolve();

            app()->instance('accounting_office', $office);

            return $next($request);
        } catch (InvalidAccountingTokenException $e) {
            return APIResponse::error(__('messages.auth.token_invalid'), 401, 'TOKEN_INVALID');
        } catch (CustomerTokenBlacklistedException $e) {
            return APIResponse::error(__('messages.auth.token_blacklisted'), 401, 'TOKEN_BLACKLISTED');
        } catch (AccountingOfficeNotFoundException $e) {
            return APIResponse::error(__('messages.auth.unauthenticated'), 401, 'UNAUTHENTICATED');
        } catch (\PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException $e) {
            return APIResponse::error(__('messages.auth.token_expired'), 401, 'TOKEN_EXPIRED');
        } catch (\PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException $e) {
            return APIResponse::error(__('messages.auth.token_invalid'), 401, 'TOKEN_INVALID');
        } catch (\Throwable $e) {
            return APIResponse::error(__('messages.auth.unauthenticated'), 401, 'UNAUTHENTICATED');
        }
    }
}
