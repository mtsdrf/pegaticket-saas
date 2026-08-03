<?php

namespace App\Http\Controllers\Portal;

use App\DTOs\Portal\RequestOtpDTO;
use App\DTOs\Portal\VerifyOtpDTO;
use App\Exceptions\InvalidOtpException;
use App\Exceptions\PortalOtpDeliveryException;
use App\Exceptions\TooManyOtpAttemptsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\RequestOtpRequest;
use App\Http\Requests\Portal\VerifyOtpRequest;
use App\Services\APIResponse;
use App\Services\Portal\PortalAuthService;

class PortalAuthController extends Controller
{
    public function __construct(
        private PortalAuthService $service
    ) {
    }

    public function requestOtp(RequestOtpRequest $request)
    {
        $dto = RequestOtpDTO::fromArray($request->validated());

        try {
            $this->service->requestOtp($dto);
        } catch (PortalOtpDeliveryException $e) {
            return APIResponse::error($e->getMessage(), 503, 'OTP_DELIVERY_UNAVAILABLE');
        }

        // Resposta genérica SEMPRE, independente do e-mail já ter conta ou
        // não — nunca revelar existência de cadastro (mesma lógica de
        // qualquer fluxo de OTP/reset de senha decente).
        return APIResponse::success(
            ['expires_in_minutes' => PortalAuthService::otpExpiresInMinutes()],
            __('messages.portal.otp_sent')
        );
    }

    public function verifyOtp(VerifyOtpRequest $request)
    {
        $dto = VerifyOtpDTO::fromArray($request->validated());

        try {
            $session = $this->service->verifyOtp($dto);
        } catch (TooManyOtpAttemptsException $e) {
            return APIResponse::error($e->getMessage(), 422, 'TOO_MANY_ATTEMPTS');
        } catch (InvalidOtpException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_OTP');
        }

        return APIResponse::success($session, __('messages.portal.otp_verified'));
    }
}
