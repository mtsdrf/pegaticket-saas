<?php

namespace App\Http\Controllers\Balcao;

use App\DTOs\Balcao\CreateTableDTO;
use App\DTOs\Balcao\UpdateTableDTO;
use App\Exceptions\DuplicateNameException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Balcao\StoreTableRequest;
use App\Http\Requests\Balcao\UpdateTableRequest;
use App\Http\Resources\Balcao\TableResource;
use App\Models\Balcao\Table;
use App\Services\APIResponse;
use App\Services\Balcao\TableService;

class TableController extends Controller
{
    public function __construct(
        private TableService $service
    ) {
    }

    public function index()
    {
        $tables = $this->service->list(app('tenant_id'));

        return APIResponse::success(
            TableResource::collection($tables),
            __('messages.table.list')
        );
    }

    public function store(StoreTableRequest $request)
    {
        $dto = CreateTableDTO::fromArray($request->validated(), app('tenant_id'));

        try {
            $table = $this->service->create($dto);
        } catch (DuplicateNameException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DUPLICATE_NAME');
        }

        return APIResponse::success(
            new TableResource($table),
            __('messages.table.created'),
            201
        );
    }

    public function update(UpdateTableRequest $request, Table $table)
    {
        $dto = UpdateTableDTO::fromArray($request->validated());

        try {
            $table = $this->service->update($table, $dto);
        } catch (DuplicateNameException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DUPLICATE_NAME');
        }

        return APIResponse::success(
            new TableResource($table),
            __('messages.table.updated')
        );
    }

    public function destroy(Table $table)
    {
        $this->service->delete($table);

        return APIResponse::success(null, __('messages.table.deleted'), 204);
    }
}
