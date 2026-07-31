<?php

namespace App\Http\Controllers\Location;

use App\DTOs\Location\CreateEnderecoDTO;
use App\DTOs\Location\UpdateEnderecoDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Location\ListEnderecoRequest;
use App\Http\Requests\Location\StoreEnderecoRequest;
use App\Http\Requests\Location\UpdateEnderecoRequest;
use App\Http\Resources\Location\EnderecoResource;
use App\Models\Location\Endereco;
use App\Services\APIResponse;
use App\Services\Location\EnderecoService;

class EnderecoController extends Controller
{
    public function __construct(
        private EnderecoService $service
    ) {
    }

    public function index(ListEnderecoRequest $request)
    {
        $tenantId = app('tenant_id');
        $validated = $request->validated();

        $filters = collect($validated)->only([
            'logradouro',
            'cep',
            'cidade_name',
            'bairro_name',
            'is_active',
        ])->all();

        $list = $this->service->paginate(
            $tenantId,
            (int) ($validated['per_page'] ?? 15),
            $filters,
            $validated['sort_by'] ?? null,
            $validated['sort_dir'] ?? 'asc'
        );

        return APIResponse::success(
            EnderecoResource::collection($list),
            __('messages.endereco.list'),
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

    public function store(StoreEnderecoRequest $request)
    {
        $dto = CreateEnderecoDTO::fromArray(
            $request->validated(),
            app('tenant_id')
        );

        try {
            $endereco = $this->service->create($dto);
        } catch (\App\Exceptions\LocationChainException $e) {
            return APIResponse::error($e->getMessage(), 422, 'LOCATION_CHAIN_MISMATCH');
        }

        return APIResponse::success(
            new EnderecoResource($endereco),
            __('messages.endereco.created'),
            201
        );
    }

    public function update(UpdateEnderecoRequest $request, Endereco $endereco)
    {
        $dto = UpdateEnderecoDTO::fromArray($request->validated());

        try {
            $endereco = $this->service->update($endereco, $dto);
        } catch (\App\Exceptions\LocationChainException $e) {
            return APIResponse::error($e->getMessage(), 422, 'LOCATION_CHAIN_MISMATCH');
        }

        return APIResponse::success(
            new EnderecoResource($endereco),
            __('messages.endereco.updated')
        );
    }

    public function destroy(Endereco $endereco)
    {
        $this->service->delete($endereco);

        return APIResponse::success(
            null,
            __('messages.endereco.deleted'),
            204
        );
    }
}
