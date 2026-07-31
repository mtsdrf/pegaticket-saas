<?php

namespace App\Http\Controllers\Accounting;

use App\DTOs\Accounting\AccountingLoginDTO;
use App\DTOs\Accounting\ConfirmTotpDTO;
use App\DTOs\Accounting\RegisterAccountingOfficeDTO;
use App\Exceptions\EmailAlreadyRegisteredException;
use App\Exceptions\InvalidAccountingCredentialsException;
use App\Exceptions\InvalidTotpCodeException;
use App\Exceptions\TotpNotConfiguredException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\AccountingLoginRequest;
use App\Http\Requests\Accounting\ConfirmTotpRequest;
use App\Http\Requests\Accounting\RegisterAccountingOfficeRequest;
use App\Http\Resources\Accounting\AccountingOfficeResource;
use App\Services\Accounting\AccountingAuthService;
use App\Services\APIResponse;

class AccountingAuthController extends Controller
{
    public function __construct(
        private AccountingAuthService $service
    ) {
    }

    public function register(RegisterAccountingOfficeRequest $request)
    {
        try {
            $result = $this->service->register(
                RegisterAccountingOfficeDTO::fromArray($request->validated())
            );
        } catch (EmailAlreadyRegisteredException $e) {
            return APIResponse::error($e->getMessage(), 422, 'EMAIL_ALREADY_REGISTERED');
        }

        return APIResponse::success([
            'office' => new AccountingOfficeResource($result['office']),
            // Front usa isso para gerar o QR Code (ou exibir o secret em texto).
            // TOTP ainda precisa ser confirmado antes do primeiro login.
            'totp_secret' => $result['totp_secret'],
            'otpauth_uri' => $result['otpauth_uri'],
        ], __('messages.accounting_auth.registered'), 201);
    }

    public function confirmTotp(ConfirmTotpRequest $request)
    {
        try {
            $this->service->confirmTotp(ConfirmTotpDTO::fromArray($request->validated()));
        } catch (InvalidAccountingCredentialsException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_CREDENTIALS');
        } catch (TotpNotConfiguredException $e) {
            return APIResponse::error($e->getMessage(), 422, 'TOTP_NOT_CONFIGURED');
        } catch (InvalidTotpCodeException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_TOTP');
        }

        return APIResponse::success(null, __('messages.accounting_auth.totp_enabled'));
    }

    public function login(AccountingLoginRequest $request)
    {
        try {
            $session = $this->service->login(AccountingLoginDTO::fromArray($request->validated()));
        } catch (TotpNotConfiguredException $e) {
            return APIResponse::error($e->getMessage(), 403, 'TOTP_SETUP_REQUIRED');
        } catch (InvalidAccountingCredentialsException $e) {
            return APIResponse::error($e->getMessage(), 401, 'INVALID_CREDENTIALS');
        } catch (InvalidTotpCodeException $e) {
            return APIResponse::error($e->getMessage(), 401, 'INVALID_TOTP');
        }

        return APIResponse::success($session, __('messages.accounting_auth.logged_in'));
    }

    public function me()
    {
        return APIResponse::success(
            new AccountingOfficeResource(accounting_office()),
            __('messages.accounting_auth.me')
        );
    }
}
