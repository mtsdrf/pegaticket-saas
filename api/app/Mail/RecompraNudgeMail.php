<?php

namespace App\Mail;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Tenant\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Automação de recompra (Fase 6, fatia final) — "sentimos sua falta" para
 * compradores que já compraram desta loja mas não compram há mais de N
 * dias (ver SendRecompraNudgeMailsCommand). Reaproveita a mesma
 * infraestrutura de Mail já usada por TicketDeliveryMail, mailable/view
 * próprios porque o conteúdo é bem diferente (convite, não comprovante).
 */
class RecompraNudgeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public FinalCustomer $finalCustomer,
        public string $storefrontUrl,
    ) {}

    public function build(): self
    {
        return $this
            ->subject(__('messages.customers.recompra_mail_subject', ['tenant' => $this->tenant->name]))
            ->view('emails.recompra-nudge')
            ->with([
                'tenant' => $this->tenant,
                'finalCustomer' => $this->finalCustomer,
                'storefrontUrl' => $this->storefrontUrl,
            ]);
    }
}
