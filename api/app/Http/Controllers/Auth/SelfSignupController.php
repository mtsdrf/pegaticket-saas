<?php

namespace App\Http\Controllers\Auth;

use App\DTOs\Auth\CreateSelfSignupDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreSelfSignupRequest;
use App\Http\Resources\Auth\AuthResource;
use App\Http\Resources\Plan\PublicPlanResource;
use App\Models\Plan\Plan;
use App\Services\APIResponse;
use App\Services\Auth\SelfSignupService;

class SelfSignupController extends Controller
{
    public function __construct(private SelfSignupService $service)
    {
    }

    public function plans()
    {
        $plans = Plan::query()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return APIResponse::success(
            PublicPlanResource::collection($plans),
            __('messages.auth.signup_plans_listed')
        );
    }

    public function store(StoreSelfSignupRequest $request)
    {
        $session = $this->service->register(
            CreateSelfSignupDTO::fromArray($request->validated()),
            $request->ip(),
            $request->userAgent()
        );

        return APIResponse::success(
            new AuthResource($session),
            __('messages.auth.signup_success'),
            201
        );
    }
}
