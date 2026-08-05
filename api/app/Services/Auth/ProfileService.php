<?php

namespace App\Services\Auth;

use App\DTOs\Auth\ConfirmEmailChangeDTO;
use App\DTOs\Auth\RequestEmailChangeDTO;
use App\DTOs\Auth\UpdatePasswordDTO;
use App\DTOs\Auth\UpdateProfileDTO;
use App\Events\User\UserEmailChanged;
use App\Events\User\UserPasswordChanged;
use App\Events\User\UserProfileUpdated;
use App\Exceptions\InvalidEmailConfirmationTokenException;
use App\Mail\UserEmailConfirmationMail;
use App\Models\User\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Communication\CommunicationDispatcherService;
use App\Services\Media\MediaStorageService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Auto-serviço "Meus dados": o usuário STAFF autenticado edita a SI MESMO
 * (nome/foto/senha/e-mail), sem depender de Functionality/perm nenhuma —
 * mesmo espírito de AuthAccessController::show. Não confundir com o CRUD
 * admin de outros usuários (UserService/UserController, gated por
 * perm:users,*).
 */
class ProfileService
{
    private const EMAIL_TOKEN_EXPIRES_IN_HOURS = 24;

    public function __construct(
        private UserRepositoryInterface $repository,
        private MediaStorageService $mediaStorage,
        private CommunicationDispatcherService $communicationDispatcher
    ) {}

    public function update(User $user, UpdateProfileDTO $dto): User
    {
        $newMedia = null;
        $oldPath = $user->avatar_path;

        if ($dto->avatar) {
            $newMedia = $this->mediaStorage->storePublicImage($dto->avatar, $user->uuid, 'avatar');
        }

        try {
            return DB::transaction(function () use ($user, $dto, $newMedia, $oldPath) {
                $original = $user->getOriginal();

                $data = array_filter([
                    'name' => $dto->name,
                    'phone' => $dto->phone,
                ], fn ($v) => ! is_null($v));

                if ($newMedia) {
                    $data['avatar_path'] = $newMedia['path'];
                    $data['avatar_data'] = null;
                    $data['avatar_mime'] = $newMedia['mime'];
                    $data['avatar_updated_at'] = $newMedia['updated_at'];
                }

                if (! empty($data)) {
                    $user = $this->repository->updateUser($user, $data);
                }

                if ($newMedia && $oldPath && $oldPath !== $newMedia['path']) {
                    DB::afterCommit(fn () => $this->mediaStorage->deletePublicMedia($oldPath, 'avatar'));
                }

                $changes = array_diff_assoc($user->getAttributes(), $original);

                if (! empty($changes)) {
                    event(new UserProfileUpdated(
                        userUuid: $user->uuid,
                        actorId: Auth::id(),
                        changes: array_keys($changes)
                    ));
                }

                return $user;
            });
        } catch (\Throwable $e) {
            if ($newMedia) {
                $this->mediaStorage->deletePublicMedia($newMedia['path'], 'avatar');
            }

            throw $e;
        }
    }

    public function changePassword(User $user, UpdatePasswordDTO $dto): void
    {
        DB::transaction(function () use ($user, $dto) {
            $this->repository->updateUser($user, [
                'password' => Hash::make($dto->newPassword),
            ]);

            event(new UserPasswordChanged(
                userUuid: $user->uuid,
                actorId: Auth::id()
            ));
        });
    }

    public function requestEmailChange(User $user, RequestEmailChangeDTO $dto): void
    {
        DB::transaction(function () use ($user, $dto) {
            $plainToken = Str::random(64);

            $this->repository->updateUser($user, [
                'pending_email' => $dto->newEmail,
                'pending_email_token_hash' => hash('sha512', $plainToken),
                'pending_email_expires_at' => now()->addHours(self::EMAIL_TOKEN_EXPIRES_IN_HOURS),
            ]);

            $confirmUrl = rtrim((string) config('app.frontend_url'), '/').'/confirmar-email/'.$plainToken;

            $this->communicationDispatcher->send(
                'email_confirmation',
                new UserEmailConfirmationMail($user, $dto->newEmail, $confirmUrl),
                $dto->newEmail
            );
        });
    }

    public function confirmEmailChange(ConfirmEmailChangeDTO $dto): void
    {
        DB::transaction(function () use ($dto) {
            $user = User::where('pending_email_token_hash', hash('sha512', $dto->token))
                ->whereNull('deleted_at')
                ->first();

            if (! $user || ! $user->pending_email_expires_at || $user->pending_email_expires_at->isPast()) {
                throw new InvalidEmailConfirmationTokenException(
                    __('messages.profile.invalid_or_expired_token')
                );
            }

            $oldEmail = $user->email;
            $newEmail = $user->pending_email;

            $this->repository->updateUser($user, [
                'email' => $newEmail,
                'pending_email' => null,
                'pending_email_token_hash' => null,
                'pending_email_expires_at' => null,
            ]);

            event(new UserEmailChanged(
                userUuid: $user->uuid,
                actorId: $user->id,
                oldEmail: $oldEmail,
                newEmail: $newEmail
            ));
        });
    }
}
