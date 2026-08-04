<?php

namespace App\Http\Controllers\Affiliate;

use App\DTOs\Affiliate\CreateAffiliateDTO;
use App\DTOs\Affiliate\UpdateAffiliateDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Affiliate\CreateAffiliateRequest;
use App\Http\Requests\Affiliate\UpdateAffiliateRequest;
use App\Http\Resources\Affiliate\AffiliateCommissionResource;
use App\Http\Resources\Affiliate\AffiliateResource;
use App\Services\Affiliate\AffiliateService;
use App\Services\APIResponse;

class AffiliateController extends Controller
{
    public function __construct(
        private AffiliateService $service
    ) {}

    public function index()
    {
        $affiliates = $this->service->paginate(app('tenant_id'));

        return APIResponse::success(
            AffiliateResource::collection($affiliates),
            __('messages.affiliate.list'),
            200,
            ['pagination' => [
                'current_page' => $affiliates->currentPage(),
                'per_page' => $affiliates->perPage(),
                'total' => $affiliates->total(),
                'last_page' => $affiliates->lastPage(),
            ]]
        );
    }

    public function store(CreateAffiliateRequest $request)
    {
        $dto = CreateAffiliateDTO::fromArray($request->validated());

        $affiliate = $this->service->create(app('tenant_id'), $dto);

        return APIResponse::success(
            new AffiliateResource($affiliate),
            __('messages.affiliate.created'),
            201
        );
    }

    public function show(string $uuid)
    {
        $affiliate = $this->service->find(app('tenant_id'), $uuid);

        return APIResponse::success(
            new AffiliateResource($affiliate),
            __('messages.affiliate.show')
        );
    }

    public function update(UpdateAffiliateRequest $request, string $uuid)
    {
        $dto = UpdateAffiliateDTO::fromArray($request->validated());

        $affiliate = $this->service->update(app('tenant_id'), $uuid, $dto);

        return APIResponse::success(
            new AffiliateResource($affiliate),
            __('messages.affiliate.updated')
        );
    }

    public function commissions(string $uuid)
    {
        $commissions = $this->service->paginateCommissions(app('tenant_id'), $uuid);

        return APIResponse::success(
            AffiliateCommissionResource::collection($commissions),
            __('messages.affiliate.commissions_list'),
            200,
            ['pagination' => [
                'current_page' => $commissions->currentPage(),
                'per_page' => $commissions->perPage(),
                'total' => $commissions->total(),
                'last_page' => $commissions->lastPage(),
            ]]
        );
    }
}
