<?php

namespace App\Support\Payment;

use App\Models\Payment\PaymentIdempotencyKey;

/**
 * Resultado de `IdempotencyRepositoryInterface::findOrCreatePending()`.
 * Distingue os 4 estados possíveis de uma operação financeira contra o PSP:
 *
 * - NEW: nenhuma tentativa "em voo"/decidida — pode chamar o PSP com
 *   `record->idempotency_key`.
 * - ALREADY_SUCCEEDED: a operação já foi concluída com sucesso antes
 *   (mesma chave lógica) — o caller NUNCA deve chamar o PSP de novo.
 * - LOCKED: já existe uma tentativa `pending` recente (lock ainda válido)
 *   — provável concorrência/duplo-clique. O caller deve rejeitar a
 *   chamada com uma mensagem clara, sem tocar o PSP.
 * - AMBIGUOUS: existia uma tentativa `pending` mas o lock expirou sem
 *   confirmação (timeout de rede real) — o caller NÃO deve gerar uma nova
 *   tentativa cega; precisa de reconciliação (consulta ativa ao PSP por
 *   `external_reference`) antes de decidir.
 */
final class IdempotencyOutcome
{
    public const STATE_NEW = 'new';
    public const STATE_ALREADY_SUCCEEDED = 'already_succeeded';
    public const STATE_LOCKED = 'locked';
    public const STATE_AMBIGUOUS = 'ambiguous';

    public function __construct(
        public readonly PaymentIdempotencyKey $record,
        public readonly string $state,
    ) {
    }

    public function isNew(): bool
    {
        return $this->state === self::STATE_NEW;
    }

    public function isLocked(): bool
    {
        return $this->state === self::STATE_LOCKED;
    }

    public function isAmbiguous(): bool
    {
        return $this->state === self::STATE_AMBIGUOUS;
    }

    public function isAlreadySucceeded(): bool
    {
        return $this->state === self::STATE_ALREADY_SUCCEEDED;
    }

    public function key(): string
    {
        return (string) $this->record->idempotency_key;
    }
}
