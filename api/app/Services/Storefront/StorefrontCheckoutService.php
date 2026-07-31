<?php

namespace App\Services\Storefront;

use App\DTOs\Location\CreateEnderecoDTO;
use App\DTOs\Order\CreateOrderDTO;
use App\DTOs\Storefront\StorefrontCheckoutDTO;
use App\Exceptions\BelowMinimumOrderException;
use App\Exceptions\DeliveryAreaNotServedException;
use App\Exceptions\StorefrontDisabledException;
use App\Exceptions\StoreClosedException;
use App\Exceptions\StorePickupUnavailableException;
use App\Exceptions\DeliveryUnavailableException;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\FinalCustomer\FinalCustomerTenantLink;
use App\Models\Order\Order;
use App\Models\Event\EventProduct;
use App\Models\Event\TicketType;
use App\Models\Storefront\CouponRedemption;
use App\Repositories\Contracts\FinalCustomerTenantLinkRepositoryInterface;
use App\Services\Location\EnderecoService;
use App\Services\Order\OrderService;
use App\Services\Permission\PermissionService;
use App\Services\Tenant\TenantSettingsService;
use App\Services\Tenant\TenantExecutionContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Checkout da loja pública (roadmap Delivery, Fase 1) — desde 2026-07-31,
 * FinalCustomer absorveu Client por completo: não existe mais Client pra
 * criar. O que este service resolve/cria é o FinalCustomerTenantLink (o
 * registro POR-TENANT do cliente já autenticado via OTP) + Endereco, sem
 * tocar em PortalLinkService::link() (caminho de vínculo por order_uuid
 * pré-existente, continua intocado). Toda a lógica de preço/estoque/
 * criação de pedido é 100% reaproveitada de OrderService::create() — este
 * service só garante o link e monta o CreateOrderDTO com
 * origin='storefront'/status='pending_approval'.
 */
class StorefrontCheckoutService
{
    public function __construct(
        private StorefrontCatalogService $catalogService,
        private EnderecoService $enderecoService,
        private FinalCustomerTenantLinkRepositoryInterface $linkRepository,
        private TenantSettingsService $tenantSettingsService,
        private OrderService $orderService,
        private PermissionService $permissionService,
        private StoreBusinessHoursService $businessHoursService,
        private StoreDeliveryFeeService $deliveryFeeService,
        private ProductPromotionService $productPromotionService,
        private CouponService $couponService,
        private StoreAddressService $storeAddressService,
        private TenantExecutionContext $tenantExecutionContext,
    ) {
    }

    public function checkout(string $slug, FinalCustomer $customer, StorefrontCheckoutDTO $dto): Order
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

            // 3 guards novos (roadmap Delivery, Fase 2), sempre ANTES de
            // resolver/criar Client+Endereco+Link e montar o CreateOrderDTO
            // — nenhum efeito colateral acontece se qualquer um bloquear.

            // Guard 1: horário de funcionamento.
            if (!$this->businessHoursService->isOpenNow($tenant->id)) {
                throw new StoreClosedException(__('messages.storefront.store_closed'));
            }

            // Guard 1B: retirada na loja (roadmap Delivery) — tenant precisa
            // ter habilitado explicitamente tenant_settings.allow_store_pickup
            // (default false, mesmo espírito de nunca frete grátis/pickup
            // implícito por omissão do guard 3 de entrega).
            $isPickup = $dto->fulfillmentType === 'pickup';

            if ($isPickup && !$settings->allow_store_pickup) {
                throw new StorePickupUnavailableException(__('messages.storefront.store_pickup_not_enabled'));
            }

            // Guard 1C: entrega (configurador de formas de entrega) —
            // simétrico do guard de pickup acima. allow_delivery default
            // true preserva o comportamento histórico (entrega sempre foi
            // implicitamente aceita); só bloqueia quando o tenant desativa
            // explicitamente.
            if (!$isPickup && !$settings->allow_delivery) {
                throw new DeliveryUnavailableException(__('messages.storefront.delivery_not_enabled'));
            }

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

                    throw new BelowMinimumOrderException(
                        __('messages.storefront.below_minimum_order', ['missing' => $missing])
                    );
                }
            }

            // Guard 3: taxa de entrega — bairro sem taxa cadastrada bloqueia
            // a entrega (decisão travada com o usuário: nunca frete
            // grátis/padrão implícito por omissão). Pulado inteiramente
            // quando pickup: não há entrega, logo não há taxa de entrega —
            // $deliveryFee fica 0.0 (não confundir com free_shipping, que é
            // um desconto de cupom, não a ausência de taxa).
            $deliveryFee = 0.0;

            if (!$isPickup) {
                $deliveryFee = $this->deliveryFeeService->findFee($tenant->id, $dto->bairroUuid);

                if ($deliveryFee === null) {
                    throw new DeliveryAreaNotServedException(__('messages.storefront.delivery_area_not_served'));
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

                $deliveryFeeCents = (int) round($deliveryFee * 100);
                $discountAmountCents = $this->couponService->calculateDiscountCents(
                    $coupon,
                    $subtotalCents,
                    $deliveryFeeCents
                );
                $couponId = $coupon->id;
            }

            $this->ensureCustomerLink($tenant->id, $customer, $dto);

            // unit_price explícito por item (prioridade máxima sobre a
            // resolução interna de OrderService::create(), já documentado no
            // DTO desde a Fase 1) — garante que promoção/atacado resolvidos
            // AQUI (resolveEffectiveUnitPrice()) sejam exatamente os
            // praticados no pedido criado, sem depender de OrderService
            // recalcular (ele nem sabe de ProductPromotion).
            $items = $this->resolveItemsWithEffectivePrice($tenant->id, $dto->items);

            $orderDto = new CreateOrderDTO(
                tenantId: $tenant->id,
                finalCustomerUuid: $customer->uuid,
                stockLocationUuid: null,
                isInstallment: false,
                installmentsCount: null,
                notes: $dto->notes,
                expectedDeliveryDate: null,
                markAsDelivered: false,
                markAsPaid: false,
                items: $items,
                origin: 'storefront',
                status: 'pending_approval',
                reserveStock: $settings->block_order_without_stock,
                deliveryFee: $deliveryFee,
                couponId: $couponId,
                discountAmount: $discountAmountCents / 100,
                fulfillmentType: $dto->fulfillmentType,
                paymentMethod: $dto->paymentMethod,
                needsChange: $dto->needsChange,
                changeForAmount: $dto->changeForAmount,
            );

            $order = $this->orderService->create($orderDto);

            // CouponRedemption criado só depois do Order existir com
            // sucesso, na MESMA transação — se qualquer coisa acima falhar
            // (ex: estoque insuficiente), nada de cupom é consumido.
            if ($orderDto->couponId !== null) {
                CouponRedemption::create([
                    'tenant_id' => $tenant->id,
                    'coupon_id' => $orderDto->couponId,
                    'final_customer_id' => $customer->id,
                    'order_id' => $order->id,
                    'redeemed_at' => now(),
                ]);
            }

                return $order;
            });
        });
    }

    /**
     * Garante que existe um FinalCustomerTenantLink CONFIRMADO pra este
     * (customer, tenant) — cria na primeira compra dessa loja, com o
     * endereço/telefone informados no checkout; em compras seguintes só
     * retorna o link já existente (não sobrescreve dado já capturado).
     * A prova de posse aqui é o próprio OTP já verificado (customer.jwt),
     * não um order_uuid pré-existente como no fluxo de
     * PortalLinkService::link().
     */
    private function ensureCustomerLink(int $tenantId, FinalCustomer $customer, StorefrontCheckoutDTO $dto): FinalCustomerTenantLink
    {
        $existing = $this->findExistingLink($tenantId, $customer);

        if ($existing) {
            return $existing;
        }

        [$estadoUuid, $cidadeUuid, $bairroUuid, $logradouro] = $this->resolveClientAddress($tenantId, $dto);

        $endereco = $this->enderecoService->create(new CreateEnderecoDTO(
            tenantId: $tenantId,
            estadoUuid: $estadoUuid,
            cidadeUuid: $cidadeUuid,
            bairroUuid: $bairroUuid,
            logradouro: $logradouro,
            numero: $dto->numero,
            complemento: $dto->complemento,
            cep: $dto->cep,
            isActive: true,
        ));

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
                'endereco_id' => $endereco->id,
                'phone_primary' => $dto->clientPhone,
                'is_trusted' => false,
                'is_active' => true,
                'confirmed_at' => now(),
            ]);
        } catch (QueryException $e) {
            if ((int) $e->getCode() !== 23000) {
                throw $e;
            }

            // Perdemos a corrida: outra requisição concorrente já criou o
            // link pra esse (final_customer_id, tenant_id) entre o SELECT
            // acima e este INSERT. Descarta o Endereco órfão que acabamos
            // de criar (sem link nenhum apontando pra ele, seguro de
            // remover) e reaproveita o link da vencedora.
            $endereco->delete();

            return FinalCustomerTenantLink::where('final_customer_id', $customer->id)
                ->where('tenant_id', $tenantId)
                ->firstOrFail();
        }
    }

    /**
     * Resolve o endereço a persistir num FinalCustomerTenantLink NOVO —
     * quando o checkout é pickup e o cliente não informou endereço (fluxo
     * esperado do frontend nesse caso), reaproveita o endereço da própria
     * loja (StoreAddressService) em vez de um endereço de entrega que não
     * existe. Loja com pickup habilitado mas sem endereço próprio
     * configurado ainda bloqueia com mensagem clara, nunca quebra com erro
     * de FK.
     *
     * @return array{0: string, 1: string, 2: string, 3: string} [estado_uuid, cidade_uuid, bairro_uuid, logradouro]
     */
    private function resolveClientAddress(int $tenantId, StorefrontCheckoutDTO $dto): array
    {
        if ($dto->estadoUuid !== null && $dto->cidadeUuid !== null && $dto->bairroUuid !== null && $dto->logradouro !== null) {
            return [$dto->estadoUuid, $dto->cidadeUuid, $dto->bairroUuid, $dto->logradouro];
        }

        $storeAddress = $this->storeAddressService->getForTenant($tenantId);

        if ($storeAddress === null) {
            throw new StorePickupUnavailableException(__('messages.storefront.store_pickup_address_missing'));
        }

        $storeAddress->loadMissing(['estado', 'cidade', 'bairro']);

        return [$storeAddress->estado->uuid, $storeAddress->cidade->uuid, $storeAddress->bairro->uuid, $storeAddress->logradouro];
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
     * (resolveEffectiveUnitPrice() — promoção sempre vence, senão base).
     * Mesmo cuidado de centavos/arredondamento de OrderService::create() —
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
     * do CreateOrderDTO com unit_price explícito por item — garante que o
     * pedido criado por OrderService::create() pratique exatamente o mesmo
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
            ];
        }, $items);
    }

    /**
     * Preço efetivo de venda pro público:
     * 1. Promoção ativa (ProductPromotionService::findActivePromoPrice())
     *    SEMPRE vence, quando existir e o item for um TicketType — é o
     *    preço de venda público que o tenant decidiu. EventProduct não tem
     *    promoção (fora do MVP).
     * 2. Senão, preço base do item.
     */
    public function resolveEffectiveUnitPrice(TicketType|EventProduct $sellable, float $quantity): float
    {
        if ($sellable instanceof TicketType) {
            $promoPrice = $this->productPromotionService->findActivePromoPrice($sellable);

            if ($promoPrice !== null) {
                return $promoPrice;
            }
        }

        return (float) $sellable->price;
    }
}
