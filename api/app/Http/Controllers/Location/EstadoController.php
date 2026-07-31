<?php

namespace App\Http\Controllers\Location;

use App\DTOs\Location\CreateEstadoDTO;
use App\DTOs\Location\UpdateEstadoDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Location\ListEstadoRequest;
use App\Http\Requests\Location\StoreEstadoRequest;
use App\Http\Requests\Location\UpdateEstadoRequest;
use App\Http\Resources\Location\EstadoResource;
use App\Models\Location\Estado;
use App\Services\APIResponse;
use App\Services\Location\EstadoService;

class EstadoController extends Controller
{
    public function __construct(
        private EstadoService $service
    ) {
    }

    public function index(ListEstadoRequest $request)
    {
        $validated = $request->validated();

        $filters = collect($validated)->only([
            'name',
            'uf',
            'is_active',
        ])->all();

        $list = $this->service->paginate(
            (int) ($validated['per_page'] ?? 15),
            $filters,
            $validated['sort_by'] ?? null,
            $validated['sort_dir'] ?? 'asc'
        );

        return APIResponse::success(
            EstadoResource::collection($list),
            __('messages.estado.list'),
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

    public function store(StoreEstadoRequest $request)
    {
        $dto = CreateEstadoDTO::fromArray($request->validated());

        try {
            $estado = $this->service->create($dto);
        } catch (\App\Exceptions\DuplicateNameException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DUPLICATE_NAME');
        }

        return APIResponse::success(
            new EstadoResource($estado),
            __('messages.estado.created'),
            201
        );
    }

    public function update(UpdateEstadoRequest $request, Estado $estado)
    {
        $dto = UpdateEstadoDTO::fromArray($request->validated());

        try {
            $estado = $this->service->update($estado, $dto);
        } catch (\App\Exceptions\DuplicateNameException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DUPLICATE_NAME');
        }

        return APIResponse::success(
            new EstadoResource($estado),
            __('messages.estado.updated')
        );
    }

    public function destroy(Estado $estado)
    {
        $this->service->delete($estado);

        return APIResponse::success(
            null,
            __('messages.estado.deleted'),
            204
        );
    }
}
