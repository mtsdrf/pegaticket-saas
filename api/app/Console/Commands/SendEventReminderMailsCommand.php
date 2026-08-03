<?php

namespace App\Console\Commands;

use App\Models\Sale\Sale;
use App\Models\Ticket\Ticket;
use App\Services\Logging\ApplicationLogger;
use App\Services\Ticket\TicketDeliveryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Comunicação transacional mínima (roadmap Fase 1) — lembrete de evento por
 * e-mail para vendas pagas cujo evento/sessão começa dentro da janela
 * configurada, ainda não lembradas (`sales.reminder_sent_at`). Reaproveita
 * o mesmo TicketDeliveryMail já usado na confirmação de compra (modo
 * `reminder`), sem duplicar o template de e-mail.
 */
class SendEventReminderMailsCommand extends Command
{
    protected $signature = 'sales:send-event-reminders {--hours-ahead=24}';

    protected $description = 'Envia e-mail de lembrete de evento para vendas pagas com evento começando dentro da janela configurada.';

    public function __construct(
        private TicketDeliveryService $ticketDeliveryService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $hoursAhead = max(1, (int) $this->option('hours-ahead'));
        $windowStart = now();
        $windowEnd = now()->addHours($hoursAhead);

        $saleIds = $this->resolveSaleIdsWithEventStartingWithin($windowStart, $windowEnd);

        $sent = 0;
        $failed = 0;

        foreach (Sale::whereIn('id', $saleIds)->cursor() as $sale) {
            try {
                $tickets = Ticket::whereHas('saleItem', fn ($q) => $q->where('sale_id', $sale->id))
                    ->whereNotIn('status', ['cancelado', 'estornado'])
                    ->get();

                if ($tickets->isEmpty()) {
                    continue;
                }

                $this->ticketDeliveryService->sendForSale($sale, $tickets, 'reminder');

                $sale->forceFill(['reminder_sent_at' => now()])->save();
                $sent++;
            } catch (\Throwable $e) {
                $failed++;

                ApplicationLogger::error('Falha ao enviar lembrete de evento', [
                    'sale_uuid' => $sale->uuid,
                    'exception_class' => get_class($e),
                    'exception_message' => $e->getMessage(),
                ]);
            }
        }

        $this->info('Vendas elegíveis: '.count($saleIds).'.');
        $this->info("Lembretes enviados: {$sent}.");
        $this->info("Falhas: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Vendas pagas, não canceladas, ainda não lembradas, com pelo menos um
     * item cujo evento (via sessão, quando houver, senão o próprio evento)
     * começa dentro da janela.
     *
     * @return array<int, int>
     */
    private function resolveSaleIdsWithEventStartingWithin(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return DB::table('sales')
            ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
            ->join('ticket_types', 'ticket_types.id', '=', 'sale_items.ticket_type_id')
            ->join('events', 'events.id', '=', 'ticket_types.event_id')
            ->leftJoin('event_sessions', 'event_sessions.id', '=', 'ticket_types.event_session_id')
            ->where('sales.is_paid', true)
            ->whereNull('sales.cancelled_at')
            ->whereNull('sales.reminder_sent_at')
            ->whereNull('sale_items.deleted_at')
            ->whereNull('sales.deleted_at')
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('event_sessions.starts_at', [$start, $end])
                    ->orWhere(function ($q) use ($start, $end) {
                        $q->whereNull('event_sessions.id')
                            ->whereBetween('events.starts_at', [$start, $end]);
                    });
            })
            ->distinct()
            ->pluck('sales.id')
            ->all();
    }
}
