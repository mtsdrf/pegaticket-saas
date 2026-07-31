<?php

namespace App\Http\Controllers\Auth;

use App\DTOs\Auth\RequestEmailChangeDTO;
use App\DTOs\Auth\UpdatePasswordDTO;
use App\DTOs\Auth\UpdateProfileDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestEmailChangeRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\Auth\ProfileResource;
use App\Services\APIResponse;
use App\Services\Auth\ProfileService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        private ProfileService $service
    ) {
    }

    public function show(Request $request)
    {
        return APIResponse::success(
            new ProfileResource($request->user()),
            __('messages.profile.show')
        );
    }

    public function update(UpdateProfileRequest $request)
    {
        $dto = UpdateProfileDTO::fromArray($request->validated());

        $user = $this->service->update($request->user(), $dto);

        return APIResponse::success(
            new ProfileResource($user),
            __('messages.profile.updated')
        );
    }

    public function changePassword(UpdatePasswordRequest $request)
    {
        $dto = UpdatePasswordDTO::fromArray($request->validated());

        $this->service->changePassword($request->user(), $dto);

        return APIResponse::success(
            null,
            __('messages.profile.password_changed')
        );
    }

    public function requestEmailChange(RequestEmailChangeRequest $request)
    {
        $dto = RequestEmailChangeDTO::fromArray($request->validated());

        $this->service->requestEmailChange($request->user(), $dto);

        return APIResponse::success(
            null,
            __('messages.profile.email_change_requested')
        );
    }
}
