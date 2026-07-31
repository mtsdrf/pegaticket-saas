<?php

namespace App\Services\Auth;

use App\DTOs\Auth\CreateSelfSignupDTO;
use App\DTOs\Tenant\CreateTenantDTO;
use App\Events\User\UserCreated;
use App\Models\Group\Group;
use App\Models\Legal\LegalAcceptance;
use App\Repositories\Contracts\LegalDocumentRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Tenant\TenantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SelfSignupService
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private TenantService $tenantService,
        private AuthService $authService,
        private LegalDocumentRepositoryInterface $legalDocumentRepository
    ) {
    }

    public function register(CreateSelfSignupDTO $dto, string $ip, ?string $userAgent): array
    {
        return DB::transaction(function () use ($dto, $ip, $userAgent) {
            $user = $this->userRepository->createUser([
                'name' => $dto->ownerName,
                'email' => $dto->ownerEmail,
                'password' => Hash::make($dto->password),
                'is_active' => true,
            ]);

            $clientsGroupUuid = Group::query()
                ->where('slug', 'clients')
                ->whereNull('deleted_at')
                ->value('uuid');

            if ($clientsGroupUuid) {
                $user = $this->userRepository->syncGroups($user, [$clientsGroupUuid]);
            }

            event(new UserCreated(
                userUuid: $user->uuid,
                actorId: $user->id
            ));

            $this->recordAcceptanceIfPublished($user->id, 'terms', $dto->termsAccepted, $ip);
            $this->recordAcceptanceIfPublished($user->id, 'privacy', $dto->privacyAccepted, $ip);

            $tenant = $this->tenantService->create(
                CreateTenantDTO::fromArray([
                    'name' => $dto->tenantName,
                    'slug' => $dto->tenantSlug,
                    'plan_uuid' => $dto->planUuid,
                    'is_active' => true,
                ]),
                ownerUserId: $user->id,
                actorId: $user->id
            );

            return $this->authService->createSession(
                $user,
                $ip,
                $userAgent,
                $tenant->id,
                $tenant->uuid
            );
        });
    }

    private function recordAcceptanceIfPublished(int $userId, string $type, bool $accepted, string $ip): void
    {
        if (! $accepted) {
            return;
        }

        $document = $this->legalDocumentRepository->findActiveByType($type);
        if (! $document) {
            return;
        }

        LegalAcceptance::create([
            'user_id' => $userId,
            'legal_document_id' => $document->id,
            'accepted_at' => now(),
            'ip' => $ip,
        ]);
    }
}
