<?php

namespace App\Http\Controllers\Auth;

use App\DTOs\Auth\ConfirmEmailChangeDTO;
use App\Exceptions\InvalidEmailConfirmationTokenException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmEmailRequest;
use App\Services\APIResponse;
use App\Services\Auth\ProfileService;

class ConfirmEmailController extends Controller
{
    public function __construct(
        private ProfileService $service
    ) {
    }

    public function store(ConfirmEmailRequest $request)
    {
        $dto = ConfirmEmailChangeDTO::fromArray($request->validated());

        try {
            $this->service->confirmEmailChange($dto);
        } catch (InvalidEmailConfirmationTokenException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_EMAIL_CONFIRMATION_TOKEN');
        }

        return APIResponse::success(
            null,
            __('messages.profile.email_confirmed')
        );
    }
}
