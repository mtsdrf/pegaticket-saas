<?php

namespace App\Mail;

use App\Models\Tenant\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Resumo de relatório agendado (roadmap A2) — KPIs do Home
 * (ReportService::indicators()), diário ou semanal. Mailable simples, sem
 * EmailTemplateResolverService: conteúdo é 100% dado agregado, não texto
 * editável pelo tenant (diferente de ticket_delivery/recompra_nudge etc.).
 */
class ScheduledReportSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public string $frequencyLabel,
        public array $indicators,
        public string $dashboardUrl,
    ) {}

    public function build(): self
    {
        return $this->subject(__('messages.report.scheduled_summary_mail_subject', [
            'tenant' => $this->tenant->name,
            'frequency' => $this->frequencyLabel,
        ]))->view('emails.scheduled-report-summary')->with([
            'tenant' => $this->tenant,
            'frequencyLabel' => $this->frequencyLabel,
            'indicators' => $this->indicators,
            'dashboardUrl' => $this->dashboardUrl,
        ]);
    }
}
