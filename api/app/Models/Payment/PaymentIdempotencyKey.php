<?php

namespace App\Models\Payment;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * Controle técnico de idempotência de operações financeiras contra o PSP
 * (Mercado Pago). Não estende BaseModel de propósito: não tem
 * created_by/updated_by/soft delete, é gerado/atualizado pelo próprio
 * backend (MercadoPagoPaymentProvider/reconciliação), não por ação direta
 * de usuário staff — mesmo raciocínio já usado em WebhookDelivery/AuditLog.
 * `uuid` (2026-07-24) é só o identificador público exposto ao painel
 * administrativo de pendências — HasUuid reaproveitado sem herdar BaseModel.
 */
class PaymentIdempotencyKey extends Model
{
    use HasUuid;

    protected $table = 'payment_idempotency_keys';

    protected $guarded = ['id'];

    protected $casts = [
        'provider_response' => 'array',
        'locked_until' => 'datetime',
    ];

    public function isLockActive(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }
}
