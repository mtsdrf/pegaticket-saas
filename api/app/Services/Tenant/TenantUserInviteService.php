<?php

namespace App\Services\Tenant;

use App\DTOs\Tenant\CreateTenantUserInviteDTO;
use App\Events\Tenant\TenantUserInvited;
use App\Exceptions\DuplicateInviteException;
use App\Exceptions\EmailAlreadyRegisteredException;
use App\Mail\TenantUserInviteMail;
use App\Models\Tenant\TenantRole;
use App\Models\Tenant\TenantUserInvite;
use App\Models\User\User;
use App\Repositories\Contracts\TenantUserInviteRepositoryInterface;
use App\Services\Communication\CommunicationDispatcherService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantUserInviteService
{
    private const EXPIRES_IN_DAYS = 7;

    public function __construct(
        private TenantUserInviteRepositoryInterface $repository,
        private CommunicationDispatcherService $communicationDispatcher
    ) {}

    public function create(CreateTenantUserInviteDTO $dto, int $tenantId): TenantUserInvite
    {
        return DB::transaction(function () use ($dto, $tenantId) {
            $role = TenantRole::where('uuid', $dto->roleUuid)
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->firstOrFail();

            if (User::where('email', $dto->email)->whereNull('deleted_at')->exists()) {
                throw new EmailAlreadyRegisteredException(
                    __('messages.tenant_user_invite.email_already_registered')
                );
            }

            if ($this->repository->findPendingByTenantAndEmail($tenantId, $dto->email)) {
                throw new DuplicateInviteException(
                    __('messages.tenant_user_invite.pending_invite_exists')
                );
            }

            $plainToken = Str::random(64);

            $invite = $this->repository->create([
                'tenant_id' => $tenantId,
                'tenant_role_id' => $role->id,
                'name' => $dto->name,
                'email' => $dto->email,
                'token_hash' => hash('sha512', $plainToken),
                'expires_at' => now()->addDays(self::EXPIRES_IN_DAYS),
            ]);

            $inviteUrl = rtrim((string) config('app.frontend_url'), '/').'/convite/'.$plainToken;

            $this->communicationDispatcher->send(
                'tenant_user_invite',
                new TenantUserInviteMail($invite, $inviteUrl),
                $invite->email,
                $tenantId
            );

            event(new TenantUserInvited(
                tenantUserInviteUuid: $invite->uuid,
                actorId: Auth::id()
            ));

            return $invite;
        });
    }
}
