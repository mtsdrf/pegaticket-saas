<?php

namespace App\Services\Storefront;

use App\DTOs\Client\CreateClientDTO;
use App\DTOs\Order\CreateOrderDTO;
use App\DTOs\Storefront\StorefrontCheckoutDTO;
use App\Exceptions\BelowMinimumOrderException;
use App\Exceptions\DeliveryAreaNotServedException;
use App\Exceptions\StorefrontDisabledException;
use App\Exceptions\StoreClosedException;
use App\Exceptions\StorePickupUnavailableException;
use App\Exceptions\DeliveryUnavailableException;
use App\Models\Client\Client;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\FinalCustomer\FinalCustomerTenantLink;
use App\Models\Order\Order;
use App\Models\Product\Product;
use App\Models\Product\ProductOption;
use App\Models\Storefront\CouponRedemption;
use App\Repositories\Contracts\FinalCustomerTenantLinkRepositoryInterface;
use App\Services\Client\ClientService;
use App\Services\Order\OrderService;
use App\Services\Permission\PermissionService;
use App\Services\Storefront\CashbackService;
use App\Services\Product\ProductPricingService;
use App\Services\Tenant\TenantSettingsService;
use App\Services\Tenant\TenantExecutionContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Checkout da loja pública (roadmap Delivery, Fase 1) — implementa a
 * "decisão central" do plano: resolve/cria Client+Endereco+
 * FinalCustomerTenantLink pra satisfazer orders.client_id/clients.
 * endereco_id (obrigatórios), sem tocar em PortalLinkService::link()
 * (caminho de vínculo por order_uuid pré-existente, continua intocado).
 * Toda a lógica de preço/estoque/criação de pedido é 100% reaproveitada de
 * OrderService::create() — este service só resolve client_uuid e monta o
 * CreateOrderDTO com origin='storefront'/status='pending_approval'.
 */
class StorefrontCheckoutService
{
    public function __construct(
        private StorefrontCatalogService $catalogService,
        private ClientService $clientService,
        private FinalCustomerTenantLinkRepositoryInterface $linkRepository,
        private TenantSettingsService $tenantSettingsService,
        private OrderService $orderService,
        private PermissionService $permissionService,
        private StoreBusinessHoursService $businessHoursService,
        private StoreDeliveryFeeService $deliveryFeeService,
        private ProductPricingService $pricingService,
        private ProductPromotionService $productPromotionService,
        private CouponService $couponService,
        private CashbackService $cashbackService,
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

            // Achado de code review: o guard 2 original somava sempre pelo
            // Product.price BASE, mesmo quando o cliente já tinha um
            // Client vinculado (segunda compra+) com desconto de categoria
            // aplicável — o total final calculado por OrderService::create()
            // via ProductPricingService podia ficar abaixo do mínimo
            // mesmo o guard tendo liberado o checkout. Correção: resolve o
            // Client já vinculado (busca só-leitura, sem side-effect) ANTES
            // do guard, e usa o preço com desconto quando ele existe —
            // cliente novo (sem Client ainda) continua pelo preço base,
            // que é a única opção possível nesse caso (nada pra descontar
            // ainda).
            $existingClient = $this->findExistingClient($tenant->id, $customer);

            // Calculado incondicionalmente (não só quando há mínimo
            // configurado) — Guard 4 (cupom) também precisa deste mesmo
            // subtotal (com promoção/desconto de categoria já aplicados)
            // para checar coupons.minimum_order_value e calcular o
            // desconto.
            $subtotalCents = $this->calculateCartSubtotalCents($tenant->id, $dto->items, $existingClient);

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

            // Guard 5: cashback (roadmap Delivery, Fase 5) — opcional, só
            // roda quando dto->useCashback=true e o plano do tenant permite
            // a funcionalidade (defesa em profundidade, mesmo espírito do
            // guard de 'storefront' no início). Teto = min(saldo disponível
            // real, % configurada do subtotal já líquido de cupom) —
            // reserveRedemption() é quem de fato garante que nunca resgata
            // mais do que o saldo real em banco permite.
            $cashbackReservation = ['reserved_cents' => 0, 'redemption_ids' => []];

            if (
                $dto->useCashback
                && $settings->cashback_enabled
                && $settings->cashback_redeem_max_percentage !== null
                && $this->permissionService->tenantPlanAllowsFunctionality($tenant->id, 'cashback')
            ) {
                $subtotalPostCouponCents = max(0, $subtotalCents - $discountAmountCents);
                $redeemCapCents = (int) round(
                    $subtotalPostCouponCents * ((float) $settings->cashback_redeem_max_percentage) / 100
                );

                if ($redeemCapCents > 0) {
                    $cashbackReservation = $this->cashbackService->reserveRedemption(
                        $tenant->id,
                        $customer->id,
                        $redeemCapCents
                    );
                }
            }

            $clientUuid = $this->resolveClientUuid($tenant->id, $customer, $dto);

            // unit_price explícito por item (prioridade máxima sobre a
            // resolução interna de OrderService::create(), já documentado no
            // DTO desde a Fase 1) — garante que promoção/desconto de
            // categoria resolvidos AQUI (resolveEffectiveUnitPrice()) sejam
            // exatamente os praticados no pedido criado, sem depender de
            // OrderService recalcular (ele nem sabe de ProductPromotion).
            $items = $this->resolveItemsWithEffectivePrice($tenant->id, $dto->items, $existingClient);

            $orderDto = new CreateOrderDTO(
                tenantId: $tenant->id,
                clientUuid: $clientUuid,
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
                cashbackRedeemedAmount: $cashbackReservation['reserved_cents'] / 100,
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

            // Vincula as CashbackRedemption reservadas pelo Guard 5 (criadas
            // com order_id nulo, antes do Order existir) ao pedido recém-
            // criado — mesma transação, nunca fica órfão de fato.
            $this->cashbackService->attachRedemptionToOrder($cashbackReservation['redemption_ids'], $order->id);

                return $order;
            });
        });
    }

    /**
     * Achado de code review: a unique antiga em final_customer_tenant_links
     * era (final_customer_id, client_id) — não impedia 2 requisições
     * concorrentes de checkout criarem 2 Client diferentes pro mesmo par
     * (final_customer_id, tenant_id) na primeira compra desse cliente
     * nessa loja (client_id sempre novo, satisfaz a unique antiga
     * trivialmente). Migration nova adiciona unique(final_customer_id,
     * tenant_id); aqui capturamos a violação da corrida perdedora e
     * reaproveitamos o link da vencedora, em vez de deixar 2 Client
     * órfãos/duplicados para o mesmo cliente na mesma loja.
     */
    private function resolveClientUuid(int $tenantId, FinalCustomer $customer, StorefrontCheckoutDTO $dto): string
    {
        $existing = $this->findExistingClient($tenantId, $customer);

        if ($existing) {
            return $existing->uuid;
        }

        [$estadoUuid, $cidadeUuid, $bairroUuid, $logradouro] = $this->resolveClientAddress($tenantId, $dto);

        // is_trusted: false — diferente do default do formulário do staff
        // (true): cliente autoatendido, ainda não verificado por ninguém
        // da equipe.
        $client = $this->clientService->create(new CreateClientDTO(
            tenantId: $tenantId,
            name: $dto->clientName,
            lastName: $dto->clientLastName,
            phonePrimary: $dto->clientPhone,
            phoneSecondary: null,
            notes: null,
            isTrusted: false,
            isActive: true,
            diaIdealUuid: null,
            periodoIdealUuid: null,
            estadoUuid: $estadoUuid,
            cidadeUuid: $cidadeUuid,
            bairroUuid: $bairroUuid,
            logradouro: $logradouro,
            numero: $dto->numero,
            complemento: $dto->complemento,
            cep: $dto->cep,
        ));

        try {
            // confirmed_at = now(): a prova de posse aqui é o próprio OTP já
            // verificado (customer.jwt), não um order_uuid pré-existente
            // como no fluxo de PortalLinkService::link().
            $this->linkRepository->create([
                'final_customer_id' => $customer->id,
                'tenant_id' => $tenantId,
                'client_id' => $client->id,
                'confirmed_at' => now(),
            ]);
        } catch (QueryException $e) {
            if ((int) $e->getCode() !== 23000) {
                throw $e;
            }

            // Perdemos a corrida: outra requisição concorrente já criou o
            // link pra esse (final_customer_id, tenant_id) entre o SELECT
            // acima e este INSERT. Descarta o Client órfão que acabamos de
            // criar (sem link nenhum, seguro de remover) e reaproveita o
            // client_id da vencedora.
            $client->delete();

            $winningLink = FinalCustomerTenantLink::where('final_customer_id', $customer->id)
                ->where('tenant_id', $tenantId)
                ->firstOrFail();

            $winningLink->loadMissing('client');

            return $winningLink->client->uuid;
        }

        return $client->uuid;
    }

    /**
     * Resolve o endereço a persistir num Client NOVO: clients.endereco_id é
     * NOT NULL no banco (todo Client precisa de UM endereço, mesmo sem
     * entrega) — quando o checkout é pickup e o cliente não informou
     * endereço (fluxo esperado do frontend nesse caso), reaproveita o
     * endereço da própria loja (StoreAddressService) em vez de um endereço
     * de entrega que não existe. Loja com pickup habilitado mas sem
     * endereço próprio configurado ainda bloqueia com mensagem clara, nunca
     * quebra com erro de FK.
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
     * Busca só-leitura do Client já vinculado a este FinalCustomer nesta
     * loja, sem nenhum side-effect (não cria nada) — usada tanto pelo
     * guard 2 (pedido mínimo, precisa do preço com desconto de categoria
     * quando o cliente já existe) quanto por resolveClientUuid() (que só
     * cria um Client novo quando esta busca não encontra nada).
     */
    private function findExistingClient(int $tenantId, FinalCustomer $customer): ?Client
    {
        $link = FinalCustomerTenantLink::where('final_customer_id', $customer->id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$link) {
            return null;
        }

        $link->loadMissing('client');

        return $link->client;
    }

    /**
     * Soma dos itens do carrinho em centavos, já usando o preço EFETIVO
     * (resolveEffectiveUnitPrice() — promoção sempre vence, senão preço de
     * categoria, senão base). Achado de code review da Fase 2: usar sempre
     * o Product.price BASE, mesmo para um cliente já vinculado com desconto
     * de categoria aplicável, podia liberar um checkout cujo total final
     * ficasse abaixo do mínimo configurado — por isso o guard de mínimo
     * precisa do MESMO preço que será praticado no pedido. Mesmo cuidado de
     * centavos/arredondamento de OrderService::create() — evita erro de
     * ponto flutuante na soma.
     *
     * @param array<int, array{product_uuid: string, quantity: float, options?: array<int, array{product_option_uuid: string, quantity?: int}>}> $items
     */
    private function calculateCartSubtotalCents(int $tenantId, array $items, ?Client $client): int
    {
        $totalCents = 0;

        foreach ($items as $item) {
            $product = Product::where('uuid', $item['product_uuid'])
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->firstOrFail();

            $unitPrice = $this->resolveEffectiveUnitPrice($product, $client, (float) $item['quantity']);

            $unitPriceCents = (int) round($unitPrice * 100);
            $totalCents += (int) round($unitPriceCents * (float) $item['quantity']);
            $totalCents += $this->calculateItemOptionsCents($item['options'] ?? [], (float) $item['quantity']);
        }

        return $totalCents;
    }

    /**
     * Soma o preço dos adicionais (product_options) escolhidos num item —
     * usada tanto pelo guard de pedido mínimo/cupom (calculateCartSubtotalCents)
     * quanto implicitamente reproduzida por OrderService::resolveOrderItemOptions()
     * na criação do pedido em si. Preço/quantidade inválidos (opção
     * inexistente/indisponível) são ignorados aqui de propósito — a
     * validação de existência já é feita no Request, e a resolução final e
     * autoritativa acontece dentro de OrderService::create(); esta soma é
     * só para o guard de elegibilidade rodar com o valor certo antes de
     * chegar lá.
     *
     * @param array<int, array{product_option_uuid: string, quantity?: int}> $options
     */
    private function calculateItemOptionsCents(array $options, float $itemQuantity): int
    {
        if ($options === []) {
            return 0;
        }

        $optionUuids = array_column($options, 'product_option_uuid');

        $prices = ProductOption::whereIn('uuid', $optionUuids)
            ->whereNull('deleted_at')
            ->pluck('price', 'uuid');

        $totalCents = 0;

        foreach ($options as $option) {
            $price = $prices->get($option['product_option_uuid']);

            if ($price === null) {
                continue;
            }

            $optionQuantity = (int) ($option['quantity'] ?? 1);

            $totalCents += (int) round((float) $price * 100) * $optionQuantity * (int) $itemQuantity;
        }

        return $totalCents;
    }

    /**
     * Reaproveita resolveEffectiveUnitPrice() para montar o array de itens
     * do CreateOrderDTO com unit_price explícito por item — garante que o
     * pedido criado por OrderService::create() pratique exatamente o mesmo
     * preço que passou pelo guard de mínimo acima, sem duplicar a
     * resolução de preço numa segunda camada.
     *
     * @param array<int, array{product_uuid: string, quantity: float, notes?: string}> $items
     * @return array<int, array{product_uuid: string, quantity: float, unit_price: float, notes: ?string}>
     */
    private function resolveItemsWithEffectivePrice(int $tenantId, array $items, ?Client $client): array
    {
        return array_map(function (array $item) use ($tenantId, $client) {
            $product = Product::where('uuid', $item['product_uuid'])
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->firstOrFail();

            return [
                'product_uuid' => $item['product_uuid'],
                'quantity' => $item['quantity'],
                'unit_price' => $this->resolveEffectiveUnitPrice($product, $client, (float) $item['quantity']),
                'notes' => isset($item['notes']) && trim((string) $item['notes']) !== '' ? trim((string) $item['notes']) : null,
                // Achado de code review (2026-07-28): esta função descartava
                // silenciosamente as opções escolhidas no checkout público —
                // eram validadas pelo Request mas nunca chegavam a
                // OrderService::create()/resolveOrderItemOptions(), então
                // nenhum adicional escolhido pelo cliente final era
                // realmente aplicado ao pedido. `options` já está no shape
                // exato que OrderService espera ({product_option_uuid,
                // quantity}), mesmo formato usado por StoreOrderRequest.
                'options' => $item['options'] ?? [],
            ];
        }, $items);
    }

    /**
     * Preço efetivo de venda pro público:
     * 1. Promoção ativa (ProductPromotionService::findActivePromoPrice())
     *    SEMPRE vence, quando existir — é o preço de venda público que o
     *    tenant decidiu, mesmo pra um cliente com desconto de categoria
     *    aplicável ou quantidade de atacado.
     * 2. Senão, preço de categoria via ProductPricingService (quando
     *    $client já é conhecido e tem override aplicável) — se existir,
     *    USA esse valor e NÃO aplica atacado (decisão de produto travada:
     *    atacado só pra quem NÃO tem categoria; categoria sempre vence).
     * 3. Senão, atacado por quantidade (roadmap Loja) — quando o produto
     *    tem os dois campos configurados e a quantidade atinge o mínimo.
     * 4. Senão, Product.price base.
     */
    public function resolveEffectiveUnitPrice(Product $product, ?Client $client, float $quantity): float
    {
        $promoPrice = $this->productPromotionService->findActivePromoPrice($product);

        if ($promoPrice !== null) {
            return $promoPrice;
        }

        $categoryPrice = $this->pricingService->resolveCategoryPrice($product, $client);

        if ($categoryPrice !== null) {
            return $categoryPrice;
        }

        if (
            $product->wholesale_min_quantity !== null
            && $product->wholesale_price !== null
            && $quantity >= (float) $product->wholesale_min_quantity
        ) {
            return (float) $product->wholesale_price;
        }

        return (float) $product->price;
    }
}
