<?php

namespace App\Mail;

use App\Models\Tenant\TenantUserInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TenantUserInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TenantUserInvite $invite,
        public string $inviteUrl
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject(__('messages.tenant_user_invite.mail_subject', ['tenant' => $this->invite->tenant->name]))
            ->view('emails.tenant-user-invite')
            ->with([
                'invite' => $this->invite,
                'inviteUrl' => $this->inviteUrl,
            ]);
    }
}
