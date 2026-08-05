<?php

namespace App\Http\Controllers\EmailTemplate;

use App\DTOs\EmailTemplate\UpsertEmailTemplateDTO;
use App\Exceptions\InvalidEmailTemplateTypeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\EmailTemplate\UpdateEmailTemplateRequest;
use App\Http\Resources\EmailTemplate\EmailTemplateResource;
use App\Services\APIResponse;
use App\Services\EmailTemplate\EmailTemplateService;

class EmailTemplateController extends Controller
{
    public function __construct(
        private EmailTemplateService $service
    ) {}

    public function index()
    {
        $tenantId = app('tenant_id');

        $list = $this->service->listForTenant($tenantId);

        return APIResponse::success(
            EmailTemplateResource::collection(collect($list)),
            __('messages.email_template.list')
        );
    }

    public function show(string $type)
    {
        $tenantId = app('tenant_id');

        try {
            $data = $this->service->findForTenant($tenantId, $type);
        } catch (InvalidEmailTemplateTypeException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_EMAIL_TEMPLATE_TYPE');
        }

        return APIResponse::success(
            new EmailTemplateResource($data),
            __('messages.email_template.show')
        );
    }

    public function update(UpdateEmailTemplateRequest $request, string $type)
    {
        $dto = UpsertEmailTemplateDTO::fromArray($request->validated(), app('tenant_id'), $type);

        try {
            $this->service->upsert($dto);
        } catch (InvalidEmailTemplateTypeException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_EMAIL_TEMPLATE_TYPE');
        }

        $data = $this->service->findForTenant(app('tenant_id'), $type);

        return APIResponse::success(
            new EmailTemplateResource($data),
            __('messages.email_template.updated')
        );
    }

    public function destroy(string $type)
    {
        $tenantId = app('tenant_id');

        try {
            $this->service->reset($tenantId, $type);
        } catch (InvalidEmailTemplateTypeException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_EMAIL_TEMPLATE_TYPE');
        }

        return APIResponse::success(
            null,
            __('messages.email_template.reset'),
            204
        );
    }
}
