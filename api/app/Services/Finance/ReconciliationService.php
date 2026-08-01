<?php

namespace App\Services\Finance;

use App\Models\Sale\Sale;
use App\Models\Subscription\Payment;
use App\Models\Subscription\WebhookEvent;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Apoio de leitura à conciliação financeira do tenant (roadmap A3.12).
 * Sem tabela própria — agrega `payments`/`refunds`/`webhook_events`
 * (já existentes desde o roadmap 2A/1B), mesmo espírito de AnalyticsService/
 * ReportService: Service consulta direto via Eloquent/DB, sem Repository
 * dedicado, porque é leitura agregada multi-tabela, não CRUD de uma
 * entidade própria (padrão já estabelecido nos módulos de relatório deste
 * projeto).
 *
 * Escopo por tenant: `payments` é polimórfico (payable_type=Sale|Invoice).
 * Conciliação do tenant enxerga só pagamentos de PEDIDO (cliente final →
 * tenant), nunca fatura de assinatura (PegaTicket → tenant, sem tenant_id
 * direto em `payments`) — por isso o filtro é sempre
 * `payable_type=Sale::class` + `orders.tenant_id`.
 *
 * Casamento com webhook_events é best-effort em PHP (não em SQL): a tabela
 * não tem FK pra `payments`, só (provider, external_id) — o vínculo real é
 * o payload conter o `provider_charge_id`. Sem PSP real ligado ainda
 * (PaymentWebhookController sempre responde 501), a lista tende a vir vazia
 * na prática; a estrutura já fica pronta pra quando um provedor real
 * processar webhooks de verdade.
 */
class ReconciliationService
{
    public function list(int $tenantId, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Payment::query()
            ->whereNull('deleted_at')
            ->where('payable_type', Sale::class)
            ->whereIn('payable_id', function ($sub) use ($tenantId) {
                $sub->select('id')
                    ->from('orders')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at');
            })
            ->with(['refunds' => fn($q) => $q->whereNull('deleted_at'), 'payable']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['method'])) {
            $query->where('method', $filters['method']);
        }

        if (!empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        $paginator = $query->orderByDesc('id')->paginate($perPage);

        $this->attachMatchedWebhookEvents($paginator->getCollection());

        return $paginator;
    }

    /**
     * Anexa `matched_webhook_event` (atributo dinâmico, lido pelo Resource)
     * a cada Payment da página atual. Busca só os providers presentes na
     * página, para não varrer `webhook_events` inteira a cada chamada.
     */
    private function attachMatchedWebhookEvents($payments): void
    {
        $providers = $payments->pluck('provider')->filter()->unique()->values()->all();

        if (empty($providers)) {
            return;
        }

        $events = WebhookEvent::whereIn('provider', $providers)
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        foreach ($payments as $payment) {
            $chargeId = $payment->provider_charge_id;

            $match = $chargeId === null
                ? null
                : $events->first(function (WebhookEvent $event) use ($payment, $chargeId) {
                    if ($event->provider !== $payment->provider) {
                        return false;
                    }

                    $payload = $event->payload ?? [];

                    return ($payload['provider_charge_id'] ?? null) === $chargeId
                        || ($payload['charge_id'] ?? null) === $chargeId
                        || $event->external_id === $chargeId;
                });

            $payment->setAttribute('matched_webhook_event', $match);
        }
    }

    public function summary(int $tenantId, array $filters): array
    {
        $base = Payment::query()
            ->whereNull('payments.deleted_at')
            ->where('payable_type', Sale::class)
            ->whereIn('payable_id', function ($sub) use ($tenantId) {
                $sub->select('id')
                    ->from('orders')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at');
            });

        if (!empty($filters['from'])) {
            $base->whereDate('created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $base->whereDate('created_at', '<=', $filters['to']);
        }

        $rows = (clone $base)
            ->select('status', DB::raw('COUNT(*) as total_count'), DB::raw('SUM(amount) as total_amount'))
            ->groupBy('status')
            ->get();

        $refundedAmount = (clone $base)
            ->join('refunds', 'refunds.payment_id', '=', 'payments.id')
            ->whereNull('refunds.deleted_at')
            ->sum('refunds.amount');

        return [
            'by_status' => $rows->map(fn($row) => [
                'status' => $row->status,
                'count' => (int) $row->total_count,
                'amount' => round((float) $row->total_amount, 2),
            ])->values()->all(),
            'total_refunded_amount' => round((float) $refundedAmount, 2),
        ];
    }
}
