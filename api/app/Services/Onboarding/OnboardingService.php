<?php

namespace App\Services\Onboarding;

use App\Models\FinalCustomer\FinalCustomerTenantLink;
use App\Models\Order\Order;
use App\Models\Event\TicketType;
use App\Models\Storefront\StoreBusinessHour;
use App\Models\Tenant\TenantUser;
use App\Services\Permission\PermissionService;

/**
 * Checklist de implantação (roadmap A2, dores #4/#15) — leitura pura sobre
 * entidades já existentes, sem tabela nova. No contexto atual de ingressos,
 * a loja online é considerada configurada quando o tenant já definiu seus
 * horários de funcionamento.
 */
class OnboardingService
{
    public function __construct(
        private PermissionService $permissionService
    ) {
    }

    public function checklist(int $tenantId, TenantUser $tenantUser): array
    {
        $items = [
            'has_product' => TicketType::where('tenant_id', $tenantId)->whereNull('deleted_at')->exists(),
            'has_client' => FinalCustomerTenantLink::where('tenant_id', $tenantId)->exists(),
            'has_first_order' => Order::where('tenant_id', $tenantId)->whereNull('deleted_at')->exists(),
        ];

        $steps = [
            [
                'key' => 'has_product',
                'label' => 'Cadastre seu primeiro tipo de ingresso',
                'to' => '/ticket-types',
                'link_label' => 'Cadastrar tipo de ingresso',
                'completed' => $items['has_product'],
            ],
            [
                'key' => 'has_client',
                'label' => 'Preencha um cliente em um pedido',
                'to' => '/pedidos/novo',
                'link_label' => 'Abrir pedido',
                'completed' => $items['has_client'],
            ],
        ];

        $allowedFunctionalities = array_fill_keys(
            $this->permissionService->resolveTenantAllowedFunctionalities($tenantId),
            true
        );

        if (isset($allowedFunctionalities['storefront'])) {
            $items['storefront_configured'] = StoreBusinessHour::where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->exists();

            $steps[] = [
                'key' => 'storefront_configured',
                'label' => 'Configure sua loja online',
                'to' => '/configuracoes/loja-online',
                'link_label' => 'Configurar loja',
                'completed' => $items['storefront_configured'],
            ];
        }

        $steps[] = [
            'key' => 'has_first_order',
            'label' => 'Registre seu primeiro pedido',
            'to' => '/pedidos/novo',
            'link_label' => 'Novo pedido',
            'completed' => $items['has_first_order'],
        ];

        return array_merge($items, [
            'steps' => $steps,
            'is_dismissed' => $tenantUser->onboarding_checklist_dismissed_at !== null,
            'dismissed_at' => $tenantUser->onboarding_checklist_dismissed_at?->toIso8601String(),
            'completed' => count(array_filter($steps, fn (array $step) => $step['completed'])),
            'total' => count($steps),
        ]);
    }

    public function dismiss(TenantUser $tenantUser): array
    {
        $tenantUser->forceFill([
            'onboarding_checklist_dismissed_at' => now(),
        ])->save();

        return $this->checklist((int) $tenantUser->tenant_id, $tenantUser->fresh());
    }

    public function restore(TenantUser $tenantUser): array
    {
        $tenantUser->forceFill([
            'onboarding_checklist_dismissed_at' => null,
        ])->save();

        return $this->checklist((int) $tenantUser->tenant_id, $tenantUser->fresh());
    }
}
