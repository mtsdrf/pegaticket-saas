<?php

namespace App\Http\Controllers\Location;

use App\DTOs\Location\CreateBairroDTO;
use App\DTOs\Location\UpdateBairroDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Location\ListBairroRequest;
use App\Http\Requests\Location\StoreBairroRequest;
use App\Http\Requests\Location\UpdateBairroRequest;
use App\Http\Resources\Location\BairroResource;
use App\Models\Location\Bairro;
use App\Services\APIResponse;
use App\Services\Location\BairroService;

class BairroController extends Controller
{
    public function __construct(
        private BairroService $service
    ) {
    }

    public function index(ListBairroRequest $request)
    {
        $validated = $request->validated();

        $filters = collect($validated)->only([
            'name',
            'cidade_name',
            'is_active',
        ])->all();

        $list = $this->service->paginate(
            perPage: (int) ($validated['per_page'] ?? 15),
            cidadeUuid: $validated['cidade_uuid'] ?? null,
            filters: $filters,
            sortBy: $validated['sort_by'] ?? null,
            sortDir: $validated['sort_dir'] ?? 'asc'
        );

        return APIResponse::success(
            BairroResource::collection($list),
            __('messages.bairro.list'),
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

    public function store(StoreBairroRequest $request)
    {
        $dto = CreateBairroDTO::fromArray($request->validated());

        try {
            $bairro = $this->service->create($dto);
        } catch (\App\Exceptions\DuplicateNameException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DUPLICATE_NAME');
        }

        return APIResponse::success(
            new BairroResource($bairro),
            __('messages.bairro.created'),
            201
        );
    }

    public function update(UpdateBairroRequest $request, Bairro $bairro)
    {
        $dto = UpdateBairroDTO::fromArray($request->validated());

        try {
            $bairro = $this->service->update($bairro, $dto);
        } catch (\App\Exceptions\DuplicateNameException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DUPLICATE_NAME');
        }

        return APIResponse::success(
            new BairroResource($bairro),
            __('messages.bairro.updated')
        );
    }

    public function destroy(Bairro $bairro)
    {
        $this->service->delete($bairro);

        return APIResponse::success(
            null,
            __('messages.bairro.deleted'),
            204
        );
    }
}
