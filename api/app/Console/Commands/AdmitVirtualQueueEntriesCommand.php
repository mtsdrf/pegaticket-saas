<?php

namespace App\Console\Commands;

use App\Models\Event\Event;
use App\Models\Storefront\VirtualQueueEntry;
use App\Services\Storefront\VirtualQueueService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fila virtual para alta demanda (roadmap Fase 7) — a cada minuto, para
 * cada evento com high_demand_mode=true, promove um lote de entradas
 * `waiting` para `admitted` respeitando o limite configurado em
 * `events.virtual_queue_admission_batch_size` (default técnico, não
 * validado com o usuário) e expira admissões antigas sem hold concluído
 * (VirtualQueueService::ADMISSION_WINDOW_MINUTES) para liberar o slot.
 */
class AdmitVirtualQueueEntriesCommand extends Command
{
    protected $signature = 'storefront:admit-virtual-queue-entries';

    protected $description = 'Promove entradas aguardando na fila virtual para admitido, respeitando o limite de admissões simultâneas por evento.';

    public function handle(): int
    {
        $admittedTotal = 0;
        $expiredTotal = 0;

        Event::query()
            ->where('high_demand_mode', true)
            ->whereNull('deleted_at')
            ->chunkById(50, function ($events) use (&$admittedTotal, &$expiredTotal) {
                foreach ($events as $event) {
                    [$admitted, $expired] = $this->processEvent($event);
                    $admittedTotal += $admitted;
                    $expiredTotal += $expired;
                }
            });

        $this->info("Entradas admitidas: {$admittedTotal}. Admissões expiradas: {$expiredTotal}.");

        return self::SUCCESS;
    }

    private function processEvent(Event $event): array
    {
        return DB::transaction(function () use ($event) {
            $expired = VirtualQueueEntry::query()
                ->where('tenant_id', $event->tenant_id)
                ->where('event_id', $event->id)
                ->where('status', VirtualQueueEntry::STATUS_ADMITTED)
                ->where('admitted_at', '<=', now()->subMinutes(VirtualQueueService::ADMISSION_WINDOW_MINUTES))
                ->update(['status' => VirtualQueueEntry::STATUS_EXPIRED]);

            $activeAdmitted = VirtualQueueEntry::query()
                ->where('tenant_id', $event->tenant_id)
                ->where('event_id', $event->id)
                ->where('status', VirtualQueueEntry::STATUS_ADMITTED)
                ->count();

            $batchSize = max(0, (int) $event->virtual_queue_admission_batch_size);
            $slots = max(0, $batchSize - $activeAdmitted);

            if ($slots === 0) {
                return [0, $expired];
            }

            $entryIds = VirtualQueueEntry::query()
                ->where('tenant_id', $event->tenant_id)
                ->where('event_id', $event->id)
                ->where('status', VirtualQueueEntry::STATUS_WAITING)
                ->orderBy('position')
                ->limit($slots)
                ->lockForUpdate()
                ->pluck('id');

            if ($entryIds->isEmpty()) {
                return [0, $expired];
            }

            VirtualQueueEntry::query()
                ->whereIn('id', $entryIds)
                ->update([
                    'status' => VirtualQueueEntry::STATUS_ADMITTED,
                    'admitted_at' => now(),
                ]);

            return [$entryIds->count(), $expired];
        });
    }
}
