<?php

namespace App\Repositories\Eloquent;

use App\Models\Payment\PaymentIdempotencyKey;
use App\Repositories\Contracts\IdempotencyRepositoryInterface;
use App\Support\Payment\IdempotencyOutcome;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IdempotencyRepository implements IdempotencyRepositoryInterface
{
    /**
     * TTL padrão do lock (2 minutos) — tempo suficiente para cobrir um
     * timeout de rede real (o adapter usa timeout de 15s por chamada)
     * sem travar o usuário por muito tempo se a tentativa original
     * realmente falhou rápido.
     */
    private const DEFAULT_LOCK_SECONDS = 120;

    public function findOrCreatePending(int $tenantId, string $operation): IdempotencyOutcome
    {
        // Transação CURTA e própria, deliberadamente chamada pelo caller
        // FORA de qualquer transação de negócio ambiente — commit
        // imediato, independente do que acontecer depois (chamada ao PSP,
        // persistência local). É essa durabilidade que impede um retry de
        // gerar uma chave nova quando a transação de negócio reverte por
        // causa de um timeout/erro do PSP.
        return DB::transaction(function () use ($tenantId, $operation) {
            $existing = PaymentIdempotencyKey::query()
                ->where('tenant_id', $tenantId)
                ->where('operation', $operation)
                ->lockForUpdate()
                ->first();

            if ($existing === null) {
                $record = PaymentIdempotencyKey::create([
                    'tenant_id' => $tenantId,
                    'operation' => $operation,
                    'idempotency_key' => (string) Str::uuid(),
                    'status' => 'pending',
                    'locked_until' => now()->addSeconds($this->lockSeconds()),
                ]);

                return new IdempotencyOutcome($record, IdempotencyOutcome::STATE_NEW);
            }

            if ($existing->status === 'succeeded') {
                return new IdempotencyOutcome($existing, IdempotencyOutcome::STATE_ALREADY_SUCCEEDED);
            }

            if ($existing->status === 'failed') {
                // Falha anterior foi DECISIVA (erro de dado/validação) —
                // libera uma tentativa nova imediata com uma chave NOVA
                // (nunca reaproveita a chave de uma tentativa já
                // claramente rejeitada pelo PSP).
                $existing->fill([
                    'idempotency_key' => (string) Str::uuid(),
                    'status' => 'pending',
                    'provider_response' => null,
                    'locked_until' => now()->addSeconds($this->lockSeconds()),
                ]);
                $existing->save();

                return new IdempotencyOutcome($existing, IdempotencyOutcome::STATE_NEW);
            }

            // status === 'pending'
            if ($existing->isLockActive()) {
                return new IdempotencyOutcome($existing, IdempotencyOutcome::STATE_LOCKED);
            }

            // Lock expirado sem confirmação (timeout ambíguo real). NUNCA
            // gera uma chave nova aqui — só a reconciliação ativa (busca
            // por external_reference no PSP) pode decidir com segurança.
            return new IdempotencyOutcome($existing, IdempotencyOutcome::STATE_AMBIGUOUS);
        });
    }

    public function markSucceeded(PaymentIdempotencyKey $record, array $providerResponse): void
    {
        $record->fill([
            'status' => 'succeeded',
            'provider_response' => $providerResponse,
            'locked_until' => null,
        ]);
        $record->save();
    }

    public function markFailed(PaymentIdempotencyKey $record): void
    {
        $record->fill([
            'status' => 'failed',
            'locked_until' => null,
        ]);
        $record->save();
    }

    public function findExpiredPending(int $limit = 100): Collection
    {
        return PaymentIdempotencyKey::query()
            ->where('status', 'pending')
            ->where(function ($query) {
                $query->whereNull('locked_until')->orWhere('locked_until', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    private function lockSeconds(): int
    {
        return max(1, (int) config('services.mercadopago.idempotency_lock_seconds', self::DEFAULT_LOCK_SECONDS));
    }
}
