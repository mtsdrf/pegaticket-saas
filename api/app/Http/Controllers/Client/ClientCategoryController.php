<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreClientCategoryRequest;
use App\Http\Requests\Client\UpdateClientCategoryRequest;
use App\Http\Resources\Client\ClientCategoryResource;
use App\Models\Client\ClientCategory;
use App\Services\APIResponse;
use App\Services\Client\ClientCategoryService;
use Illuminate\Http\Request;
use App\DTOs\Client\CreateClientCategoryDTO;
use App\DTOs\Client\UpdateClientCategoryDTO;

class ClientCategoryController extends Controller
{
    public function __construct(
        private ClientCategoryService $service
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
            ClientCategoryResource::collection($list),
            __('messages.client_category.list'),
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

    public function store(StoreClientCategoryRequest $request)
    {
        $dto = CreateClientCategoryDTO::fromArray(
            $request->validated(),
            app('tenant_id')
        );

        try {
            $category = $this->service->create($dto);
        } catch (\App\Exceptions\DuplicateNameException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DUPLICATE_NAME');
        }

        return APIResponse::success(
            new ClientCategoryResource($category),
            __('messages.client_category.created'),
            201
        );
    }

    public function update(UpdateClientCategoryRequest $request, ClientCategory $clientCategory)
    {
        $dto = UpdateClientCategoryDTO::fromArray($request->validated());

        try {
            $category = $this->service->update($clientCategory, $dto);
        } catch (\App\Exceptions\DuplicateNameException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DUPLICATE_NAME');
        }

        return APIResponse::success(
            new ClientCategoryResource($category),
            __('messages.client_category.updated')
        );
    }

    public function destroy(ClientCategory $clientCategory)
    {
        $this->service->delete($clientCategory);

        return APIResponse::success(
            null,
            __('messages.client_category.deleted'),
            204
        );
    }
}
