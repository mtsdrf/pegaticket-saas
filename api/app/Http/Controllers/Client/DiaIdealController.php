<?php

namespace App\Http\Controllers\Client;

use App\DTOs\Client\CreateDiaIdealDTO;
use App\DTOs\Client\UpdateDiaIdealDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreDiaIdealRequest;
use App\Http\Requests\Client\UpdateDiaIdealRequest;
use App\Http\Resources\Client\DiaIdealResource;
use App\Models\Client\DiaIdeal;
use App\Services\APIResponse;
use App\Services\Client\DiaIdealService;
use Illuminate\Http\Request;

class DiaIdealController extends Controller
{
    public function __construct(
        private DiaIdealService $service
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
            DiaIdealResource::collection($list),
            __('messages.dia_ideal.list'),
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

    public function store(StoreDiaIdealRequest $request)
    {
        $dto = CreateDiaIdealDTO::fromArray(
            $request->validated(),
            app('tenant_id')
        );

        try {
            $diaIdeal = $this->service->create($dto);
        } catch (\App\Exceptions\DuplicateNameException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DUPLICATE_NAME');
        }

        return APIResponse::success(
            new DiaIdealResource($diaIdeal),
            __('messages.dia_ideal.created'),
            201
        );
    }

    public function update(UpdateDiaIdealRequest $request, DiaIdeal $diaIdeal)
    {
        $dto = UpdateDiaIdealDTO::fromArray($request->validated());

        try {
            $diaIdeal = $this->service->update($diaIdeal, $dto);
        } catch (\App\Exceptions\DuplicateNameException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DUPLICATE_NAME');
        }

        return APIResponse::success(
            new DiaIdealResource($diaIdeal),
            __('messages.dia_ideal.updated')
        );
    }

    public function destroy(DiaIdeal $diaIdeal)
    {
        $this->service->delete($diaIdeal);

        return APIResponse::success(
            null,
            __('messages.dia_ideal.deleted'),
            204
        );
    }
}
