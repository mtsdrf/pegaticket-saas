<?php

namespace App\Repositories\Contracts;

use App\Models\Payment\PaymentIdempotencyKey;
use App\Support\Payment\IdempotencyOutcome;
use Illuminate\Support\Collection;

/**
 * Idempotência persistida de operações financeiras contra o PSP (Mercado
 * Pago). Garante no máximo 1 tentativa "em voo" por operação lógica
 * (`tenant_id` + `operation`) e nunca gera uma chave nova para a MESMA
 * operação enquanto ela ainda não estiver decisivamente resolvida
 * (sucesso ou falha clara de dado).
 */
interface IdempotencyRepositoryInterface
{
    /**
     * Resolve (ou cria) a tentativa "em voo" para a operação lógica.
     * Chamado SEMPRE fora de qualquer transação ambiente do caller — a
     * durabilidade desta chamada é o que impede um retry de gerar uma
     * chave nova quando a transação de negócio do caller reverte por
     * causa de um timeout/erro do PSP.
     */
    public function findOrCreatePending(int $tenantId, string $operation): IdempotencyOutcome;

    /**
     * Marca a operação como concluída com sucesso. `$providerResponse`
     * carrega só os dados mínimos necessários para reconciliação futura
     * (ex.: id remoto criado), nunca payload sensível bruto.
     *
     * @param array<string, mixed> $providerResponse
     */
    public function markSucceeded(PaymentIdempotencyKey $record, array $providerResponse): void;

    /**
     * Marca a operação como falha DECISIVA (erro de dado/validação claro
     * do PSP) — libera uma nova tentativa imediata (chave nova) na
     * próxima chamada de `findOrCreatePending` para a mesma operação.
     */
    public function markFailed(PaymentIdempotencyKey $record): void;

    /**
     * Tentativas `pending` com lock expirado — candidatas a reconciliação
     * ativa (`payments:reconcile-idempotency`).
     *
     * @return Collection<int, PaymentIdempotencyKey>
     */
    public function findExpiredPending(int $limit = 100): Collection;
}
