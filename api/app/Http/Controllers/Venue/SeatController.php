<?php

namespace App\Http\Controllers\Venue;

use App\DTOs\Venue\CreateSeatDTO;
use App\DTOs\Venue\UpdateSeatDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Venue\ListSeatRequest;
use App\Http\Requests\Venue\StoreSeatRequest;
use App\Http\Requests\Venue\UpdateSeatRequest;
use App\Http\Resources\Venue\SeatResource;
use App\Models\Venue\Seat;
use App\Models\Venue\Venue;
use App\Services\APIResponse;
use App\Services\Venue\SeatService;

class SeatController extends Controller
{
    public function __construct(
        private SeatService $service
    ) {
    }

    public function index(ListSeatRequest $request, Venue $venue)
    {
        $validated = $request->validated();
        $filters = collect($validated)->only(['kind', 'sector_name'])->all();

        $list = $this->service->paginate(
            $venue,
            $filters,
            (int) ($validated['per_page'] ?? 50)
        );

        return APIResponse::success(
            SeatResource::collection($list),
            __('messages.seat.list'),
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

    public function show(Venue $venue, Seat $seat)
    {
        $seat = $this->service->find($seat);

        return APIResponse::success(
            new SeatResource($seat),
            __('messages.seat.show')
        );
    }

    public function store(StoreSeatRequest $request, Venue $venue)
    {
        $dto = CreateSeatDTO::fromArray(
            $request->validated(),
            app('tenant_id'),
            $venue->uuid
        );

        $seat = $this->service->create($dto);

        return APIResponse::success(
            new SeatResource($seat),
            __('messages.seat.created'),
            201
        );
    }

    public function update(UpdateSeatRequest $request, Venue $venue, Seat $seat)
    {
        $dto = UpdateSeatDTO::fromArray($request->validated());
        $seat = $this->service->update($seat, $dto);

        return APIResponse::success(
            new SeatResource($seat),
            __('messages.seat.updated')
        );
    }

    public function destroy(Venue $venue, Seat $seat)
    {
        $this->service->delete($seat);

        return APIResponse::success(
            null,
            __('messages.seat.deleted'),
            204
        );
    }
}
