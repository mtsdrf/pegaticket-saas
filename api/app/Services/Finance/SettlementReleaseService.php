<?php

namespace App\Services\Finance;

use App\Models\Finance\LedgerEntry;
use App\Models\Finance\Receivable;
use App\Models\Finance\Settlement;
use App\Models\Tenant\TenantSettings;
use App\Services\Payment\PagBankPaymentProvider;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class SettlementReleaseService
{
    public function __construct(
        private PagBankPaymentProvider $pagBankPaymentProvider,
    ) {}

    /**
     * @return array{settlements_seen:int,released:int,release_requested:int,skipped:int}
     */
    public function releaseDue(?CarbonInterface $cutoffAt = null, ?int $tenantId = null, ?string $settlementUuid = null): array
    {
        $cutoffAt = ($cutoffAt ?? now())->copy();

        $query = Settlement::query()
            ->whereNull('deleted_at')
            ->whereIn('status', ['scheduled', 'release_requested'])
            ->where('scheduled_for', '<=', $cutoffAt)
            ->when($tenantId !== null, fn ($builder) => $builder->where('tenant_id', $tenantId))
            ->when($settlementUuid !== null, fn ($builder) => $builder->where('uuid', $settlementUuid))
            ->orderBy('id');

        $settlementsSeen = 0;
        $released = 0;
        $releaseRequested = 0;
        $skipped = 0;

        foreach ($query->get() as $settlement) {
            $settlementsSeen++;

            $result = $this->releaseSettlement((string) $settlement->uuid);

            if ($result === 'released') {
                $released++;

                continue;
            }

            if ($result === 'release_requested') {
                $releaseRequested++;

                continue;
            }

            $skipped++;
        }

        return [
            'settlements_seen' => $settlementsSeen,
            'released' => $released,
            'release_requested' => $releaseRequested,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return array{settlements_seen:int,released:int,still_pending:int,skipped:int}
     */
    public function reconcileRequested(?int $tenantId = null, ?string $settlementUuid = null): array
    {
        $query = Settlement::query()
            ->whereNull('deleted_at')
            ->where('status', 'release_requested')
            ->when($tenantId !== null, fn ($builder) => $builder->where('tenant_id', $tenantId))
            ->when($settlementUuid !== null, fn ($builder) => $builder->where('uuid', $settlementUuid))
            ->orderBy('id');

        $settlementsSeen = 0;
        $released = 0;
        $stillPending = 0;
        $skipped = 0;

        foreach ($query->get() as $settlement) {
            $settlementsSeen++;

            $result = $this->reconcileRequestedSettlement((string) $settlement->uuid);

            if ($result === 'released') {
                $released++;

                continue;
            }

            if ($result === 'still_pending') {
                $stillPending++;

                continue;
            }

            $skipped++;
        }

        return [
            'settlements_seen' => $settlementsSeen,
            'released' => $released,
            'still_pending' => $stillPending,
            'skipped' => $skipped,
        ];
    }

    public function releaseSettlement(string $settlementUuid): string
    {
        // Passo (a): transação curta só para travar, ler e validar o estado atual.
        $prepared = DB::transaction(function () use ($settlementUuid) {
            /** @var Settlement|null $settlement */
            $settlement = Settlement::query()
                ->where('uuid', $settlementUuid)
                ->whereNull('deleted_at')
                ->with(['receivables'])
                ->lockForUpdate()
                ->first();

            if ($settlement === null || $settlement->receivables->isEmpty()) {
                return 'skipped';
            }

            if ($settlement->status === 'released') {
                return 'skipped';
            }

            $receiverAccountId = $this->resolveTenantReceiverAccountId((int) $settlement->tenant_id);
            $splitIds = $settlement->receivables
                ->pluck('provider_split_id')
                ->filter()
                ->unique()
                ->values();

            if ($splitIds->isEmpty() || $receiverAccountId === null) {
                return $this->markSettlementReleased(
                    $settlement,
                    releasedAt: now(),
                    metadata: [
                        'release_mode' => 'local_only',
                        'released_split_ids' => [],
                    ],
                );
            }

            return ['receiverAccountId' => $receiverAccountId, 'splitIds' => $splitIds];
        });

        if (is_string($prepared)) {
            return $prepared;
        }

        // Passo (c): chamadas HTTP ao PagBank fora de qualquer lock de banco.
        $receiverAccountId = $prepared['receiverAccountId'];
        $splitIds = $prepared['splitIds'];
        $releasedSplitIds = [];
        $releaseRequested = false;

        foreach ($splitIds as $splitId) {
            $splitPayload = $this->pagBankPaymentProvider->getSplit((string) $splitId);
            $receiver = $this->findReceiverByAccountId((array) ($splitPayload['receivers'] ?? []), $receiverAccountId);
            $custodyStatus = $receiver['configurations']['custody']['status'] ?? null;
            $releasedAt = $receiver['configurations']['custody']['release']['released_at'] ?? null;

            if ($custodyStatus === 'RELEASED') {
                $releasedSplitIds[] = (string) $splitId;

                continue;
            }

            $this->pagBankPaymentProvider->releaseSplitCustody((string) $splitId, [$receiverAccountId]);
            $afterReleasePayload = $this->pagBankPaymentProvider->getSplit((string) $splitId);
            $afterReceiver = $this->findReceiverByAccountId((array) ($afterReleasePayload['receivers'] ?? []), $receiverAccountId);
            $afterStatus = $afterReceiver['configurations']['custody']['status'] ?? null;
            $afterReleasedAt = $afterReceiver['configurations']['custody']['release']['released_at'] ?? null;

            if ($afterStatus === 'RELEASED' && $afterReleasedAt !== null) {
                $releasedSplitIds[] = (string) $splitId;

                continue;
            }

            $releaseRequested = true;
        }

        // Passo (d): segunda transação curta só para persistir, revalidando o estado atual
        // para não sobrescrever uma mudança concorrente ocorrida entre os dois locks.
        return DB::transaction(function () use ($settlementUuid, $releasedSplitIds, $releaseRequested) {
            /** @var Settlement|null $settlement */
            $settlement = Settlement::query()
                ->where('uuid', $settlementUuid)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if ($settlement === null || $settlement->status === 'released') {
                return 'skipped';
            }

            if ($releaseRequested) {
                $settlement->status = 'release_requested';
                $settlement->metadata = array_merge($settlement->metadata ?? [], [
                    'release_mode' => 'pagbank_split_custody',
                    'released_split_ids' => $releasedSplitIds,
                    'release_requested_at' => now()->toIso8601String(),
                ]);
                $settlement->save();

                Receivable::query()
                    ->where('settlement_id', $settlement->id)
                    ->update([
                        'status' => 'release_requested',
                        'updated_at' => now(),
                    ]);

                return 'release_requested';
            }

            return $this->markSettlementReleased(
                $settlement,
                releasedAt: now(),
                metadata: [
                    'release_mode' => 'pagbank_split_custody',
                    'released_split_ids' => $releasedSplitIds,
                ],
            );
        });
    }

    public function reconcileRequestedSettlement(string $settlementUuid): string
    {
        return DB::transaction(function () use ($settlementUuid) {
            /** @var Settlement|null $settlement */
            $settlement = Settlement::query()
                ->where('uuid', $settlementUuid)
                ->whereNull('deleted_at')
                ->with(['receivables'])
                ->lockForUpdate()
                ->first();

            if ($settlement === null || $settlement->status !== 'release_requested' || $settlement->receivables->isEmpty()) {
                return 'skipped';
            }

            $receiverAccountId = $this->resolveTenantReceiverAccountId((int) $settlement->tenant_id);
            $splitIds = $settlement->receivables
                ->pluck('provider_split_id')
                ->filter()
                ->unique()
                ->values();

            if ($splitIds->isEmpty() || $receiverAccountId === null) {
                return 'skipped';
            }

            $releasedSplitIds = [];
            $releasedAtCandidates = [];

            foreach ($splitIds as $splitId) {
                $splitPayload = $this->pagBankPaymentProvider->getSplit((string) $splitId);
                $receiver = $this->findReceiverByAccountId((array) ($splitPayload['receivers'] ?? []), $receiverAccountId);
                $status = $receiver['configurations']['custody']['status'] ?? null;
                $releasedAt = $receiver['configurations']['custody']['release']['released_at'] ?? null;

                if ($status === 'RELEASED') {
                    $releasedSplitIds[] = (string) $splitId;

                    if (is_string($releasedAt) && $releasedAt !== '') {
                        $releasedAtCandidates[] = $releasedAt;
                    }
                }
            }

            if (count($releasedSplitIds) !== $splitIds->count()) {
                $settlement->metadata = array_merge($settlement->metadata ?? [], [
                    'release_mode' => 'pagbank_split_custody',
                    'released_split_ids' => $releasedSplitIds,
                    'last_release_reconciliation_at' => now()->toIso8601String(),
                ]);
                $settlement->save();

                return 'still_pending';
            }

            $releasedAt = collect($releasedAtCandidates)
                ->filter()
                ->map(fn (string $value) => Carbon::parse($value))
                ->sort()
                ->last();

            return $this->markSettlementReleased(
                $settlement,
                releasedAt: $releasedAt instanceof CarbonInterface ? $releasedAt : now(),
                metadata: [
                    'release_mode' => 'pagbank_split_custody',
                    'released_split_ids' => $releasedSplitIds,
                    'last_release_reconciliation_at' => now()->toIso8601String(),
                ],
            );
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $receivers
     * @return array<string, mixed>|null
     */
    private function findReceiverByAccountId(array $receivers, string $accountId): ?array
    {
        foreach ($receivers as $receiver) {
            if (($receiver['account']['id'] ?? null) === $accountId) {
                return $receiver;
            }
        }

        return null;
    }

    private function resolveTenantReceiverAccountId(int $tenantId): ?string
    {
        $settings = TenantSettings::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->first();

        $accountId = trim((string) ($settings?->pagbank_receiver_account_id ?? ''));

        return $accountId !== '' ? $accountId : null;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function markSettlementReleased(Settlement $settlement, CarbonInterface $releasedAt, array $metadata): string
    {
        $settlement->status = 'released';
        $settlement->released_at = $releasedAt;
        $settlement->metadata = array_merge($settlement->metadata ?? [], $metadata, [
            'released_at' => $releasedAt->toIso8601String(),
        ]);
        $settlement->save();

        Receivable::query()
            ->where('settlement_id', $settlement->id)
            ->update([
                'status' => 'released',
                'updated_at' => now(),
            ]);

        $this->writeLedgerEntry($settlement, $releasedAt);

        return 'released';
    }

    private function writeLedgerEntry(Settlement $settlement, CarbonInterface $releasedAt): void
    {
        $existing = LedgerEntry::query()
            ->where('settlement_id', $settlement->id)
            ->where('entry_type', 'settlement_release')
            ->whereNull('deleted_at')
            ->first();

        if ($existing !== null) {
            return;
        }

        LedgerEntry::create([
            'tenant_id' => $settlement->tenant_id,
            'settlement_id' => $settlement->id,
            'direction' => 'debit',
            'entry_type' => 'settlement_release',
            'amount' => $settlement->net_amount,
            'occurred_at' => $releasedAt,
            'description' => 'Repasse liberado ao organizador.',
            'metadata' => [
                'settlement_code' => $settlement->code,
            ],
        ]);
    }
}
