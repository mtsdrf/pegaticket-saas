<?php

namespace App\Mail;

use App\Models\Tenant\TenantUserInvite;
use App\Services\Communication\EmailTemplateResolverService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TenantUserInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TenantUserInvite $invite,
        public string $inviteUrl
    ) {}

    public function build(): self
    {
        $placeholders = [
            'nome' => $this->invite->name,
            'empresa' => $this->invite->tenant->name,
            'link' => $this->inviteUrl,
        ];

        $resolver = app(EmailTemplateResolverService::class);
        $defaultSubject = __('messages.tenant_user_invite.mail_subject', ['tenant' => $this->invite->tenant->name]);
        $subject = $resolver->resolveSubject('tenant_user_invite', $this->invite->tenant_id, $defaultSubject, $placeholders);
        $bodyHtml = $resolver->resolveBodyHtml('tenant_user_invite', $this->invite->tenant_id, $placeholders);

        $mail = $this->subject($subject);

        return $bodyHtml !== null
            ? $mail->html($bodyHtml)
            : $mail->view('emails.tenant-user-invite')->with([
                'invite' => $this->invite,
                'inviteUrl' => $this->inviteUrl,
            ]);
    }
}
