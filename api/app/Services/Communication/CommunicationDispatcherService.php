<?php

namespace App\Services\Communication;

use App\Repositories\Contracts\CommunicationLogRepositoryInterface;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * Ponto central de envio de e-mail transacional (roadmap "hub de
 * comunicação"). Substitui `Mail::to()->send()` direto nos Services/
 * Commands, adicionando tracking em `communication_logs` — não muda
 * comportamento de propagação de erro: se o envio falhar, a exceção
 * original é relançada (o caller decide o que fazer, ex. rollback de
 * transação em andamento), só com o log de falha gravado antes.
 */
class CommunicationDispatcherService
{
    public function __construct(
        private CommunicationLogRepositoryInterface $repository
    ) {}

    public function send(string $type, Mailable $mailable, string $recipientEmail, ?int $tenantId = null): void
    {
        try {
            Mail::to($recipientEmail)->send($mailable);

            $this->repository->create([
                'tenant_id' => $tenantId,
                'type' => $type,
                'recipient_email' => $recipientEmail,
                'status' => 'sent',
                'sent_at' => now(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->repository->create([
                'tenant_id' => $tenantId,
                'type' => $type,
                'recipient_email' => $recipientEmail,
                'status' => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 500),
                'created_at' => now(),
            ]);

            throw $e;
        }
    }
}
