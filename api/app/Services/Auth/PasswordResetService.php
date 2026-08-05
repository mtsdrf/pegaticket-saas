<?php

namespace App\Services\Auth;

use App\DTOs\Auth\ForgotPasswordDTO;
use App\DTOs\Auth\ResetPasswordDTO;
use App\Events\User\UserPasswordChanged;
use App\Exceptions\InvalidPasswordResetTokenException;
use App\Mail\PasswordResetMail;
use App\Models\User\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Communication\CommunicationDispatcherService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * "Esqueci a senha" — fluxo público (usuário deslogado), espelha o padrão
 * de troca de e-mail em ProfileService::requestEmailChange/confirmEmailChange,
 * mas com validade mais curta (1h, contra 24h) por ser mais sensível.
 */
class PasswordResetService
{
    private const RESET_TOKEN_EXPIRES_IN_HOURS = 1;

    public function __construct(
        private UserRepositoryInterface $repository,
        private CommunicationDispatcherService $communicationDispatcher
    ) {}

    /**
     * Nunca revela se o e-mail existe ou não (user enumeration) — sempre
     * retorna normalmente, com ou sem envio de e-mail.
     */
    public function requestReset(ForgotPasswordDTO $dto): void
    {
        $user = User::whereRaw('LOWER(email) = ?', [Str::lower($dto->email)])
            ->whereNull('deleted_at')
            ->first();

        if (! $user) {
            return;
        }

        DB::transaction(function () use ($user) {
            $plainToken = Str::random(64);

            $this->repository->updateUser($user, [
                'password_reset_token_hash' => hash('sha512', $plainToken),
                'password_reset_expires_at' => now()->addHours(self::RESET_TOKEN_EXPIRES_IN_HOURS),
            ]);

            $resetUrl = rtrim((string) config('app.frontend_url'), '/').'/redefinir-senha/'.$plainToken;

            $this->communicationDispatcher->send(
                'password_reset',
                new PasswordResetMail($user, $resetUrl),
                $user->email
            );
        });
    }

    public function resetPassword(ResetPasswordDTO $dto): void
    {
        DB::transaction(function () use ($dto) {
            $user = User::where('password_reset_token_hash', hash('sha512', $dto->token))
                ->whereNull('deleted_at')
                ->first();

            if (! $user || ! $user->password_reset_expires_at || $user->password_reset_expires_at->isPast()) {
                throw new InvalidPasswordResetTokenException(
                    __('messages.auth.invalid_or_expired_reset_token')
                );
            }

            $this->repository->updateUser($user, [
                'password' => Hash::make($dto->newPassword),
                'password_reset_token_hash' => null,
                'password_reset_expires_at' => null,
            ]);

            event(new UserPasswordChanged(
                userUuid: $user->uuid,
                actorId: $user->id
            ));
        });
    }
}
