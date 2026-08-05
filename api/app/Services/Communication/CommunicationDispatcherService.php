<?php

namespace App\Services\Communication;

use App\Repositories\Contracts\CommunicationLogRepositoryInterface;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * Ponto central de envio de e-mail transacional (roadmap "hub de
 * comunicação"). Substitui `Mail::to()->send()` direto nos Services/
 * Commands, adicionando tracking em `communication_logs` — não muda
 * comportamento de propagação de erro: se o envio falhar (padrão e,
 * quando configurado, fallback), a exceção original é relançada (o
 * caller decide o que fazer, ex. rollback de transação em andamento), só
 * com o log de falha gravado antes.
 *
 * Fallback: se `mail.fallback_mailer` (env MAIL_MAILER_FALLBACK) estiver
 * preenchido, uma falha no mailer padrão dispara UMA tentativa pelo
 * mailer de fallback antes de desistir. Sem fallback configurado, o
 * comportamento é idêntico ao anterior a esta feature (falha direto no
 * mailer padrão, sem tentar nada a mais) — por isso o caminho principal
 * continua chamando `Mail::to()` puro (mailer padrão implícito), não
 * `Mail::mailer(...)`.
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

            $this->logSent($type, $recipientEmail, $tenantId, (string) config('mail.default'));

            return;
        } catch (\Throwable $primaryError) {
            $fallbackMailer = trim((string) config('mail.fallback_mailer', ''));

            if ($fallbackMailer === '') {
                $this->logFailed($type, $recipientEmail, $tenantId, $primaryError, null);

                throw $primaryError;
            }

            try {
                Mail::mailer($fallbackMailer)->to($recipientEmail)->send($mailable);

                $this->logSent($type, $recipientEmail, $tenantId, $fallbackMailer);
            } catch (\Throwable $fallbackError) {
                $this->logFailed($type, $recipientEmail, $tenantId, $fallbackError, $fallbackMailer);

                throw $fallbackError;
            }
        }
    }

    private function logSent(string $type, string $recipientEmail, ?int $tenantId, string $mailerUsed): void
    {
        $this->repository->create([
            'tenant_id' => $tenantId,
            'type' => $type,
            'recipient_email' => $recipientEmail,
            'status' => 'sent',
            'mailer_used' => $mailerUsed,
            'sent_at' => now(),
            'created_at' => now(),
        ]);
    }

    private function logFailed(string $type, string $recipientEmail, ?int $tenantId, \Throwable $e, ?string $mailerUsed): void
    {
        $this->repository->create([
            'tenant_id' => $tenantId,
            'type' => $type,
            'recipient_email' => $recipientEmail,
            'status' => 'failed',
            'mailer_used' => $mailerUsed,
            'error_message' => mb_substr($e->getMessage(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
