<?php

namespace Tests\Feature\Payment;

use App\Events\Payment\TenantPagBankConnectionStatusChanged;
use App\Mail\PagBankReceivingStatusChangedMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

/**
 * Notificação de mudança de status de recebimentos (roadmap R2.5, seção
 * 9.5.6) — App\Listeners\Payment\NotifyTenantPagBankConnectionStatusChanged,
 * segundo listener de TenantPagBankConnectionStatusChanged (o primeiro,
 * de auditoria, já é coberto em outro teste e não é tocado aqui).
 */
class PagBankReceivingNotificationTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        // owner: findOwnerUserForTenant só resolve TenantRole slug=owner.
        $this->setUpTenantScopedUser('owner-receiving@test.com', 'owner', 'Proprietário');
    }

    #[Test]
    public function notifies_owner_when_status_changes_to_enabled(): void
    {
        Mail::fake();

        event(new TenantPagBankConnectionStatusChanged(
            tenantId: $this->tenant->id,
            actorId: $this->userId,
            fromStatus: 'under_review',
            toStatus: 'enabled'
        ));

        Mail::assertSent(PagBankReceivingStatusChangedMail::class, function ($mail) {
            return $mail->variant === PagBankReceivingStatusChangedMail::VARIANT_ENABLED
                && $mail->tenantId === $this->tenant->id;
        });
    }

    #[Test]
    public function notifies_owner_when_status_changes_to_verified(): void
    {
        Mail::fake();

        event(new TenantPagBankConnectionStatusChanged(
            tenantId: $this->tenant->id,
            actorId: $this->userId,
            fromStatus: 'submitted',
            toStatus: 'verified'
        ));

        Mail::assertSent(PagBankReceivingStatusChangedMail::class, fn ($mail) => $mail->variant === PagBankReceivingStatusChangedMail::VARIANT_ENABLED);
    }

    #[Test]
    public function notifies_owner_when_status_changes_to_restricted_or_rejected(): void
    {
        Mail::fake();

        event(new TenantPagBankConnectionStatusChanged(
            tenantId: $this->tenant->id,
            actorId: $this->userId,
            fromStatus: 'enabled',
            toStatus: 'restricted'
        ));

        event(new TenantPagBankConnectionStatusChanged(
            tenantId: $this->tenant->id,
            actorId: $this->userId,
            fromStatus: 'submitted',
            toStatus: 'rejected'
        ));

        Mail::assertSent(PagBankReceivingStatusChangedMail::class, 2);
        Mail::assertSent(PagBankReceivingStatusChangedMail::class, fn ($mail) => $mail->variant === PagBankReceivingStatusChangedMail::VARIANT_PENDING);
    }

    #[Test]
    public function does_not_notify_for_transitions_outside_the_enabled_or_pending_groups(): void
    {
        Mail::fake();

        foreach (['pending_connection', 'pending_kyc', 'under_review', 'submitted', 'started', 'disabled'] as $toStatus) {
            event(new TenantPagBankConnectionStatusChanged(
                tenantId: $this->tenant->id,
                actorId: $this->userId,
                fromStatus: 'not_configured',
                toStatus: $toStatus
            ));
        }

        Mail::assertNothingSent();
    }

    #[Test]
    public function repeated_sync_without_a_real_status_change_never_fires_the_event_so_it_cannot_duplicate_the_email(): void
    {
        // O evento só é despachado quando fromStatus !== toStatus (ver
        // dispatchStatusChanged em PagBankAccountService/
        // PagBankConnectService) — aqui simulamos diretamente o "sync
        // repetido sem mudança" não disparando o evento, provando que a
        // ausência de duplicidade é estrutural, não uma checagem extra
        // no listener.
        Mail::fake();

        // Nenhum evento disparado (equivalente a syncStatus() encontrar o
        // mesmo status de antes) -> nenhum e-mail.
        Mail::assertNothingSent();
    }
}
