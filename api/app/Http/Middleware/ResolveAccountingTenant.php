<?php

namespace App\Http\Middleware;

use App\Enums\Accounting\AccountingAccessStatus;
use App\Models\Accounting\AccountingOfficeTenant;
use App\Models\Tenant\Tenant;
use App\Services\APIResponse;
use Closure;
use Illuminate\Http\Request;

/**
 * Resolução do tenant ativo para o CONTADOR (roadmap 2C). Igual em espírito a
 * ResolveTenant, mas mecanismo próprio: NÃO lê `tenant_uuid` de claim de JWT
 * staff — o contador já está autenticado como AccountingOffice (via
 * `accounting.jwt`, que roda antes) e o tenant vem do path param
 * `{tenant_uuid}`. Valida que existe um vínculo `approved` em
 * accounting_office_tenant e só então popula tenant/tenant_id/etc no
 * container. Também deixa disponível o vínculo (`accounting_office_tenant`)
 * para os controllers auditarem/escoparem consultas.
 */
class ResolveAccountingTenant
{
    public function handle(Request $request, Closure $next)
    {
        $office = app()->bound('accounting_office') ? app('accounting_office') : null;

        if (!$office) {
            return APIResponse::error(__('messages.auth.unauthenticated'), 401, 'UNAUTHENTICATED');
        }

        $tenantUuid = $request->route('tenant_uuid');

        if (!$tenantUuid) {
            return APIResponse::error(__('messages.tenant.invalid'), 403, 'TENANT_INVALID');
        }

        $tenant = Tenant::where('uuid', $tenantUuid)
            ->where('is_active', true)
            ->first();

        if (!$tenant) {
            return APIResponse::error(__('messages.tenant.invalid'), 403, 'TENANT_INVALID');
        }

        $link = AccountingOfficeTenant::where('accounting_office_id', $office->id)
            ->where('tenant_id', $tenant->id)
            ->where('status', AccountingAccessStatus::Approved->value)
            ->first();

        if (!$link) {
            return APIResponse::error(
                __('messages.accounting_access.not_approved'),
                403,
                'ACCOUNTING_ACCESS_DENIED'
            );
        }

        app()->instance('tenant', $tenant);
        app()->instance('tenant_id', $tenant->id);
        app()->instance('tenant_uuid', $tenant->uuid);
        app()->instance('accounting_office_tenant', $link);

        return $next($request);
    }
}
