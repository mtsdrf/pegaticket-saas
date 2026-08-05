<?php

namespace App\Http\Controllers\Report;

use App\DTOs\Report\CreateScheduledReportSubscriptionDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\StoreScheduledReportSubscriptionRequest;
use App\Http\Resources\Report\ScheduledReportSubscriptionResource;
use App\Services\APIResponse;
use App\Services\Report\ScheduledReportSubscriptionService;

/**
 * CRUD simples de relatório agendado (roadmap A2) — e-mail diário/semanal
 * com o resumo dos KPIs do Home. Ver
 * App\Services\Report\ScheduledReportSubscriptionService.
 */
class ScheduledReportSubscriptionController extends Controller
{
    public function __construct(
        private ScheduledReportSubscriptionService $service
    ) {}

    public function index()
    {
        $list = $this->service->listForTenant((int) app('tenant_id'));

        return APIResponse::success(
            ScheduledReportSubscriptionResource::collection($list),
            __('messages.report.scheduled_subscriptions_listed')
        );
    }

    public function store(StoreScheduledReportSubscriptionRequest $request)
    {
        $dto = CreateScheduledReportSubscriptionDTO::fromArray($request->validated(), (int) app('tenant_id'));

        $subscription = $this->service->create($dto);

        return APIResponse::success(
            new ScheduledReportSubscriptionResource($subscription),
            __('messages.report.scheduled_subscription_created'),
            201
        );
    }

    public function destroy(string $uuid)
    {
        $this->service->cancel((int) app('tenant_id'), $uuid);

        return APIResponse::success(
            null,
            __('messages.report.scheduled_subscription_cancelled'),
            204
        );
    }
}
