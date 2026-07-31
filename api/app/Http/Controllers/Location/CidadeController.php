<?php

namespace App\Http\Controllers\Location;

use App\DTOs\Location\CreateCidadeDTO;
use App\DTOs\Location\UpdateCidadeDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Location\ListCidadeRequest;
use App\Http\Requests\Location\StoreCidadeRequest;
use App\Http\Requests\Location\UpdateCidadeRequest;
use App\Http\Resources\Location\CidadeResource;
use App\Models\Location\Cidade;
use App\Services\APIResponse;
use App\Services\Location\CidadeService;

class CidadeController extends Controller
{
    public function __construct(
        private CidadeService $service
    ) {
    }

    public function index(ListCidadeRequest $request)
    {
        $validated = $request->validated();

        $filters = collect($validated)->only([
            'name',
            'estado_name',
            'is_active',
        ])->all();

        $list = $this->service->paginate(
            perPage: (int) ($validated['per_page'] ?? 15),
            estadoUuid: $validated['estado_uuid'] ?? null,
            filters: $filters,
            sortBy: $validated['sort_by'] ?? null,
            sortDir: $validated['sort_dir'] ?? 'asc'
        );

        return APIResponse::success(
            CidadeResource::collection($list),
            __('messages.cidade.list'),
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

    public function store(StoreCidadeRequest $request)
    {
        $dto = CreateCidadeDTO::fromArray($request->validated());

        try {
            $cidade = $this->service->create($dto);
        } catch (\App\Exceptions\DuplicateNameException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DUPLICATE_NAME');
        }

        return APIResponse::success(
            new CidadeResource($cidade),
            __('messages.cidade.created'),
            201
        );
    }

    public function update(UpdateCidadeRequest $request, Cidade $cidade)
    {
        $dto = UpdateCidadeDTO::fromArray($request->validated());

        try {
            $cidade = $this->service->update($cidade, $dto);
        } catch (\App\Exceptions\DuplicateNameException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DUPLICATE_NAME');
        }

        return APIResponse::success(
            new CidadeResource($cidade),
            __('messages.cidade.updated')
        );
    }

    public function destroy(Cidade $cidade)
    {
        $this->service->delete($cidade);

        return APIResponse::success(
            null,
            __('messages.cidade.deleted'),
            204
        );
    }
}
