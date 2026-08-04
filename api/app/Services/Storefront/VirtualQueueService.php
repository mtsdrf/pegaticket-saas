<?php

namespace App\Services\Storefront;

use App\Models\Event\Event;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Storefront\VirtualQueueEntry;
use Illuminate\Support\Facades\DB;

/**
 * Fila virtual para alta demanda (roadmap Fase 7) — quando um evento tem
 * `high_demand_mode=true`, StorefrontHoldService::createHold() deixa de
 * reservar na hora e exige uma entrada admitida nesta fila para o
 * `session_token` do comprador. AdmitVirtualQueueEntriesCommand promove
 * lotes de `waiting` para `admitted` respeitando
 * `events.virtual_queue_admission_batch_size` (default técnico não
 * validado com o usuário).
 */
class VirtualQueueService
{
    /**
     * Janela de validade de uma admissão (default técnico, não validado
     * com o usuário): depois desse tempo sem o comprador concluir o hold,
     * a admissão expira e o slot volta a ficar disponível para o próximo
     * lote. Alinhado à mesma ordem de grandeza do TTL padrão de hold
     * (StorefrontHoldService::DEFAULT_HOLD_TTL_MINUTES=15) com folga.
     */
    public const ADMISSION_WINDOW_MINUTES = 20;

    public function __construct(
        private StorefrontCatalogService $catalogService,
    ) {}

    /**
     * Ponto único de entrada usado pelo endpoint de polling: cria a
     * entrada na primeira chamada (posição = próxima da fila) e retorna o
     * status atual em todas as chamadas seguintes, sem duplicar linha por
     * session_token+evento (uniq_virtual_queue_tenant_event_session).
     */
    public function enterOrStatus(string $slug, string $eventSlug, string $sessionToken, ?FinalCustomer $customer = null): array
    {
        $tenant = $this->catalogService->findTenantBySlug($slug);
        $event = Event::query()
            ->where('tenant_id', $tenant->id)
            ->where('slug', $eventSlug)
            ->whereNull('deleted_at')
            ->firstOrFail();

        if (! $event->high_demand_mode) {
            return $this->statusPayload($event, null, 0);
        }

        $entry = DB::transaction(function () use ($tenant, $event, $sessionToken, $customer) {
            $existing = VirtualQueueEntry::query()
                ->where('tenant_id', $tenant->id)
                ->where('event_id', $event->id)
                ->where('session_token', $sessionToken)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $nextPosition = ((int) VirtualQueueEntry::query()
                ->where('tenant_id', $tenant->id)
                ->where('event_id', $event->id)
                ->max('position')) + 1;

            return VirtualQueueEntry::create([
                'tenant_id' => $tenant->id,
                'event_id' => $event->id,
                'final_customer_id' => $customer?->id,
                'session_token' => $sessionToken,
                'position' => $nextPosition,
                'status' => VirtualQueueEntry::STATUS_WAITING,
            ]);
        });

        $waitingAhead = $entry->status === VirtualQueueEntry::STATUS_WAITING
            ? VirtualQueueEntry::query()
                ->where('tenant_id', $tenant->id)
                ->where('event_id', $event->id)
                ->where('status', VirtualQueueEntry::STATUS_WAITING)
                ->where('position', '<', $entry->position)
                ->count()
            : 0;

        return $this->statusPayload($event, $entry, $waitingAhead);
    }

    /**
     * Usado por StorefrontHoldService::createHold() — só permite criar o
     * hold de verdade quando o session_token está admitido para este
     * evento. Eventos sem high_demand_mode sempre retornam true (nunca
     * afeta o fluxo padrão).
     */
    public function isAdmitted(int $tenantId, int $eventId, string $sessionToken): bool
    {
        return VirtualQueueEntry::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('session_token', $sessionToken)
            ->where('status', VirtualQueueEntry::STATUS_ADMITTED)
            ->exists();
    }

    private function statusPayload(Event $event, ?VirtualQueueEntry $entry, int $waitingAhead): array
    {
        return [
            'high_demand_mode' => (bool) $event->high_demand_mode,
            'status' => $entry?->status ?? VirtualQueueEntry::STATUS_ADMITTED,
            'position' => $entry?->position,
            'waiting_ahead' => $waitingAhead,
            'admitted_at' => $entry?->admitted_at,
        ];
    }
}
