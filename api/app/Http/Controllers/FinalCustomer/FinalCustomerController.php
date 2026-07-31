<?php

namespace App\Http\Controllers\FinalCustomer;

use App\DTOs\FinalCustomer\SearchFinalCustomerDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\FinalCustomer\ListFinalCustomerRequest;
use App\Http\Resources\FinalCustomer\FinalCustomerTenantLinkResource;
use App\Services\APIResponse;
use App\Services\FinalCustomer\FinalCustomerService;

class FinalCustomerController extends Controller
{
    public function __construct(
        private FinalCustomerService $service
    ) {
    }

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
}
