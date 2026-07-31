<?php

namespace App\Http\Controllers\Group;

use App\DTOs\Group\CreateGroupDTO;
use App\DTOs\Group\UpdateGroupDTO;
use App\DTOs\Group\SyncGroupUsersDTO;
use App\DTOs\Group\SyncGroupPermissionsDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Group\StoreGroupRequest;
use App\Http\Requests\Group\UpdateGroupRequest;
use App\Http\Requests\Group\SyncGroupUsersRequest;
use App\Http\Requests\Group\SyncGroupPermissionsRequest;
use App\Http\Resources\Group\GroupResource;
use App\Models\Group\Group;
use App\Services\APIResponse;
use App\Services\Group\GroupService;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function __construct(private GroupService $service)
    {
    }

    public function index(Request $request)
    {
        $groups = $this->service->paginate(
            (int) $request->get('per_page', 15),
            $request->only(['name', 'slug', 'is_active']),
            $request->get('sort_by'),
            (string) $request->get('sort_dir', 'asc')
        );

        return APIResponse::success(
            GroupResource::collection($groups),
            __('messages.group.list'),
            200,
            [
                'pagination' => [
                    'current_page' => $groups->currentPage(),
                    'per_page'     => $groups->perPage(),
                    'total'        => $groups->total(),
                    'last_page'    => $groups->lastPage(),
                ],
            ]
        );
    }

    public function store(StoreGroupRequest $request)
    {
        $dto = CreateGroupDTO::fromArray($request->validated());
        $group = $this->service->create($dto);

        return APIResponse::success(
            new GroupResource($group),
            __('messages.group.created'),
            201
        );
    }

    public function show(Group $group)
    {
        $group->load('users');

        return APIResponse::success(
            new GroupResource($group),
            __('messages.group.show'),
            200
        );
    }

    public function update(UpdateGroupRequest $request, Group $group)
    {
        $dto = UpdateGroupDTO::fromArray($request->validated());
        $group = $this->service->update($group, $dto);

        return APIResponse::success(
            new GroupResource($group),
            __('messages.group.updated'),
            200
        );
    }

    public function destroy(Group $group)
    {
        $this->service->delete($group);

        return APIResponse::success(
            null,
            __('messages.group.deleted'),
            204
        );
    }

    public function syncUsers(SyncGroupUsersRequest $request, Group $group)
    {
        $dto = SyncGroupUsersDTO::fromArray($request->validated());
        $group = $this->service->syncUsersSoft($group, $dto);

        return APIResponse::success(
            new GroupResource($group),
            __('messages.group.users_synced'),
            200
        );
    }

    public function syncPermissions(SyncGroupPermissionsRequest $request, Group $group)
    {
        $dto = SyncGroupPermissionsDTO::fromArray($request->validated());
        $this->service->syncPermissionsSoft($group, $dto);

        return APIResponse::success(
            null,
            __('messages.group.permissions_synced'),
            200
        );
    }
}
