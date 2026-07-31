<?php

namespace App\Http\Controllers\Balcao;

use App\DTOs\Balcao\CreateStationDTO;
use App\DTOs\Balcao\UpdateStationDTO;
use App\Exceptions\DuplicateNameException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Balcao\StoreStationRequest;
use App\Http\Requests\Balcao\UpdateStationRequest;
use App\Http\Resources\Balcao\ComandaItemResource;
use App\Http\Resources\Balcao\StationResource;
use App\Models\Balcao\Station;
use App\Services\APIResponse;
use App\Services\Balcao\ComandaService;
use App\Services\Balcao\StationService;

class StationController extends Controller
{
    public function __construct(
        private StationService $service,
        private ComandaService $comandaService,
    ) {
    }

    public function index()
    {
        $stations = $this->service->list(app('tenant_id'));

        return APIResponse::success(
            StationResource::collection($stations),
            __('messages.station.list')
        );
    }

    public function store(StoreStationRequest $request)
    {
        $dto = CreateStationDTO::fromArray($request->validated(), app('tenant_id'));

        try {
            $station = $this->service->create($dto);
        } catch (DuplicateNameException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DUPLICATE_NAME');
        }

        return APIResponse::success(
            new StationResource($station),
            __('messages.station.created'),
            201
        );
    }

    public function update(UpdateStationRequest $request, Station $station)
    {
        $dto = UpdateStationDTO::fromArray($request->validated());

        try {
            $station = $this->service->update($station, $dto);
        } catch (DuplicateNameException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DUPLICATE_NAME');
        }

        return APIResponse::success(
            new StationResource($station),
            __('messages.station.updated')
        );
    }

    public function destroy(Station $station)
    {
        $this->service->delete($station);

        return APIResponse::success(null, __('messages.station.deleted'), 204);
    }

    public function tickets(Station $station)
    {
        $tickets = $this->comandaService->stationTickets(app('tenant_id'), $station->uuid);

        return APIResponse::success(
            ComandaItemResource::collection($tickets),
            __('messages.station.tickets')
        );
    }
}
