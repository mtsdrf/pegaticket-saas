<?php

namespace App\Console\Commands;

use App\Services\TicketTypeWaitlist\TicketTypeWaitlistService;
use Illuminate\Console\Command;

/**
 * Varredura periódica (mesmo padrão de SendRecompraNudgeMailsCommand) —
 * disponibilidade de TicketType é calculada dinamicamente, não é uma
 * coluna que muda num único ponto do código, então não dá pra "escutar"
 * um evento de estoque voltando; comparamos disponibilidade atual contra
 * os inscritos ainda não notificados a cada execução.
 */
class NotifyTicketTypeWaitlistCommand extends Command
{
    protected $signature = 'ticket-types:notify-waitlist';

    protected $description = 'Notifica por e-mail os inscritos na lista de espera de tipos de ingresso que voltaram a ter disponibilidade.';

    public function handle(TicketTypeWaitlistService $service): int
    {
        $result = $service->notifyAvailableTicketTypes();

        $this->info("Notificações enviadas: {$result['sent']}.");
        $this->info("Ignorados: {$result['skipped']}.");
        $this->info("Falhas: {$result['failed']}.");

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
