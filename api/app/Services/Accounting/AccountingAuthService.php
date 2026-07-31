<?php

namespace App\Services\Accounting;

use App\DTOs\Accounting\AccountingLoginDTO;
use App\DTOs\Accounting\ConfirmTotpDTO;
use App\DTOs\Accounting\RegisterAccountingOfficeDTO;
use App\Events\Accounting\AccountingLoginFailed;
use App\Events\Accounting\AccountingLoginSucceeded;
use App\Events\Accounting\AccountingOfficeRegistered;
use App\Events\Accounting\AccountingTotpEnabled;
use App\Exceptions\EmailAlreadyRegisteredException;
use App\Exceptions\InvalidAccountingCredentialsException;
use App\Exceptions\InvalidTotpCodeException;
use App\Exceptions\TotpNotConfiguredException;
use App\Models\Accounting\AccountingOffice;
use App\Services\Auth\AccountingJWTService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

/**
 * Autenticação do escritório de contabilidade (roadmap 2C) com TOTP
 * OBRIGATÓRIO: o cadastro gera o `totp_secret`, mas o login só é permitido
 * depois de `totp_enabled_at` (confirmação do primeiro código). Réplica em
 * espírito de PortalAuthService/AuthService adaptada para senha + TOTP.
 */
class AccountingAuthService
{
    public function __construct(
        private AccountingJWTService $jwtService,
        private Google2FA $google2fa,
    ) {
    }

    /**
     * @return array{office: AccountingOffice, totp_secret: string, otpauth_uri: string}
     */
    public function register(RegisterAccountingOfficeDTO $dto): array
    {
        return DB::transaction(function () use ($dto) {
            if (AccountingOffice::where('email', $dto->email)->exists()) {
                throw new EmailAlreadyRegisteredException(
                    __('messages.accounting_auth.email_already_registered')
                );
            }

            $secret = $this->google2fa->generateSecretKey();

            $office = AccountingOffice::create([
                'cnpj' => $dto->cnpj,
                'company_name' => $dto->companyName,
                'responsible_name' => $dto->responsibleName,
                'email' => $dto->email,
                'password_hash' => Hash::make($dto->password),
                'totp_secret' => $secret,
                'totp_enabled_at' => null,
            ]);

            event(new AccountingOfficeRegistered(
                accountingOfficeUuid: $office->uuid,
                email: $office->email,
            ));

            return [
                'office' => $office,
                'totp_secret' => $secret,
                'otpauth_uri' => $this->google2fa->getQRCodeUrl(
                    'Maskats Contador',
                    $office->email,
                    $secret
                ),
            ];
        });
    }

    public function confirmTotp(ConfirmTotpDTO $dto): void
    {
        $office = $this->authenticateCredentials($dto->email, $dto->password);

        if (!$office->totp_secret) {
            throw new TotpNotConfiguredException(__('messages.accounting_auth.totp_not_configured'));
        }

        if (!$this->google2fa->verifyKey($office->totp_secret, $dto->code)) {
            throw new InvalidTotpCodeException(__('messages.accounting_auth.invalid_totp'));
        }

        if (!$office->isTotpEnabled()) {
            $office->forceFill(['totp_enabled_at' => now()])->save();

            event(new AccountingTotpEnabled(accountingOfficeUuid: $office->uuid));
        }
    }

    /**
     * @return array{access_token: string, token_type: string, expires_in: int}
     */
    public function login(AccountingLoginDTO $dto): array
    {
        $office = AccountingOffice::where('email', $dto->email)->first();

        if (!$office || !Hash::check($dto->password, $office->password_hash)) {
            event(new AccountingLoginFailed(email: $dto->email, reason: 'invalid_credentials'));

            throw new InvalidAccountingCredentialsException(
                __('messages.accounting_auth.invalid_credentials')
            );
        }

        if (!$office->isTotpEnabled()) {
            event(new AccountingLoginFailed(email: $dto->email, reason: 'totp_not_configured'));

            throw new TotpNotConfiguredException(__('messages.accounting_auth.totp_setup_required'));
        }

        if (!$this->google2fa->verifyKey((string) $office->totp_secret, $dto->code)) {
            event(new AccountingLoginFailed(email: $dto->email, reason: 'invalid_totp'));

            throw new InvalidTotpCodeException(__('messages.accounting_auth.invalid_totp'));
        }

        event(new AccountingLoginSucceeded(accountingOfficeUuid: $office->uuid));

        return [
            'access_token' => $this->jwtService->issueAccessToken($office),
            'token_type' => 'Bearer',
            'expires_in' => (int) config('jwt.ttl') * 60,
        ];
    }

    private function authenticateCredentials(string $email, string $password): AccountingOffice
    {
        $office = AccountingOffice::where('email', $email)->first();

        if (!$office || !Hash::check($password, $office->password_hash)) {
            throw new InvalidAccountingCredentialsException(
                __('messages.accounting_auth.invalid_credentials')
            );
        }

        return $office;
    }
}
