<?php

namespace App\Console\Commands;

use App\Mail\ScheduledReportSummaryMail;
use App\Models\Report\ScheduledReportSubscription;
use App\Services\Communication\CommunicationDispatcherService;
use App\Services\Logging\ApplicationLogger;
use App\Services\Report\ReportService;
use Illuminate\Console\Command;

/**
 * Relatórios agendados básicos (roadmap A2) — roda diária, decide por
 * assinatura se está em dia com a frequência configurada
 * (ScheduledReportSubscription::isDue, ver model) e, se sim, envia o
 * resumo dos KPIs do Home (ReportService::indicators()) via
 * CommunicationDispatcherService (nunca Mail::send() direto). Mesmo
 * espírito de SendRecompraNudgeMailsCommand: marca a data de envio pra não
 * duplicar.
 */
class SendScheduledReportSummariesCommand extends Command
{
    protected $signature = 'reports:send-scheduled-summaries';

    protected $description = 'Envia o resumo agendado (diário/semanal) dos KPIs do Home para as assinaturas em dia.';

    public function handle(ReportService $reportService, CommunicationDispatcherService $communicationDispatcher): int
    {
        $subscriptions = ScheduledReportSubscription::query()
            ->whereNull('deleted_at')
            ->with('tenant')
            ->get()
            ->filter(fn (ScheduledReportSubscription $subscription) => $subscription->isDue);

        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($subscriptions as $subscription) {
            if (! $subscription->tenant) {
                $skipped++;

                continue;
            }

            try {
                $indicators = $reportService->indicators($subscription->tenant_id);
                $frequencyLabel = $subscription->frequency === ScheduledReportSubscription::FREQUENCY_WEEKLY ? 'semanal' : 'diário';
                $dashboardUrl = rtrim((string) config('app.frontend_url'), '/').'/dashboard';

                $communicationDispatcher->send(
                    'scheduled_report_summary',
                    new ScheduledReportSummaryMail(
                        tenant: $subscription->tenant,
                        frequencyLabel: $frequencyLabel,
                        indicators: $indicators,
                        dashboardUrl: $dashboardUrl,
                    ),
                    $subscription->recipient_email,
                    $subscription->tenant_id
                );

                $subscription->forceFill(['last_sent_at' => now()])->save();
                $sent++;
            } catch (\Throwable $e) {
                $failed++;

                ApplicationLogger::error('Falha ao enviar resumo de relatório agendado', [
                    'scheduled_report_subscription_uuid' => $subscription->uuid,
                    'exception_class' => get_class($e),
                    'exception_message' => $e->getMessage(),
                ]);
            }
        }

        $this->info('Assinaturas em dia: '.$subscriptions->count().'.');
        $this->info("Resumos enviados: {$sent}.");
        $this->info("Ignorados (sem tenant): {$skipped}.");
        $this->info("Falhas: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
