<?php

namespace App\Http\Controllers\Portal;

use App\DTOs\Portal\CreatePortalLinkDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\CreatePortalLinkRequest;
use App\Http\Resources\Portal\PortalLinkResource;
use App\Services\APIResponse;
use App\Services\Portal\PortalLinkService;

class PortalLinkController extends Controller
{
    public function __construct(
        private PortalLinkService $service
    ) {
    }

    public function store(CreatePortalLinkRequest $request)
    {
        $dto = CreatePortalLinkDTO::fromArray($request->validated());

        $link = $this->service->link(portal_customer(), $dto);

        return APIResponse::success(
            new PortalLinkResource($link),
            __('messages.portal.link_confirmed')
        );
    }
}
