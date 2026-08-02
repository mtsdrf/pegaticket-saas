<?php

namespace App\DTOs\Sale;

class CreateSaleDTO
{
    /**
     * @param array<int, array{product_uuid: string, quantity: float, unit_price?: float, notes?: string, attendee_data?: array<int, array{name?: string, document?: string}>}> $items
     *   attendee_data (spec 5.10 Etapa 2) é opcional e só considerado para
     *   item de ticket_type — TicketIssuanceService consome 1 registro por
     *   Ticket emitido, na ordem informada.
     *   unit_price é opcional — quando ausente, SaleService::create() usa
     *   o Product.price atual (comportamento padrão, nunca confia no
     *   request); quando presente, sobrescreve o preço praticado do item.
     *   notes é opcional — recado por item (ex: "sem cebola"), distinto de
     *   $notes (recado do pedido inteiro, abaixo).
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly string $finalCustomerUuid,
        public readonly bool $isInstallment,
        public readonly ?int $installmentsCount,
        public readonly ?string $notes,
        public readonly array $items,
        public readonly string $origin = 'staff',
        public readonly string $status = 'confirmed',
        public readonly float $serviceFee = 0.0,
        public readonly ?int $couponId = null,
        public readonly float $discountAmount = 0.0,
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
            finalCustomerUuid: $data['final_customer_uuid'],
            isInstallment: (bool) ($data['is_installment'] ?? false),
            installmentsCount: isset($data['installments_count']) ? (int) $data['installments_count'] : null,
            notes: $data['notes'] ?? null,
            items: $data['items'],
            origin: $data['origin'] ?? 'staff',
            status: $data['status'] ?? 'confirmed',
            serviceFee: (float) ($data['service_fee'] ?? 0.0),
            couponId: isset($data['coupon_id']) ? (int) $data['coupon_id'] : null,
            discountAmount: (float) ($data['discount_amount'] ?? 0.0),
            paymentMethod: $data['payment_method'] ?? null,
            needsChange: (bool) ($data['needs_change'] ?? false),
            changeForAmount: isset($data['change_for_amount']) ? (float) $data['change_for_amount'] : null,
        );
    }
}
