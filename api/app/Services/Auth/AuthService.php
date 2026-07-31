<?php

namespace App\Services\Auth;

use App\Events\Auth\LoginSucceeded;
use App\Events\Auth\LoginFailed;
use App\Events\Auth\TokenRefreshed;
use App\Events\Auth\LogoutSucceeded;
use App\Events\Auth\LogoutFailed;
use App\Models\Auth\LoginLockout;
use App\Models\Auth\RefreshToken;
use App\Models\Auth\TokenBlacklist;
use App\Models\User\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthService
{
    public function __construct(private JWTService $jwtService)
    {
    }

    public function createSession(
        User $user,
        string $ip,
        ?string $userAgent,
        ?int $tenantId = null,
        ?string $tenantUuid = null
    ): array
    {
        $accessToken = $this->jwtService->issueAccessToken($user, $tenantId, $tenantUuid);

        $refreshPlain = (string) Str::uuid();
        $refreshHash = hash('sha512', $refreshPlain);

        $refresh = RefreshToken::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'token_hash' => $refreshHash,
            'expires_at' => now()->addDays(30),
            'user_agent_hash' => $userAgent ? hash('sha512', $userAgent) : null,
            'ip_hash' => $ip ? hash('sha512', $ip) : null,
        ]);

        return [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'refresh_token' => $refreshPlain,
            'refresh_token_uuid' => $refresh->uuid,
            'tenant_uuid' => $tenantUuid,
        ];
    }

    /**
     * Máximo de tentativas de credencial erradas antes de bloquear.
     */
    private const MAX_LOGIN_ATTEMPTS = 5;

    /**
     * Duração do bloqueio, em minutos, ao atingir o limite de tentativas.
     */
    private const LOCKOUT_MINUTES = 15;

    public function login(string $email, string $password, string $ip, ?string $userAgent): array
    {
        // Bloqueio de força bruta (roadmap 1A). Mensagem/erro genéricos —
        // nunca revela se o e-mail existe nem a contagem de tentativas;
        // só sinaliza "tente mais tarde" quando de fato bloqueado.
        if ($this->isLockedOut($email)) {
            event(new LoginFailed(email: $email, reason: 'account_locked'));
            throw new \RuntimeException(__('messages.auth.account_locked'));
        }

        $user = User::where('email', $email)
            ->whereNull('deleted_at')
            ->first();

        if (!$user || !$user->is_active || !Hash::check($password, $user->password)) {
            $this->registerFailedAttempt($email);
            event(new LoginFailed(email: $email, reason: 'invalid_credentials'));
            throw new \RuntimeException(__('messages.auth.invalid_credentials'));
        }

        // Login bem-sucedido zera qualquer contador/bloqueio pendente.
        $this->clearLockout($email);

        $session = $this->createSession($user, $ip, $userAgent);

        event(new LoginSucceeded(userUuid: $user->uuid, actorId: $user->id));

        return $session;
    }

    private function isLockedOut(string $email): bool
    {
        $lockout = LoginLockout::where('email', $email)->first();

        return $lockout
            && $lockout->locked_until !== null
            && $lockout->locked_until->isFuture();
    }

    private function registerFailedAttempt(string $email): void
    {
        $lockout = LoginLockout::firstOrNew(['email' => $email]);

        // Se um bloqueio anterior já expirou, a nova falha reinicia a
        // contagem em vez de continuar do valor antigo.
        if ($lockout->locked_until !== null && $lockout->locked_until->isPast()) {
            $lockout->failed_attempts = 0;
            $lockout->locked_until = null;
        }

        $lockout->failed_attempts = (int) $lockout->failed_attempts + 1;

        if ($lockout->failed_attempts >= self::MAX_LOGIN_ATTEMPTS) {
            $lockout->locked_until = now()->addMinutes(self::LOCKOUT_MINUTES);
        }

        $lockout->save();
    }

    private function clearLockout(string $email): void
    {
        LoginLockout::where('email', $email)->delete();
    }

    public function refresh(string $refreshTokenPlain, string $ip, ?string $userAgent): array
    {
        $hash = hash('sha512', $refreshTokenPlain);

        $stored = RefreshToken::where('token_hash', $hash)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->whereNull('deleted_at')
            ->first();

        if (!$stored) {
            event(new LoginFailed(email: 'unknown', reason: 'invalid_refresh_token'));
            throw new \RuntimeException(__('messages.auth.invalid_refresh_token'));
        }

        $user = User::where('id', $stored->user_id)
            ->whereNull('deleted_at')
            ->firstOrFail();

        try {
            if ($token = JWTAuth::getToken()) {
                $payload = JWTAuth::getPayload();

                TokenBlacklist::firstOrCreate(
                    ['jti' => (string) $payload->get('jti')],
                    [
                        'user_id' => $user->id,
                        'expires_at' => Carbon::createFromTimestamp($payload->get('exp')),
                        'reason' => 'refresh_rotation',
                    ]
                );
            }
        } catch (\Throwable $e) {}

        $stored->update(['revoked_at' => now()]);

        $tenantId = null;
        $tenantUuid = null;

        try {
            if ($token = JWTAuth::getToken()) {
                $payload = JWTAuth::setToken($token)->getPayload();
                $tenantId = $payload->get('tenant_id');
                $tenantUuid = $payload->get('tenant_uuid');
            }
        } catch (\Throwable $e) {}

        $session = $this->createSession($user, $ip, $userAgent, $tenantId, $tenantUuid);

        event(new TokenRefreshed(userUuid: $user->uuid, actorId: $user->id));

        return $session;
    }

    public function logout(string $accessToken): void
    {
        try {
            $payload = JWTAuth::setToken($accessToken)->getPayload();

            TokenBlacklist::firstOrCreate(
                ['jti' => (string) $payload->get('jti')],
                [
                    'user_id' => auth()->id(),
                    'expires_at' => Carbon::createFromTimestamp($payload->get('exp')),
                    'reason' => 'logout',
                ]
            );

            event(new LogoutSucceeded(actorId: auth()->id()));

        } catch (\Throwable $e) {
            event(new LogoutFailed(error: $e->getMessage()));
        }
    }
}
