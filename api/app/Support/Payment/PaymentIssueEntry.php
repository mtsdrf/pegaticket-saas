<?php

namespace App\Support\Payment;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Item normalizado da listagem administrativa de pendências de pagamento
 * (`GET /payments/issues`, roadmap 2026-07-24) — junta 4 fontes de schema
 * bem diferente (Payment, PaymentIdempotencyKey, Invoice, WebhookEvent) num
 * único formato para o staff da PegaTicket, sem misturar a modelagem de
 * domínio de cada uma.
 */
final class PaymentIssueEntry implements Arrayable
{
    /**
     * @param array{uuid: string, name: string}|null $tenant
     * @param array<string, mixed> $detail
     */
    public function __construct(
        public readonly string $issueType,
        public readonly string $reference,
        public readonly ?array $tenant,
        public readonly ?string $amount,
        public readonly string $status,
        public readonly \DateTimeInterface $occurredAt,
        public readonly bool $reprocessable,
        public readonly array $detail = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'issue_type' => $this->issueType,
            'reference' => $this->reference,
            'tenant' => $this->tenant,
            'amount' => $this->amount,
            'status' => $this->status,
            'occurred_at' => \Illuminate\Support\Carbon::parse($this->occurredAt)->toIso8601String(),
            'reprocessable' => $this->reprocessable,
            'detail' => $this->detail,
        ];
    }
}
