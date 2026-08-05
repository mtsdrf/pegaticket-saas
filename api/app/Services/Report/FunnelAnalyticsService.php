<?php

namespace App\Services\Report;

use App\Models\Storefront\StorefrontFunnelEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Funil de conversão anônimo (roadmap A2) — agrega
 * `storefront_funnel_events` por etapa (sessões únicas por step) e calcula
 * a taxa de conversão entre etapas consecutivas, na ordem fixa de
 * StorefrontFunnelEvent::STEPS. Sem Sankey/visualização elaborada nesta
 * fase (decisão adiada, ver roadmap 5.4) — o frontend usa barras
 * horizontais decrescentes.
 */
class FunnelAnalyticsService
{
    private const STEP_LABELS = [
        StorefrontFunnelEvent::STEP_EVENT_VIEWED => 'Visualizou o evento',
        StorefrontFunnelEvent::STEP_TICKET_SELECTION_STARTED => 'Iniciou seleção de ingresso',
        StorefrontFunnelEvent::STEP_HOLD_CREATED => 'Criou reserva',
        StorefrontFunnelEvent::STEP_CHECKOUT_STARTED => 'Iniciou checkout',
        StorefrontFunnelEvent::STEP_PAYMENT_CONFIRMED => 'Pagamento confirmado',
    ];

    public function funnel(int $tenantId, ?string $from, ?string $to, ?string $eventUuid = null): array
    {
        [$fromDate, $toDate] = $this->resolvePeriod($from, $to);

        $query = DB::table('storefront_funnel_events')
            ->where('storefront_funnel_events.tenant_id', $tenantId)
            ->whereBetween('storefront_funnel_events.created_at', [$fromDate, $toDate->copy()->endOfDay()]);

        if ($eventUuid) {
            $query->join('events', 'events.id', '=', 'storefront_funnel_events.event_id')
                ->where('events.uuid', $eventUuid);
        }

        $sessionsByStep = (clone $query)
            ->select('storefront_funnel_events.step')
            ->selectRaw('COUNT(DISTINCT storefront_funnel_events.session_id) as session_count')
            ->groupBy('storefront_funnel_events.step')
            ->pluck('session_count', 'step');

        $steps = [];
        $previousCount = null;

        foreach (StorefrontFunnelEvent::STEPS as $step) {
            $count = (int) ($sessionsByStep[$step] ?? 0);

            $steps[] = [
                'step' => $step,
                'label' => self::STEP_LABELS[$step] ?? $step,
                'session_count' => $count,
                'conversion_from_previous_percentage' => $previousCount !== null && $previousCount > 0
                    ? round(($count / $previousCount) * 100, 2)
                    : null,
                'conversion_from_first_percentage' => null,
            ];

            $previousCount = $count;
        }

        $firstCount = $steps[0]['session_count'] ?? 0;

        foreach ($steps as &$step) {
            $step['conversion_from_first_percentage'] = $firstCount > 0
                ? round(($step['session_count'] / $firstCount) * 100, 2)
                : null;
        }
        unset($step);

        return [
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'event_uuid' => $eventUuid,
            'steps' => $steps,
        ];
    }

    private function resolvePeriod(?string $from, ?string $to): array
    {
        $toDate = $to ? Carbon::parse($to)->startOfDay() : now()->startOfDay();
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : $toDate->copy()->subDays(30);

        if ($fromDate->gt($toDate)) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        return [$fromDate, $toDate];
    }
}
