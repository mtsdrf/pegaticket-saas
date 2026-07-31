<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Resources\Event\EventResource;
use App\Services\APIResponse;
use App\Services\Storefront\EventFavoriteService;
use Illuminate\Http\Request;

class PortalFavoriteController extends Controller
{
    public function __construct(
        private EventFavoriteService $service
    ) {
    }

    public function toggle(string $eventUuid)
    {
        $result = $this->service->toggle(portal_customer()->id, $eventUuid);

        return APIResponse::success(
            $result,
            $result['favorited']
                ? __('messages.storefront.favorite_added')
                : __('messages.storefront.favorite_removed')
        );
    }

    public function index(Request $request)
    {
        $list = $this->service->list(portal_customer()->id, (int) $request->get('per_page', 15));

        return APIResponse::success(
            EventResource::collection($list),
            __('messages.storefront.favorites_listed'),
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
}
