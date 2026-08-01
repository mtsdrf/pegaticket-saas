<?php

namespace App\Services\Onboarding;

use App\Models\FinalCustomer\FinalCustomerTenantLink;
use App\Models\Sale\Sale;
use App\Models\Event\TicketType;
use App\Models\Tenant\TenantUser;

/**
 * Checklist de implantação (roadmap A2, dores #4/#15) — leitura pura sobre
 * entidades já existentes, sem tabela nova.
 */
class OnboardingService
{
    public function checklist(int $tenantId, TenantUser $tenantUser): array
    {
        $items = [
            'has_product' => TicketType::where('tenant_id', $tenantId)->whereNull('deleted_at')->exists(),
            'has_client' => FinalCustomerTenantLink::where('tenant_id', $tenantId)->exists(),
            'has_first_order' => Sale::where('tenant_id', $tenantId)->whereNull('deleted_at')->exists(),
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
