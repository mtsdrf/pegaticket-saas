<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RefreshRequest;
use App\Http\Resources\Auth\AuthResource;
use App\Services\APIResponse;
use App\Services\Auth\AuthService;

class RefreshTokenController extends Controller
{
    public function __construct(private AuthService $authService)
    {
    }

    public function refresh(RefreshRequest $request)
    {
        try {
            $data = $this->authService->refresh(
                $request->string('refresh_token'),
                $request->ip(),
                $request->userAgent()
            );

            return APIResponse::success(
                new AuthResource($data),
                __('messages.auth.refresh_success'),
                200
            );
        } catch (\RuntimeException $e) {
            return APIResponse::error($e->getMessage(), 401, 'INVALID_REFRESH_TOKEN');
        }
    }
}