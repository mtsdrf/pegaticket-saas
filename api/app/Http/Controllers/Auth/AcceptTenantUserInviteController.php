<?php

namespace App\Http\Controllers\Auth;

use App\DTOs\Tenant\AcceptTenantUserInviteDTO;
use App\Exceptions\EmailAlreadyRegisteredException;
use App\Exceptions\InvalidInviteTokenException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptTenantUserInviteRequest;
use App\Http\Resources\Auth\AuthResource;
use App\Services\APIResponse;
use App\Services\Auth\AcceptTenantUserInviteService;

class AcceptTenantUserInviteController extends Controller
{
    public function __construct(
        private AcceptTenantUserInviteService $service
    ) {
    }

    public function store(AcceptTenantUserInviteRequest $request)
    {
        $dto = AcceptTenantUserInviteDTO::fromArray($request->validated());

        try {
            $session = $this->service->accept($dto, $request->ip(), $request->userAgent());
        } catch (InvalidInviteTokenException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_INVITE_TOKEN');
        } catch (EmailAlreadyRegisteredException $e) {
            return APIResponse::error($e->getMessage(), 422, 'EMAIL_ALREADY_REGISTERED');
        }

        return APIResponse::success(
            new AuthResource($session),
            __('messages.tenant_user_invite.accepted'),
            201
        );
    }
}
