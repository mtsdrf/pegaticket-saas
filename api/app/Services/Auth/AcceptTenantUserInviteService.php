<?php

namespace App\Services\Auth;

use App\DTOs\Tenant\AcceptTenantUserInviteDTO;
use App\DTOs\Tenant\CreateTenantUserDTO;
use App\Events\Tenant\TenantUserInviteAccepted;
use App\Events\User\UserCreated;
use App\Exceptions\EmailAlreadyRegisteredException;
use App\Exceptions\InvalidInviteTokenException;
use App\Repositories\Contracts\TenantUserInviteRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Tenant\TenantUserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AcceptTenantUserInviteService
{
    public function __construct(
        private TenantUserInviteRepositoryInterface $inviteRepository,
        private UserRepositoryInterface $userRepository,
        private TenantUserService $tenantUserService,
        private AuthService $authService
    ) {
    }

    public function accept(AcceptTenantUserInviteDTO $dto, string $ip, ?string $userAgent): array
    {
        return DB::transaction(function () use ($dto, $ip, $userAgent) {
            $invite = $this->inviteRepository->findByTokenHash(hash('sha512', $dto->token));

            if (!$invite) {
                throw new InvalidInviteTokenException(__('messages.tenant_user_invite.invalid_token'));
            }

            if ($invite->isAccepted()) {
                throw new InvalidInviteTokenException(__('messages.tenant_user_invite.already_accepted'));
            }

            if ($invite->isExpired()) {
                throw new InvalidInviteTokenException(__('messages.tenant_user_invite.expired'));
            }

            if ($this->userRepository->findByEmail($invite->email)) {
                throw new EmailAlreadyRegisteredException(
                    __('messages.tenant_user_invite.email_already_registered')
                );
            }

            $user = $this->userRepository->createUser([
                'name' => $invite->name,
                'email' => $invite->email,
                'password' => Hash::make($dto->password),
                'is_active' => true,
            ]);

            event(new UserCreated(
                userUuid: $user->uuid,
                actorId: $user->id
            ));

            // Fora do middleware `tenant` (rota pública), os helpers
            // tenant_id()/tenant_uuid() não existem ainda no container —
            // replica manualmente o binding que ResolveTenant faria, só
            // para a chamada interna a TenantUserService::create.
            app()->instance('tenant_id', $invite->tenant_id);
            app()->instance('tenant_uuid', $invite->tenant->uuid);

            $tenantUser = $this->tenantUserService->create(
                CreateTenantUserDTO::fromArray([
                    'user_uuid' => $user->uuid,
                    'tenant_uuid' => $invite->tenant->uuid,
                    'role_uuid' => $invite->role->uuid,
                    'is_active' => true,
                ]),
                actorId: $user->id
            );

            $this->inviteRepository->update($invite, ['accepted_at' => now()]);

            event(new TenantUserInviteAccepted(
                tenantUserInviteUuid: $invite->uuid,
                userUuid: $user->uuid,
                actorId: $user->id
            ));

            return $this->authService->createSession(
                $user,
                $ip,
                $userAgent,
                $tenantUser->tenant_id,
                $invite->tenant->uuid
            );
        });
    }
}
