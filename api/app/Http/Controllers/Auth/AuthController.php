<?php

namespace App\Http\Controllers\Auth;

use App\DTOs\Auth\ForgotPasswordDTO;
use App\DTOs\Auth\ResetPasswordDTO;
use App\Exceptions\InvalidPasswordResetTokenException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\Auth\AuthResource;
use App\Services\APIResponse;
use App\Services\Auth\AuthService;
use App\Services\Auth\PasswordResetService;
use App\Services\Permission\PermissionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService,
        private PermissionService $permissionService,
        private PasswordResetService $passwordResetService
    )
    {
    }

    public function login(LoginRequest $request)
    {
        try {
            $data = $this->authService->login(
                $request->string('email'),
                $request->string('password'),
                $request->ip(),
                $request->userAgent()
            );

            return APIResponse::success(
                new AuthResource($data),
                __('messages.auth.login_success'),
                200
            );
        } catch (\RuntimeException $e) {
            return APIResponse::error($e->getMessage(), 401, 'INVALID_CREDENTIALS');
        }
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $dto = ForgotPasswordDTO::fromArray($request->validated());

        $this->passwordResetService->requestReset($dto);

        return APIResponse::success(
            null,
            __('messages.auth.password_reset_requested')
        );
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $dto = ResetPasswordDTO::fromArray($request->validated());

        try {
            $this->passwordResetService->resetPassword($dto);
        } catch (InvalidPasswordResetTokenException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_PASSWORD_RESET_TOKEN');
        }

        return APIResponse::success(
            null,
            __('messages.auth.password_reset_success')
        );
    }

    public function logout(Request $request)
    {
        try {
            // Pega o token do header
            $token = $request->bearerToken();

            if (!$token) {
                return APIResponse::error(
                    __('messages.auth.token_not_provided'),
                    401,
                    'TOKEN_NOT_PROVIDED'
                );
            }

            $this->permissionService->invalidateUser(Auth::id());
            $this->authService->logout($token);

            return APIResponse::success(
                null,
                __('messages.auth.logout_success')
            );
        } catch (\Throwable $e) {
            return APIResponse::error(
                $e->getMessage(),
                500,
                'LOGOUT_ERROR'
            );
        }
    }
}