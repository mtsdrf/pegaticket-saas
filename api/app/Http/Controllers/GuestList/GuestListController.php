<?php

namespace App\Http\Controllers\GuestList;

use App\DTOs\GuestList\AddGuestListEntryDTO;
use App\DTOs\GuestList\CreateGuestListDTO;
use App\DTOs\GuestList\UpdateGuestListDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuestList\AddGuestListEntryRequest;
use App\Http\Requests\GuestList\CreateGuestListRequest;
use App\Http\Requests\GuestList\ListGuestListRequest;
use App\Http\Requests\GuestList\UpdateGuestListRequest;
use App\Http\Resources\GuestList\GuestListEntryResource;
use App\Http\Resources\GuestList\GuestListResource;
use App\Models\GuestList\GuestList;
use App\Services\APIResponse;
use App\Services\GuestList\GuestListService;

class GuestListController extends Controller
{
    public function __construct(
        private GuestListService $service
    ) {}

    public function index(ListGuestListRequest $request)
    {
        $guestLists = $this->service->paginate(
            app('tenant_id'),
            $request->validated(),
            (int) ($request->validated()['per_page'] ?? 15)
        );

        return APIResponse::success(
            GuestListResource::collection($guestLists),
            __('messages.guest_list.list'),
            200,
            ['pagination' => [
                'current_page' => $guestLists->currentPage(),
                'per_page' => $guestLists->perPage(),
                'total' => $guestLists->total(),
                'last_page' => $guestLists->lastPage(),
            ]]
        );
    }

    public function store(CreateGuestListRequest $request)
    {
        $dto = CreateGuestListDTO::fromArray($request->validated());

        $guestList = $this->service->create(app('tenant_id'), $dto);

        return APIResponse::success(
            new GuestListResource($guestList),
            __('messages.guest_list.created'),
            201
        );
    }

    public function show(string $uuid)
    {
        $guestList = $this->service->find(app('tenant_id'), $uuid);

        return APIResponse::success(
            new GuestListResource($guestList),
            __('messages.guest_list.show')
        );
    }

    public function addEntry(AddGuestListEntryRequest $request, string $uuid)
    {
        $guestList = GuestList::where('tenant_id', app('tenant_id'))->where('uuid', $uuid)->firstOrFail();
        $dto = AddGuestListEntryDTO::fromArray($request->validated());

        $entry = $this->service->addEntry(app('tenant_id'), $guestList, $dto);

        return APIResponse::success(
            new GuestListEntryResource($entry),
            __('messages.guest_list.entry_added'),
            201
        );
    }

    public function update(UpdateGuestListRequest $request, string $uuid)
    {
        $guestList = $this->service->find(app('tenant_id'), $uuid);
        $dto = UpdateGuestListDTO::fromArray($request->validated());

        $guestList = $this->service->update(app('tenant_id'), $guestList, $dto);

        return APIResponse::success(
            new GuestListResource($guestList),
            __('messages.guest_list.updated')
        );
    }

    public function destroy(string $uuid)
    {
        $guestList = $this->service->find(app('tenant_id'), $uuid);
        $this->service->delete(app('tenant_id'), $guestList);

        return APIResponse::success(
            null,
            __('messages.guest_list.deleted'),
            204
        );
    }
}
