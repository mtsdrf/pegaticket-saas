<?php

namespace App\Http\Middleware;

use App\Services\APIResponse;
use Closure;
use Illuminate\Http\Request;

class EnsureAccountingScope
{
    public function handle(Request $request, Closure $next, string ...$requiredScopes)
    {
        $link = app()->bound('accounting_office_tenant') ? app('accounting_office_tenant') : null;

        if (!$link) {
          return APIResponse::error(__('messages.auth.unauthenticated'), 401, 'UNAUTHENTICATED');
        }

        $grantedScopes = is_array($link->scopes) ? $link->scopes : [];

        foreach ($requiredScopes as $scope) {
            if (in_array($scope, $grantedScopes, true)) {
                return $next($request);
            }
        }

        return APIResponse::error(
            __('messages.accounting_access.not_approved'),
            403,
            'ACCOUNTING_SCOPE_DENIED'
        );
    }
}
