<?php

namespace App\Http\Controllers\User;

use App\DTOs\User\CreateUserDTO;
use App\DTOs\User\UpdateUserDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\User\UserResource;
use App\Models\User\User;
use App\Services\APIResponse;
use App\Services\User\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private UserService $service)
    {
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 15);
        $users = $this->service->paginate(
            $perPage,
            $request->only(['name', 'email', 'is_active']),
            $request->get('sort_by'),
            (string) $request->get('sort_dir', 'asc')
        );

        return APIResponse::success(
            UserResource::collection($users),
            __('messages.user.list'),
            200,
            [
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'per_page'     => $users->perPage(),
                    'total'        => $users->total(),
                    'last_page'    => $users->lastPage(),
                ],
            ]
        );
    }

    public function store(StoreUserRequest $request)
    {
        $dto = CreateUserDTO::fromArray($request->validated());
        $user = $this->service->create($dto);

        return APIResponse::success(
            new UserResource($user),
            __('messages.user.created'),
            201
        );
    }

    public function show(User $user)
    {
        $this->service->ensureUserAccessible($user);
        $user->load('groups');

        return APIResponse::success(
            new UserResource($user),
            __('messages.user.show'),
            200
        );
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $this->service->ensureUserAccessible($user);
        $dto = UpdateUserDTO::fromArray($request->validated());
        $user = $this->service->update($user, $dto);

        return APIResponse::success(
            new UserResource($user),
            __('messages.user.updated'),
            200
        );
    }

    public function destroy(User $user)
    {
        $this->service->ensureUserAccessible($user);
        $this->service->delete($user);

        return APIResponse::success(null, __('messages.user.deleted'), 204);
    }
}
