<?php

namespace App\Http\Controllers\Privacy;

use App\DTOs\Privacy\CreatePrivacyRequestDTO;
use App\DTOs\Privacy\UpdatePrivacyRequestDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Privacy\CreatePrivacyRequestRequest;
use App\Http\Requests\Privacy\UpdatePrivacyRequestRequest;
use App\Http\Resources\Privacy\PrivacyRequestResource;
use App\Services\APIResponse;
use App\Services\Privacy\PrivacyRequestService;

class PrivacyRequestController extends Controller
{
    public function __construct(
        private PrivacyRequestService $service
    ) {
    }

    public function index()
    {
        $items = $this->service->listForTenant(app('tenant_id'));

        return APIResponse::success(
            PrivacyRequestResource::collection($items),
            __('messages.privacy_request.list')
        );
    }

    public function store(CreatePrivacyRequestRequest $request)
    {
        $item = $this->service->create(
            app('tenant_id'),
            CreatePrivacyRequestDTO::fromArray($request->validated())
        );

        return APIResponse::success(
            new PrivacyRequestResource($item),
            __('messages.privacy_request.created'),
            201
        );
    }

    public function update(UpdatePrivacyRequestRequest $request, string $uuid)
    {
        $item = $this->service->update(
            app('tenant_id'),
            $uuid,
            UpdatePrivacyRequestDTO::fromArray($request->validated())
        );

        return APIResponse::success(
            new PrivacyRequestResource($item),
            __('messages.privacy_request.updated')
        );
    }
}
