<?php

namespace App\Http\Controllers\Functionality;

use App\Http\Controllers\Controller;
use App\Http\Requests\Functionality\StoreFunctionalityRequest;
use App\Http\Requests\Functionality\UpdateFunctionalityRequest;
use App\Http\Resources\Functionality\FunctionalityResource;
use App\Models\Functionality\Functionality;
use App\Services\APIResponse;
use App\Services\Functionality\FunctionalityService;
use Illuminate\Http\Request;
use App\DTOs\Functionality\CreateFunctionalityDTO;
use App\DTOs\Functionality\UpdateFunctionalityDTO;

class FunctionalityController extends Controller
{
    public function __construct(
        private FunctionalityService $service
    ) {
    }

    public function index(Request $request)
    {
        $list = $this->service->paginate(
            (int) $request->get('per_page', 15),
            $request->only(['name', 'slug', 'description', 'is_active']),
            $request->get('sort_by'),
            (string) $request->get('sort_dir', 'asc')
        );

        return APIResponse::success(
            FunctionalityResource::collection($list),
            __('messages.functionality.list'),
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

    public function store(StoreFunctionalityRequest $request)
    {
        $dto = CreateFunctionalityDTO::fromArray($request->validated());
        $func = $this->service->create($dto);

        return APIResponse::success(
            new FunctionalityResource($func),
            __('messages.functionality.created'),
            201
        );
    }

    public function update(UpdateFunctionalityRequest $request, Functionality $functionality)
    {
        $dto = UpdateFunctionalityDTO::fromArray($request->validated());
        $func = $this->service->update($functionality, $dto);

        return APIResponse::success(
            new FunctionalityResource($func),
            __('messages.functionality.updated')
        );
    }

    public function destroy(Functionality $functionality)
    {
        $this->service->delete($functionality);

        return APIResponse::success(
            null,
            __('messages.functionality.deleted'),
            204
        );
    }
}
