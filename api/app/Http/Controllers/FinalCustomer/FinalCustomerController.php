<?php

namespace App\Http\Controllers\FinalCustomer;

use App\DTOs\FinalCustomer\CrmFinalCustomerFilterDTO;
use App\DTOs\FinalCustomer\SearchFinalCustomerDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\FinalCustomer\ListFinalCustomerCrmRequest;
use App\Http\Requests\FinalCustomer\ListFinalCustomerRequest;
use App\Http\Resources\FinalCustomer\FinalCustomerCrmResource;
use App\Http\Resources\FinalCustomer\FinalCustomerTenantLinkResource;
use App\Services\APIResponse;
use App\Services\FinalCustomer\FinalCustomerService;

class FinalCustomerController extends Controller
{
    public function __construct(
        private FinalCustomerService $service
    ) {}

    public function index(ListFinalCustomerRequest $request)
    {
        $tenantId = app('tenant_id');
        $dto = SearchFinalCustomerDTO::fromArray($request->validated());

        $list = $this->service->paginate($tenantId, $dto);

        return APIResponse::success(
            FinalCustomerTenantLinkResource::collection($list),
            __('messages.customers.listed'),
            200,
            [
                'pagination' => [
                    'current_page' => $list->currentPage(),
                    'per_page' => $list->perPage(),
                    'total' => $list->total(),
                    'last_page' => $list->lastPage(),
                ],
            ]
        );
    }

    /**
     * CRM básico (Fase 6, fatia final) — lista compradores do tenant com
     * total gasto/quantidade de compras/última compra agregados, com
     * filtros de segmentação simples (min_spent/min_purchases/inactive_days).
     */
    public function crm(ListFinalCustomerCrmRequest $request)
    {
        $tenantId = app('tenant_id');
        $dto = CrmFinalCustomerFilterDTO::fromArray($request->validated());

        $list = $this->service->crm($tenantId, $dto);

        return APIResponse::success(
            FinalCustomerCrmResource::collection($list),
            __('messages.customers.crm_listed'),
            200,
            [
                'pagination' => [
                    'current_page' => $list->currentPage(),
                    'per_page' => $list->perPage(),
                    'total' => $list->total(),
                    'last_page' => $list->lastPage(),
                ],
            ]
        );
    }
}
