<?php

namespace App\Http\Controllers\Tenant;

use App\DTOs\Tenant\CreateTenantUserInviteDTO;
use App\Exceptions\DuplicateInviteException;
use App\Exceptions\EmailAlreadyRegisteredException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\InviteTenantUserRequest;
use App\Http\Resources\Tenant\TenantUserInviteResource;
use App\Services\APIResponse;
use App\Services\Tenant\TenantUserInviteService;

class TenantUserInviteController extends Controller
{
    public function __construct(
        private TenantUserInviteService $service
    ) {
    }

    public function store(InviteTenantUserRequest $request)
    {
        $dto = CreateTenantUserInviteDTO::fromArray($request->validated());

        try {
            $invite = $this->service->create($dto, (int) app('tenant_id'));
        } catch (EmailAlreadyRegisteredException $e) {
            return APIResponse::error($e->getMessage(), 422, 'EMAIL_ALREADY_REGISTERED');
        } catch (DuplicateInviteException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DUPLICATE_INVITE');
        }

        return APIResponse::success(
            new TenantUserInviteResource($invite),
            __('messages.tenant_user_invite.created'),
            201
        );
    }
}
