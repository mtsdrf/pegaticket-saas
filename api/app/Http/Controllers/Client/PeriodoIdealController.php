<?php

namespace App\Http\Controllers\Client;

use App\DTOs\Client\CreatePeriodoIdealDTO;
use App\DTOs\Client\UpdatePeriodoIdealDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StorePeriodoIdealRequest;
use App\Http\Requests\Client\UpdatePeriodoIdealRequest;
use App\Http\Resources\Client\PeriodoIdealResource;
use App\Models\Client\PeriodoIdeal;
use App\Services\APIResponse;
use App\Services\Client\PeriodoIdealService;
use Illuminate\Http\Request;

class PeriodoIdealController extends Controller
{
    public function __construct(
        private PeriodoIdealService $service
    ) {
    }

    public function index(Request $request)
    {
        $tenantId = app('tenant_id');

        $list = $this->service->paginate(
            $tenantId,
            (int) $request->get('per_page', 15),
            $request->only(['name', 'is_active']),
            $request->get('sort_by'),
            (string) $request->get('sort_dir', 'asc')
        );

        return APIResponse::success(
            PeriodoIdealResource::collection($list),
            __('messages.periodo_ideal.list'),
            200,
            [
                'pagination' => [
                    'current_page' => $list->currentPage(),
                    'per_page' => $list->perPage(),
                    'total' => $list->total(),
                    'last_page' => $list->lastPage(),
                ]
            ]
        );
    }

    public function store(StorePeriodoIdealRequest $request)
    {
        $dto = CreatePeriodoIdealDTO::fromArray(
            $request->validated(),
            app('tenant_id')
        );

        try {
            $periodoIdeal = $this->service->create($dto);
        } catch (\App\Exceptions\DuplicateNameException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DUPLICATE_NAME');
        }

        return APIResponse::success(
            new PeriodoIdealResource($periodoIdeal),
            __('messages.periodo_ideal.created'),
            201
        );
    }

    public function update(UpdatePeriodoIdealRequest $request, PeriodoIdeal $periodoIdeal)
    {
        $dto = UpdatePeriodoIdealDTO::fromArray($request->validated());

        try {
            $periodoIdeal = $this->service->update($periodoIdeal, $dto);
        } catch (\App\Exceptions\DuplicateNameException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DUPLICATE_NAME');
        }

        return APIResponse::success(
            new PeriodoIdealResource($periodoIdeal),
            __('messages.periodo_ideal.updated')
        );
    }

    public function destroy(PeriodoIdeal $periodoIdeal)
    {
        $this->service->delete($periodoIdeal);

        return APIResponse::success(
            null,
            __('messages.periodo_ideal.deleted'),
            204
        );
    }
}
