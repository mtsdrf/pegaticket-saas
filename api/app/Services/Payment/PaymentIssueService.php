<?php

namespace App\Services\Payment;

use App\Exceptions\Payment\PaymentIssueNotFoundException;
use App\Exceptions\Payment\PaymentIssueNotReprocessableException;
use App\Models\Order\Order;
use App\Models\Payment\PaymentIdempotencyKey;
use App\Models\Subscription\Invoice;
use App\Models\Subscription\Payment;
use App\Models\Subscription\WebhookEvent;
use App\Models\Tenant\Tenant;
use App\Support\Payment\PaymentIssueEntry;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Visão cross-tenant, exclusiva do staff interno da PegaTicket, das pendências
 * de pagamento/assinatura que hoje só eram descobertas lendo log ou
 * esperando os comandos agendados rodarem (roadmap 2026-07-24). Junta 4
 * fontes com schema diferente (Payment divergente, idempotência ambígua,
 * fatura contestada, webhook não processado) num único feed paginado.
 *
 * O reprocessamento manual reaproveita EXATAMENTE a mesma decisão de
 * reconciliação usada pelos comandos agendados (PaymentReconciliationService)
 * — nunca duplica lógica de negócio aqui.
 */
class PaymentIssueService
{
    public const TYPE_PAYMENT_DIVERGENT = 'payment_divergent';
    public const TYPE_IDEMPOTENCY_AMBIGUOUS = 'idempotency_ambiguous';
    public const TYPE_INVOICE_DISPUTED = 'invoice_disputed';
    public const TYPE_WEBHOOK_FAILED = 'webhook_failed';

    public const TYPES = [
        self::TYPE_PAYMENT_DIVERGENT,
        self::TYPE_IDEMPOTENCY_AMBIGUOUS,
        self::TYPE_INVOICE_DISPUTED,
        self::TYPE_WEBHOOK_FAILED,
    ];

    /**
     * Cada fonte busca no máximo esse tanto antes de mesclar/ordenar/paginar
     * em memória — union heterogênea entre 4 tabelas de schema muito
     * diferente não compensa a complexidade de uma query SQL única. Painel
     * interno de operação, volume esperado baixo (pendência é exceção, não
     * regra); documentado como limitação consciente.
     */
    private const SOURCE_LIMIT = 500;

    private const STALE_WEBHOOK_MINUTES = 15;

    public function __construct(
        private PaymentReconciliationService $reconciliationService,
    ) {
    }

    public function list(array $filters, int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        $type = $filters['type'] ?? null;

        $entries = collect();

        if ($type === null || $type === self::TYPE_PAYMENT_DIVERGENT) {
            $entries = $entries->merge($this->paymentDivergentEntries());
        }

        if ($type === null || $type === self::TYPE_IDEMPOTENCY_AMBIGUOUS) {
            $entries = $entries->merge($this->idempotencyAmbiguousEntries());
        }

        if ($type === null || $type === self::TYPE_INVOICE_DISPUTED) {
            $entries = $entries->merge($this->invoiceDisputedEntries());
        }

        if ($type === null || $type === self::TYPE_WEBHOOK_FAILED) {
            $entries = $entries->merge($this->webhookFailedEntries());
        }

        $entries = $entries->sortByDesc(fn (PaymentIssueEntry $entry) => $entry->occurredAt->getTimestamp())
            ->values();

        $page = max(1, $page);
        $slice = $entries->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $slice,
            $entries->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );
    }

    public function reprocess(string $type, string $reference): array
    {
        return match ($type) {
            self::TYPE_PAYMENT_DIVERGENT => $this->reprocessPaymentDivergent($reference),
            self::TYPE_IDEMPOTENCY_AMBIGUOUS => $this->reprocessIdempotency($reference),
            self::TYPE_INVOICE_DISPUTED, self::TYPE_WEBHOOK_FAILED => throw new PaymentIssueNotReprocessableException(
                "issue type '{$type}' has no automated reconciliation, requires manual review"
            ),
            default => throw new PaymentIssueNotFoundException("unknown issue type: {$type}"),
        };
    }

    /**
     * @return Collection<int, PaymentIssueEntry>
     */
    private function paymentDivergentEntries(): Collection
    {
        $payments = Payment::query()
            ->whereNull('deleted_at')
            ->where('status', 'divergent')
            ->with('payable')
            ->orderByDesc('id')
            ->limit(self::SOURCE_LIMIT)
            ->get();

        $tenants = $this->tenantsFor($payments->map(fn (Payment $payment) => $payment->payable?->tenant_id));

        return $payments->map(function (Payment $payment) use ($tenants) {
            $tenantId = $payment->payable?->tenant_id;
            $reprocessable = $payment->provider === 'mercadopago'
                && $payment->payable_type === Order::class
                && $payment->provider_charge_id !== null;

            return new PaymentIssueEntry(
                issueType: self::TYPE_PAYMENT_DIVERGENT,
                reference: $payment->uuid,
                tenant: $this->tenantSummary($tenants, $tenantId),
                amount: (string) $payment->amount,
                status: $payment->status,
                occurredAt: $payment->updated_at ?? $payment->created_at,
                reprocessable: $reprocessable,
                detail: [
                    'provider' => $payment->provider,
                    'provider_charge_id' => $payment->provider_charge_id,
                    'payable_type' => $payment->payable_type === Order::class ? 'order' : 'invoice',
                    'method' => $payment->method,
                ],
            );
        });
    }

    /**
     * @return Collection<int, PaymentIssueEntry>
     */
    private function idempotencyAmbiguousEntries(): Collection
    {
        $records = PaymentIdempotencyKey::query()
            ->where('status', 'pending')
            ->where(function ($query) {
                $query->whereNull('locked_until')->orWhere('locked_until', '<=', now());
            })
            ->orderByDesc('id')
            ->limit(self::SOURCE_LIMIT)
            ->get();

        $tenants = $this->tenantsFor($records->pluck('tenant_id'));

        return $records->map(fn (PaymentIdempotencyKey $record) => new PaymentIssueEntry(
            issueType: self::TYPE_IDEMPOTENCY_AMBIGUOUS,
            reference: $record->uuid,
            tenant: $this->tenantSummary($tenants, $record->tenant_id),
            amount: null,
            status: $record->status,
            occurredAt: $record->updated_at ?? $record->created_at,
            reprocessable: true,
            detail: [
                'operation' => $record->operation,
                'locked_until' => $record->locked_until?->toIso8601String(),
            ],
        ));
    }

    /**
     * @return Collection<int, PaymentIssueEntry>
     */
    private function invoiceDisputedEntries(): Collection
    {
        $invoices = Invoice::query()
            ->whereNull('deleted_at')
            ->where('status', 'disputed')
            ->orderByDesc('id')
            ->limit(self::SOURCE_LIMIT)
            ->get();

        $tenants = $this->tenantsFor($invoices->pluck('tenant_id'));

        return $invoices->map(fn (Invoice $invoice) => new PaymentIssueEntry(
            issueType: self::TYPE_INVOICE_DISPUTED,
            reference: $invoice->uuid,
            tenant: $this->tenantSummary($tenants, $invoice->tenant_id),
            amount: (string) $invoice->amount_net,
            status: $invoice->status,
            occurredAt: $invoice->updated_at ?? $invoice->created_at,
            reprocessable: false,
            detail: [
                'competence_period' => $invoice->competence_period,
                'dispute_deadline_at' => $invoice->dispute_deadline_at?->toIso8601String(),
            ],
        ));
    }

    /**
     * @return Collection<int, PaymentIssueEntry>
     */
    private function webhookFailedEntries(): Collection
    {
        $events = WebhookEvent::query()
            ->whereNull('processed_at')
            ->where('created_at', '<=', now()->subMinutes(self::STALE_WEBHOOK_MINUTES))
            ->orderByDesc('id')
            ->limit(self::SOURCE_LIMIT)
            ->get();

        return $events->map(fn (WebhookEvent $event) => new PaymentIssueEntry(
            issueType: self::TYPE_WEBHOOK_FAILED,
            reference: 'webhook-event:' . $event->id,
            tenant: null,
            amount: null,
            status: 'unprocessed',
            occurredAt: $event->created_at,
            reprocessable: false,
            detail: [
                'provider' => $event->provider,
                'type' => $event->type,
                'external_id' => $event->external_id,
            ],
        ));
    }

    /**
     * @param Collection<int, int|null> $tenantIds
     * @return Collection<int, Tenant>
     */
    private function tenantsFor(Collection $tenantIds): Collection
    {
        $ids = $tenantIds->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Tenant::query()->whereIn('id', $ids)->get()->keyBy('id');
    }

    /**
     * @param Collection<int, Tenant> $tenants
     * @return array{uuid: string, name: string}|null
     */
    private function tenantSummary(Collection $tenants, ?int $tenantId): ?array
    {
        if ($tenantId === null || ! $tenants->has($tenantId)) {
            return null;
        }

        $tenant = $tenants->get($tenantId);

        return ['uuid' => $tenant->uuid, 'name' => $tenant->name];
    }

    private function reprocessPaymentDivergent(string $reference): array
    {
        $payment = Payment::query()
            ->whereNull('deleted_at')
            ->where('uuid', $reference)
            ->first();

        if (
            $payment === null
            || $payment->status !== 'divergent'
            || $payment->provider !== 'mercadopago'
            || $payment->payable_type !== Order::class
            || $payment->provider_charge_id === null
        ) {
            throw new PaymentIssueNotFoundException("payment issue not found or not reprocessable: {$reference}");
        }

        $payment = $this->reconciliationService->reconcileOrderPayment($payment);

        return ['reference' => $payment->uuid, 'status' => $payment->status];
    }

    private function reprocessIdempotency(string $reference): array
    {
        $record = PaymentIdempotencyKey::query()
            ->where('uuid', $reference)
            ->first();

        if ($record === null || $record->status !== 'pending' || $record->isLockActive()) {
            throw new PaymentIssueNotFoundException("idempotency issue not found or not reprocessable: {$reference}");
        }

        $this->reconciliationService->reconcileIdempotencyRecord($record);
        $record->refresh();

        return ['reference' => $record->uuid, 'status' => $record->status];
    }
}
