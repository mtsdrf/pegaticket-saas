<?php

namespace App\Http\Controllers\Venue;

use App\DTOs\Venue\CreateVenueDTO;
use App\DTOs\Venue\UpdateVenueDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Venue\ListVenueRequest;
use App\Http\Requests\Venue\StoreVenueRequest;
use App\Http\Requests\Venue\UpdateVenueRequest;
use App\Http\Resources\Venue\VenueMapVersionResource;
use App\Http\Resources\Venue\VenueResource;
use App\Models\Venue\Venue;
use App\Services\APIResponse;
use App\Services\Venue\VenueService;

class VenueController extends Controller
{
    public function __construct(
        private VenueService $service
    ) {
    }

    public function index(ListVenueRequest $request)
    {
        $tenantId = app('tenant_id');
        $validated = $request->validated();
        $filters = collect($validated)->only(['name', 'is_active'])->all();

        $list = $this->service->paginate(
            $tenantId,
            $filters,
            (int) ($validated['per_page'] ?? 15)
        );

        return APIResponse::success(
            VenueResource::collection($list),
            __('messages.venue.list'),
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

    public function show(Venue $venue)
    {
        $venue = $this->service->find($venue);

        return APIResponse::success(
            new VenueResource($venue),
            __('messages.venue.show')
        );
    }

    public function store(StoreVenueRequest $request)
    {
        $dto = CreateVenueDTO::fromArray(
            $request->validated(),
            app('tenant_id')
        );

        $venue = $this->service->create($dto);

        return APIResponse::success(
            new VenueResource($venue),
            __('messages.venue.created'),
            201
        );
    }

    public function update(UpdateVenueRequest $request, Venue $venue)
    {
        $dto = UpdateVenueDTO::fromArray($request->validated());
        $venue = $this->service->update($venue, $dto);

        return APIResponse::success(
            new VenueResource($venue),
            __('messages.venue.updated')
        );
    }

    public function destroy(Venue $venue)
    {
        $this->service->delete($venue);

        return APIResponse::success(
            null,
            __('messages.venue.deleted'),
            204
        );
    }

    public function publish(Venue $venue)
    {
        $publishedVersion = $this->service->publish($venue);

        return APIResponse::success(
            new VenueMapVersionResource($publishedVersion),
            __('messages.venue.published')
        );
    }
}
