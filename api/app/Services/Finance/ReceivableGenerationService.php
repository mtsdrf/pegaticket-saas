<?php

namespace App\Services\Finance;

use App\Models\Event\Event;
use App\Models\Finance\LedgerEntry;
use App\Models\Finance\Receivable;
use App\Models\Sale\Sale;
use App\Models\Subscription\Payment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReceivableGenerationService
{
    public function __construct(
        private PlatformFinanceSettingsService $platformFinanceSettingsService,
    ) {}

    public function generateForSaleUuid(string $saleUuid): ?Receivable
    {
        return DB::transaction(function () use ($saleUuid) {
            $sale = Sale::query()
                ->where('uuid', $saleUuid)
                ->whereNull('deleted_at')
                ->with([
                    'items.ticketType.event',
                    'items.eventProduct.event',
                    'latestPayment',
                ])
                ->lockForUpdate()
                ->first();

            if ($sale === null || ! $sale->is_paid) {
                return null;
            }

            $existing = Receivable::query()
                ->where('sale_id', $sale->id)
                ->whereNull('deleted_at')
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $settings = $this->platformFinanceSettingsService->getCurrent();
            $payment = $sale->latestPayment;
            $primaryEvent = $this->resolvePrimaryEvent($sale);
            $eventEndsAt = $this->resolveEventEndsAt($sale, $primaryEvent);
            $availableAt = $eventEndsAt->copy()->addDays(max(1, $settings->default_settlement_offset_days))->startOfDay();

            $grossAmount = round((float) $sale->total_amount, 2);
            $platformFeeAmount = min($grossAmount, round((float) $settings->platform_fee_fixed_amount, 2));
            $processorFeeAmount = 0.0;
            $netAmountBeforeReserve = max(0, round($grossAmount - $platformFeeAmount - $processorFeeAmount, 2));

            $reserveEnabled = (bool) $settings->extra_reserve_enabled;
            $reserveAmount = $reserveEnabled
                ? min($netAmountBeforeReserve, round($netAmountBeforeReserve * ((float) $settings->extra_reserve_percentage / 100), 2))
                : 0.0;
            $netAmount = max(0, round($netAmountBeforeReserve - $reserveAmount, 2));
            $reserveReleaseAt = $reserveAmount > 0
                ? $availableAt->copy()->addDays(max(1, (int) $settings->extra_reserve_release_offset_days))
                : null;

            $receivable = Receivable::create([
                'tenant_id' => $sale->tenant_id,
                'sale_id' => $sale->id,
                'payment_id' => $payment?->id,
                'event_id' => $primaryEvent?->id,
                'status' => 'scheduled',
                'currency' => 'BRL',
                'gross_amount' => number_format($grossAmount, 2, '.', ''),
                'platform_fee_amount' => number_format($platformFeeAmount, 2, '.', ''),
                'processor_fee_amount' => number_format($processorFeeAmount, 2, '.', ''),
                'net_amount' => number_format($netAmount, 2, '.', ''),
                'reserve_amount' => number_format($reserveAmount, 2, '.', ''),
                'reserve_status' => $reserveAmount > 0 ? 'held' : 'none',
                'reserve_release_at' => $reserveReleaseAt,
                'settlement_reference' => 'event_end_d_plus_'.$settings->default_settlement_offset_days,
                'event_ends_at' => $eventEndsAt,
                'available_at' => $availableAt,
                'provider' => $payment?->provider,
                'provider_charge_id' => $payment?->provider_charge_id,
                'provider_split_id' => $payment?->metadata['split_id'] ?? null,
                'metadata' => [
                    'split_custody_enabled' => (bool) $settings->split_custody_enabled,
                    'extra_reserve_enabled' => $reserveEnabled,
                    'extra_reserve_percentage' => (float) $settings->extra_reserve_percentage,
                    'event_strategy' => $primaryEvent !== null ? 'primary_event_with_max_end' : 'sale_paid_at_fallback',
                    'pagbank_custody_release_scheduled' => $payment?->metadata['split_release_scheduled'] ?? null,
                ],
            ]);

            $this->createLedgerEntries($sale, $payment, $receivable);

            return $receivable;
        });
    }

    private function resolvePrimaryEvent(Sale $sale): ?Event
    {
        $events = $this->collectSaleEvents($sale);

        return $events->first();
    }

    private function resolveEventEndsAt(Sale $sale, ?Event $primaryEvent): CarbonInterface
    {
        $maxEvent = $this->collectSaleEvents($sale)
            ->filter(fn (Event $event) => $event->ends_at !== null)
            ->sortByDesc(fn (Event $event) => $event->ends_at?->getTimestamp() ?? 0)
            ->first();

        if ($maxEvent?->ends_at !== null) {
            return $maxEvent->ends_at->copy();
        }

        if ($primaryEvent?->ends_at !== null) {
            return $primaryEvent->ends_at->copy();
        }

        return ($sale->paid_at ?? now())->copy();
    }

    /**
     * @return Collection<int, Event>
     */
    private function collectSaleEvents(Sale $sale): Collection
    {
        return $sale->items
            ->map(fn ($item) => $item->ticketType?->event ?? $item->eventProduct?->event)
            ->filter()
            ->unique(fn (Event $event) => $event->id)
            ->values();
    }

    private function createLedgerEntries(Sale $sale, ?Payment $payment, Receivable $receivable): void
    {
        LedgerEntry::create([
            'tenant_id' => $sale->tenant_id,
            'sale_id' => $sale->id,
            'payment_id' => $payment?->id,
            'receivable_id' => $receivable->id,
            'direction' => 'credit',
            'entry_type' => 'receivable_gross',
            'amount' => $receivable->gross_amount,
            'occurred_at' => $sale->paid_at ?? now(),
            'description' => 'Recebivel bruto gerado a partir da venda paga.',
        ]);

        if ((float) $receivable->platform_fee_amount > 0) {
            LedgerEntry::create([
                'tenant_id' => $sale->tenant_id,
                'sale_id' => $sale->id,
                'payment_id' => $payment?->id,
                'receivable_id' => $receivable->id,
                'direction' => 'debit',
                'entry_type' => 'platform_fee',
                'amount' => $receivable->platform_fee_amount,
                'occurred_at' => $sale->paid_at ?? now(),
                'description' => 'Retencao da taxa fixa global da plataforma.',
            ]);
        }

        if ((float) $receivable->reserve_amount > 0) {
            LedgerEntry::create([
                'tenant_id' => $sale->tenant_id,
                'sale_id' => $sale->id,
                'payment_id' => $payment?->id,
                'receivable_id' => $receivable->id,
                'direction' => 'debit',
                'entry_type' => 'risk_reserve_hold',
                'amount' => $receivable->reserve_amount,
                'occurred_at' => $sale->paid_at ?? now(),
                'description' => 'Retencao de reserva de risco sobre o recebivel.',
            ]);
        }
    }
}
