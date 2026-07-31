<?php

namespace App\Http\Controllers\Balcao;

use App\Exceptions\Balcao\TableReservationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Balcao\CancelTableReservationRequest;
use App\Http\Requests\Balcao\SeatTableReservationRequest;
use App\Http\Requests\Balcao\StoreTableReservationRequest;
use App\Http\Resources\Balcao\TableReservationResource;
use App\Http\Resources\Balcao\TableResource;
use App\Services\APIResponse;
use App\Services\Balcao\TableReservationService;
use Illuminate\Http\Request;

class TableReservationController extends Controller
{
    public function __construct(
        private TableReservationService $service,
    ) {
    }

    public function index(Request $request)
    {
        return APIResponse::success(
            TableReservationResource::collection(
                $this->service->list(app('tenant_id'), $request->string('date')->toString() ?: null)
            ),
            __('messages.table_reservation.list')
        );
    }

    public function availability(Request $request)
    {
        $request->validate([
            'scheduled_for' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:30', 'max:480'],
            'party_size' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $tables = $this->service->availableTables(
            app('tenant_id'),
            (string) $request->input('scheduled_for'),
            (int) $request->input('duration_minutes', 120),
            (int) $request->input('party_size')
        );

        return APIResponse::success(
            TableResource::collection($tables),
            __('messages.table_reservation.availability')
        );
    }

    public function store(StoreTableReservationRequest $request)
    {
        try {
            $reservation = $this->service->create(app('tenant_id'), $request->validated());
        } catch (TableReservationException $e) {
            return APIResponse::error($e->getMessage(), 422, 'TABLE_RESERVATION_ERROR');
        }

        return APIResponse::success(
            new TableReservationResource($reservation),
            __('messages.table_reservation.created'),
            201
        );
    }

    public function seat(string $uuid, SeatTableReservationRequest $request)
    {
        try {
            $reservation = $this->service->seat(app('tenant_id'), $uuid, $request->validated());
        } catch (TableReservationException $e) {
            return APIResponse::error($e->getMessage(), 422, 'TABLE_RESERVATION_ERROR');
        }

        return APIResponse::success(
            new TableReservationResource($reservation),
            __('messages.table_reservation.seated')
        );
    }

    public function cancel(string $uuid, CancelTableReservationRequest $request)
    {
        try {
            $reservation = $this->service->cancel(app('tenant_id'), $uuid, (string) $request->validated('reason'));
        } catch (TableReservationException $e) {
            return APIResponse::error($e->getMessage(), 422, 'TABLE_RESERVATION_ERROR');
        }

        return APIResponse::success(
            new TableReservationResource($reservation),
            __('messages.table_reservation.cancelled')
        );
    }

    public function noShow(string $uuid)
    {
        try {
            $reservation = $this->service->markNoShow(app('tenant_id'), $uuid);
        } catch (TableReservationException $e) {
            return APIResponse::error($e->getMessage(), 422, 'TABLE_RESERVATION_ERROR');
        }

        return APIResponse::success(
            new TableReservationResource($reservation),
            __('messages.table_reservation.no_show')
        );
    }
}
