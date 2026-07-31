<?php

namespace App\Http\Controllers\Fiscal;

use App\DTOs\Fiscal\CreateFiscalOperationProfileDTO;
use App\DTOs\Fiscal\UpdateFiscalOperationProfileDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fiscal\FiscalOperationProfileRequest;
use App\Http\Resources\Fiscal\FiscalOperationProfileResource;
use App\Services\APIResponse;
use App\Services\Fiscal\FiscalOperationProfileService;

class FiscalOperationProfileController extends Controller
{
    public function __construct(
        private FiscalOperationProfileService $service
    ) {
    }

    public function index()
    {
        return APIResponse::success(
            FiscalOperationProfileResource::collection($this->service->list(app('tenant_id'))),
            __('messages.fiscal_operation_profile.list')
        );
    }

    public function store(FiscalOperationProfileRequest $request)
    {
        $profile = $this->service->create(
            app('tenant_id'),
            CreateFiscalOperationProfileDTO::fromArray($request->validated())
        );

        return APIResponse::success(
            new FiscalOperationProfileResource($profile),
            __('messages.fiscal_operation_profile.created'),
            201
        );
    }

    public function update(string $uuid, FiscalOperationProfileRequest $request)
    {
        $profile = $this->service->update(
            app('tenant_id'),
            $uuid,
            new UpdateFiscalOperationProfileDTO(
                name: trim($request->validated('name')),
                operationNature: $request->validated('operation_nature'),
                documentType: $request->validated('document_type'),
                defaultCfop: isset($request->validated()['default_cfop']) && $request->validated()['default_cfop'] !== ''
                    ? trim($request->validated()['default_cfop'])
                    : null,
                scope: $request->validated('scope'),
                description: isset($request->validated()['description']) && trim((string) $request->validated()['description']) !== ''
                    ? trim((string) $request->validated()['description'])
                    : null,
                isActive: (bool) ($request->validated()['is_active'] ?? true),
            )
        );

        return APIResponse::success(
            new FiscalOperationProfileResource($profile),
            __('messages.fiscal_operation_profile.updated')
        );
    }

    public function destroy(string $uuid)
    {
        $this->service->delete(app('tenant_id'), $uuid);

        return APIResponse::success(
            null,
            __('messages.fiscal_operation_profile.deleted'),
            204
        );
    }
}
