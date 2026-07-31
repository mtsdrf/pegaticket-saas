<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Services\APIResponse;
use App\Services\Onboarding\OnboardingService;

class OnboardingController extends Controller
{
    public function __construct(
        private OnboardingService $service
    ) {
    }

    public function checklist()
    {
        $checklist = $this->service->checklist(app('tenant_id'), app('tenant_user'));

        return APIResponse::success($checklist, __('messages.onboarding.checklist'));
    }

    public function dismiss()
    {
        $checklist = $this->service->dismiss(app('tenant_user'));

        return APIResponse::success($checklist, __('messages.onboarding.dismissed'));
    }

    public function restore()
    {
        $checklist = $this->service->restore(app('tenant_user'));

        return APIResponse::success($checklist, __('messages.onboarding.restored'));
    }
}
