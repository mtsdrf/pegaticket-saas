<?php

namespace App\DTOs\Order;

class CreateOrderDTO
{
    /**
     * @param array<int, array{product_uuid: string, quantity: float, unit_price?: float, notes?: string}> $items
     *   unit_price é opcional — quando ausente, OrderService::create() usa
     *   o Product.price atual (comportamento padrão, nunca confia no
     *   request); quando presente, sobrescreve o preço praticado do item.
     *   notes é opcional — recado por item (ex: "sem cebola"), distinto de
     *   $notes (recado do pedido inteiro, abaixo).
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly string $clientUuid,
        public readonly ?string $stockLocationUuid,
        public readonly bool $isInstallment,
        public readonly ?int $installmentsCount,
        public readonly ?string $notes,
        public readonly ?string $expectedDeliveryDate,
        public readonly bool $markAsDelivered,
        public readonly bool $markAsPaid,
        public readonly array $items,
        public readonly string $origin = 'staff',
        public readonly string $status = 'confirmed',
        public readonly bool $reserveStock = true,
        public readonly float $deliveryFee = 0.0,
        public readonly float $serviceFee = 0.0,
        public readonly ?int $couponId = null,
        public readonly float $discountAmount = 0.0,
        // Retirada na loja (roadmap Delivery) — 'delivery'|'pickup', persistido
        // no pedido. default 'delivery' preserva 100% o fluxo staff existente
        // (sem conceito de retirada/entrega antes desta feature).
        public readonly string $fulfillmentType = 'delivery',
        // Meio de pagamento pretendido pelo cliente no checkout público
        // (StorefrontCheckoutDTO->paymentMethod) — só informativo, persistido
        // no pedido a partir desta feature. null preserva o fluxo staff, que
        // não coleta esse dado.
        public readonly ?string $paymentMethod = null,
        // Troco (checkout público, pagamento em dinheiro) —
        // StorefrontCheckoutDTO->needsChange/changeForAmount. default false
        // preserva 100% os fluxos existentes.
        public readonly bool $needsChange = false,
        public readonly ?float $changeForAmount = null,
    ) {
    }

    public static function fromArray(array $data, int $tenantId): self
    {
        return new self(
            tenantId: $tenantId,
            clientUuid: $data['client_uuid'],
            stockLocationUuid: $data['stock_location_uuid'] ?? null,
            isInstallment: (bool) ($data['is_installment'] ?? false),
            installmentsCount: isset($data['installments_count']) ? (int) $data['installments_count'] : null,
            notes: $data['notes'] ?? null,
            expectedDeliveryDate: $data['expected_delivery_date'] ?? null,
            markAsDelivered: (bool) ($data['mark_as_delivered'] ?? false),
            markAsPaid: (bool) ($data['mark_as_paid'] ?? false),
            items: $data['items'],
            origin: $data['origin'] ?? 'staff',
            status: $data['status'] ?? 'confirmed',
            reserveStock: (bool) ($data['reserve_stock'] ?? true),
            deliveryFee: (float) ($data['delivery_fee'] ?? 0.0),
            serviceFee: (float) ($data['service_fee'] ?? 0.0),
            couponId: isset($data['coupon_id']) ? (int) $data['coupon_id'] : null,
            discountAmount: (float) ($data['discount_amount'] ?? 0.0),
            fulfillmentType: $data['fulfillment_type'] ?? 'delivery',
            paymentMethod: $data['payment_method'] ?? null,
            needsChange: (bool) ($data['needs_change'] ?? false),
            changeForAmount: isset($data['change_for_amount']) ? (float) $data['change_for_amount'] : null,
        );
    }
}
