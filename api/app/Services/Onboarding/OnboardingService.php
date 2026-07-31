<?php

namespace App\Services\Onboarding;

use App\Models\FinalCustomer\FinalCustomerTenantLink;
use App\Models\Order\Order;
use App\Models\Event\TicketType;
use App\Models\Storefront\StoreBusinessHour;
use App\Models\Storefront\StoreDeliveryFee;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantUser;
use App\Services\Permission\PermissionService;

/**
 * Checklist de implantação (roadmap A2, dores #4/#15) — leitura pura sobre
 * entidades já existentes, sem tabela nova. `storefront_configured` usa
 * `StoreBusinessHour` OU `StoreDeliveryFee`: qualquer um dos dois já é um
 * sinal de que o tenant configurou a loja online (horário de
 * funcionamento OU taxa de entrega por bairro), sem exigir os dois juntos
 * — nem todo tenant usa entrega paga (retirada no local não tem taxa).
 *
 * `has_store_address` (2026-07-24, auditoria de dados de cliente) — passo
 * obrigatório separado: `tenants.endereco_id` é nullable e não fazia parte
 * do checklist, então a origem da rota de entrega podia ficar sem
 * referência geográfica sem nenhum aviso (achado "Silencioso/Degradado"
 * na auditoria). Reaproveita o mesmo endereço de `StoreAddressController`
 * (`App\Services\Storefront\StoreAddressService`), não cria dado novo.
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
            $items['has_store_address'] = Tenant::whereKey($tenantId)->whereNotNull('endereco_id')->exists();
            $items['storefront_configured'] = StoreBusinessHour::where('tenant_id', $tenantId)->whereNull('deleted_at')->exists()
                || StoreDeliveryFee::where('tenant_id', $tenantId)->whereNull('deleted_at')->exists();

            $steps[] = [
                'key' => 'has_store_address',
                'label' => 'Cadastre o endereço da sua loja',
                'to' => '/configuracoes/loja-online',
                'link_label' => 'Cadastrar endereço',
                'completed' => $items['has_store_address'],
            ];
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
