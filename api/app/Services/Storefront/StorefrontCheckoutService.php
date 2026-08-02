<?php

namespace App\Services\Storefront;

use App\DTOs\Sale\CreateSaleDTO;
use App\DTOs\Storefront\StorefrontCheckoutDTO;
use App\Exceptions\BelowMinimumSaleException;
use App\Exceptions\StorefrontDisabledException;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\FinalCustomer\FinalCustomerTenantLink;
use App\Models\Sale\Sale;
use App\Models\Event\EventProduct;
use App\Models\Event\TicketType;
use App\Models\Inventory\InventoryHold;
use App\Models\Inventory\InventoryHoldItem;
use App\Models\Storefront\CouponRedemption;
use App\Repositories\Contracts\FinalCustomerTenantLinkRepositoryInterface;
use App\Services\Sale\SaleService;
use App\Services\Permission\PermissionService;
use App\Services\Tenant\TenantSettingsService;
use App\Services\Tenant\TenantExecutionContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Checkout da loja pública (roadmap Delivery, Fase 1) — desde 2026-07-31,
 * FinalCustomer absorveu Client por completo: não existe mais Client pra
 * criar. O que este service resolve/cria é o FinalCustomerTenantLink (o
 * registro POR-TENANT do cliente já autenticado via OTP), sem
 * tocar em PortalLinkService::link() (caminho de vínculo por sale_uuid
 * pré-existente, continua intocado). Toda a lógica de preço/criação de
 * pedido é 100% reaproveitada de SaleService::create() — este
 * service só garante o link e monta o CreateSaleDTO com
 * origin='storefront'/status='pending_approval'.
 */
class StorefrontCheckoutService
{
    public function __construct(
        private StorefrontCatalogService $catalogService,
        private FinalCustomerTenantLinkRepositoryInterface $linkRepository,
        private TenantSettingsService $tenantSettingsService,
        private SaleService $orderService,
        private PermissionService $permissionService,
        private CouponService $couponService,
        private StorefrontHoldService $holdService,
        private TenantExecutionContext $tenantExecutionContext,
    ) {
    }

    public function checkout(string $slug, FinalCustomer $customer, StorefrontCheckoutDTO $dto): Sale
    {
        $tenant = $this->catalogService->findTenantBySlug($slug);

        return $this->tenantExecutionContext->run($tenant, function () use ($tenant, $customer, $dto) {
            return DB::transaction(function () use ($tenant, $customer, $dto) {
                // Defesa em profundidade: findTenantBySlug() já checou o plano
                // fora da transação, re-checa aqui dentro dela antes de mutar
                // qualquer coisa.
                if (!$this->permissionService->tenantPlanAllowsFunctionality($tenant->id, 'storefront')) {
                    abort(404);
                }

                $settings = $this->tenantSettingsService->getForTenant($tenant->id);

                if (!$settings->storefront_enabled) {
                    throw new StorefrontDisabledException(__('messages.storefront.storefront_disabled'));
                }

                $hold = $dto->holdUuid ? $this->resolveCheckoutHold($tenant->id, $dto->holdUuid, $dto->sessionToken, $customer) : null;

            // Guards (roadmap Delivery, Fase 2), sempre ANTES de
            // resolver/criar Client+Endereco+Link e montar o CreateSaleDTO
            // — nenhum efeito colateral acontece se qualquer um bloquear.
            //
            // Guard 1B (retirada na loja física / tenant_settings.allow_store_pickup)
            // REMOVIDO em 2026-08-01 (roadmap PegaTicket, MVP compra de
            // ingresso) — resquício de checkout de loja física sem
            // equivalente no domínio de ingresso (não existe "retirada" de
            // Ticket digital). Coluna allow_store_pickup dropada em
            // 2026-08-04 (migration drop_allow_store_pickup_from_tenant_settings_table).

            // Calculado incondicionalmente (não só quando há mínimo
            // configurado) — Guard 4 (cupom) também precisa deste mesmo
            // subtotal (com promoção/desconto por atacado já aplicado)
            // para checar coupons.minimum_order_value e calcular o
            // desconto.
            $subtotalCents = $this->calculateCartSubtotalCents($tenant->id, $dto->items);

            // Guard 2: pedido mínimo (quando configurado).
            if ($settings->minimum_order_value !== null) {
                $minimumCents = (int) round($settings->minimum_order_value * 100);

                if ($subtotalCents < $minimumCents) {
                    $missing = number_format(($minimumCents - $subtotalCents) / 100, 2, ',', '.');

                    throw new BelowMinimumSaleException(
                        __('messages.storefront.below_minimum_order', ['missing' => $missing])
                    );
                }
            }

            // Guard 4: cupom (roadmap Delivery, Fase 3) — opcional, só roda
            // quando dto->couponCode vem preenchido. $customer->id já é
            // conhecido desde o início do checkout (identidade resolvida
            // pelo OTP), diferente da prévia pública (validatePreview(),
            // sem check de limite por cliente).
            $couponId = null;
            $discountAmountCents = 0;

            if ($dto->couponCode !== null && $dto->couponCode !== '') {
                $coupon = $this->couponService->validateForCheckout(
                    $tenant->id,
                    $dto->couponCode,
                    $customer->id,
                    $subtotalCents,
                    $dto->paymentMethod
                );

                $discountAmountCents = $this->couponService->calculateDiscountCents(
                    $coupon,
                    $subtotalCents,
                    0
                );
                $couponId = $coupon->id;
            }

            $this->ensureCustomerLink($tenant->id, $customer, $dto);

            if ($hold) {
                $this->validateHoldMatchesCheckout($hold, $dto->items);
            }

            // unit_price explícito por item (prioridade máxima sobre a
            // resolução interna de SaleService::create(), já documentado no
            // DTO desde a Fase 1) — garante que o preço resolvido AQUI
            // (resolveEffectiveUnitPrice()) seja exatamente o praticado no
            // pedido criado, sem depender de SaleService recalcular.
            $items = $this->resolveItemsWithEffectivePrice($tenant->id, $dto->items);

            $orderDto = new CreateSaleDTO(
                tenantId: $tenant->id,
                finalCustomerUuid: $customer->uuid,
                isInstallment: false,
                installmentsCount: null,
                notes: $dto->notes,
                items: $items,
                origin: 'storefront',
                status: 'pending_approval',
                couponId: $couponId,
                discountAmount: $discountAmountCents / 100,
                paymentMethod: $dto->paymentMethod,
                needsChange: $dto->needsChange,
                changeForAmount: $dto->changeForAmount,
            );

            $order = $this->orderService->create($orderDto);

            if ($hold) {
                $this->consumeHold($hold, $order);
            }

            // CouponRedemption criado só depois do Sale existir com
            // sucesso, na MESMA transação — se qualquer coisa acima falhar
            // (ex: estoque insuficiente), nada de cupom é consumido.
            if ($orderDto->couponId !== null) {
                CouponRedemption::create([
                    'tenant_id' => $tenant->id,
                    'coupon_id' => $orderDto->couponId,
                    'final_customer_id' => $customer->id,
                    'sale_id' => $order->id,
                    'redeemed_at' => now(),
                ]);
            }

            return $order;
            });
        });
    }

    /**
     * Garante que existe um FinalCustomerTenantLink CONFIRMADO pra este
     * (customer, tenant) — cria na primeira compra dessa loja com os dados
     * de contato informados no checkout; em compras seguintes só retorna o
     * link já existente (não sobrescreve dado já capturado).
     * A prova de posse aqui é o próprio OTP já verificado (customer.jwt),
     * não um sale_uuid pré-existente como no fluxo de
     * PortalLinkService::link().
     */
    private function ensureCustomerLink(int $tenantId, FinalCustomer $customer, StorefrontCheckoutDTO $dto): FinalCustomerTenantLink
    {
        $existing = $this->findExistingLink($tenantId, $customer);

        if ($existing) {
            return $existing;
        }

        if ($customer->name === null) {
            $customer->forceFill([
                'name' => $dto->clientName,
                'last_name' => $dto->clientLastName,
            ])->save();
        }

        try {
            // is_trusted: false — diferente do default do formulário do
            // staff (true): cliente autoatendido, ainda não verificado por
            // ninguém da equipe. confirmed_at = now(): ver docblock acima.
            return $this->linkRepository->create([
                'final_customer_id' => $customer->id,
                'tenant_id' => $tenantId,
                'phone_primary' => $dto->clientPhone,
                'is_trusted' => false,
                'is_active' => true,
                'confirmed_at' => now(),
            ]);
        } catch (QueryException $e) {
            if ((int) $e->getCode() !== 23000) {
                throw $e;
            }

            return FinalCustomerTenantLink::where('final_customer_id', $customer->id)
                ->where('tenant_id', $tenantId)
                ->firstOrFail();
        }
    }

    /**
     * Busca só-leitura do link já confirmado deste FinalCustomer nesta
     * loja, sem nenhum side-effect (não cria nada) — usada por
     * ensureCustomerLink() (que só cria um link novo quando esta busca não
     * encontra nada).
     */
    private function findExistingLink(int $tenantId, FinalCustomer $customer): ?FinalCustomerTenantLink
    {
        return FinalCustomerTenantLink::where('final_customer_id', $customer->id)
            ->where('tenant_id', $tenantId)
            ->first();
    }

    /**
     * Soma dos itens do carrinho em centavos, já usando o preço EFETIVO
     * (resolveEffectiveUnitPrice() — preço base do item).
     * Mesmo cuidado de centavos/arredondamento de SaleService::create() —
     * evita erro de ponto flutuante na soma. SIMPLIFICAÇÃO DOCUMENTADA:
     * atacado por quantidade e adicionais (product_options) foram
     * descartados junto com o split Product -> TicketType/EventProduct
     * (roadmap seção 4A) — fora do MVP de ingresso.
     *
     * @param array<int, array{ticket_type_uuid?: string, event_product_uuid?: string, quantity: float}> $items
     */
    private function calculateCartSubtotalCents(int $tenantId, array $items): int
    {
        $totalCents = 0;

        foreach ($items as $item) {
            $sellable = $this->resolveSellable($tenantId, $item);

            $unitPrice = $this->resolveEffectiveUnitPrice($sellable, (float) $item['quantity']);

            $unitPriceCents = (int) round($unitPrice * 100);
            $totalCents += (int) round($unitPriceCents * (float) $item['quantity']);
        }

        return $totalCents;
    }

    private function resolveSellable(int $tenantId, array $item): TicketType|EventProduct
    {
        if (!empty($item['ticket_type_uuid'])) {
            return TicketType::where('uuid', $item['ticket_type_uuid'])
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->firstOrFail();
        }

        return EventProduct::where('uuid', $item['event_product_uuid'])
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->firstOrFail();
    }

    /**
     * Reaproveita resolveEffectiveUnitPrice() para montar o array de itens
     * do CreateSaleDTO com unit_price explícito por item — garante que o
     * pedido criado por SaleService::create() pratique exatamente o mesmo
     * preço que passou pelo guard de mínimo acima, sem duplicar a
     * resolução de preço numa segunda camada.
     *
     * @param array<int, array{ticket_type_uuid?: string, event_product_uuid?: string, quantity: float, notes?: string}> $items
     * @return array<int, array{ticket_type_uuid?: string, event_product_uuid?: string, quantity: float, unit_price: float, notes: ?string}>
     */
    private function resolveItemsWithEffectivePrice(int $tenantId, array $items): array
    {
        return array_map(function (array $item) use ($tenantId) {
            $sellable = $this->resolveSellable($tenantId, $item);

            return [
                'ticket_type_uuid' => $item['ticket_type_uuid'] ?? null,
                'event_product_uuid' => $item['event_product_uuid'] ?? null,
                'quantity' => $item['quantity'],
                'unit_price' => $this->resolveEffectiveUnitPrice($sellable, (float) $item['quantity']),
                'notes' => isset($item['notes']) && trim((string) $item['notes']) !== '' ? trim((string) $item['notes']) : null,
                // Participantes (spec 5.10 Etapa 2) — repassados como estão,
                // SaleService::resolveSaleItemLine() já normaliza/descarta
                // pra item de event_product.
                'attendee_data' => $item['participants'] ?? null,
            ];
        }, $items);
    }

    /**
     * Preço efetivo de venda pro público — preço base do item. Promoção
     * "de/por" (ProductPromotion) removida em 2026-08-04: resíduo do
     * produto antigo "Maskats", sem consumidor no frontend PegaTicket.
     */
    public function resolveEffectiveUnitPrice(TicketType|EventProduct $sellable, float $quantity): float
    {
        return (float) $sellable->price;
    }

    private function resolveCheckoutHold(int $tenantId, string $holdUuid, ?string $sessionToken, FinalCustomer $customer): InventoryHold
    {
        $hold = InventoryHold::query()
            ->where('tenant_id', $tenantId)
            ->where('uuid', $holdUuid)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->firstOrFail();

        $customerMatches = (int) $hold->final_customer_id === (int) $customer->id;
        $sessionMatches = $sessionToken !== null && $hold->session_token === $sessionToken;

        if (!$customerMatches && !$sessionMatches) {
            abort(404);
        }

        if ($hold->status === InventoryHold::STATUS_RESERVED && $hold->expires_at?->isPast()) {
            $hold->forceFill(['status' => InventoryHold::STATUS_EXPIRED])->save();
        }

        if ($hold->status !== InventoryHold::STATUS_RESERVED) {
            abort(422, __('messages.inventory_hold.not_active'));
        }

        return $hold->load([
            'items.ticketType',
            'items.eventProduct',
            'items.ticketBatch',
            'items.seat',
        ]);
    }

    private function validateHoldMatchesCheckout(InventoryHold $hold, array $items): void
    {
        $holdItems = $hold->items->sortBy('id')->values();
        $payloadItems = collect($items)->values();

        if ($holdItems->count() !== $payloadItems->count()) {
            abort(422, __('messages.inventory_hold.checkout_mismatch'));
        }

        foreach ($holdItems as $index => $holdItem) {
            $payloadItem = $payloadItems[$index];
            $payloadTicketTypeUuid = $payloadItem['ticket_type_uuid'] ?? null;
            $payloadEventProductUuid = $payloadItem['event_product_uuid'] ?? null;
            $payloadQuantity = (int) round((float) $payloadItem['quantity']);

            $sameTicketType = $holdItem->ticketType?->uuid === $payloadTicketTypeUuid;
            $sameEventProduct = $holdItem->eventProduct?->uuid === $payloadEventProductUuid;
            $sameQuantity = (int) $holdItem->quantity === $payloadQuantity;

            if (!$sameQuantity || (!$sameTicketType && !$sameEventProduct)) {
                abort(422, __('messages.inventory_hold.checkout_mismatch'));
            }
        }
    }

    private function consumeHold(InventoryHold $hold, Sale $order): void
    {
        $order->loadMissing('items');

        $holdItems = $hold->items->sortBy('id')->values();
        $saleItems = $order->items->sortBy('id')->values();

        foreach ($holdItems as $index => $holdItem) {
            $saleItem = $saleItems[$index] ?? null;

            if (!$saleItem) {
                abort(422, __('messages.inventory_hold.checkout_mismatch'));
            }

            if ($holdItem->ticket_batch_id !== null) {
                $saleItem->ticket_batch_id = $holdItem->ticket_batch_id;
            }

            if ($holdItem->seat_id !== null) {
                $saleItem->seat_id = $holdItem->seat_id;
            }

            $saleItem->save();

            if ($holdItem->ticketBatch) {
                $holdItem->ticketBatch->increment('quantity_sold', (int) $holdItem->quantity);
            }
        }

        $hold->forceFill([
            'final_customer_id' => $order->final_customer_id,
            'converted_sale_id' => $order->id,
            'status' => InventoryHold::STATUS_CONVERTED,
        ])->save();
    }
}
