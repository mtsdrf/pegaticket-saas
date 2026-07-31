<?php

namespace App\Http\Controllers\Route;

use App\Http\Controllers\Controller;
use App\Http\Requests\Route\ListRouteCandidatesRequest;
use App\Services\APIResponse;
use App\Services\Route\RouteCandidateService;

class RouteCandidateController extends Controller
{
    public function __construct(
        private RouteCandidateService $service
    ) {
    }

    public function index(ListRouteCandidatesRequest $request)
    {
        $validated = $request->validated();

        $data = $this->service->candidates(
            app('tenant_id'),
            $validated['type'],
            $validated['date']
        );

        return APIResponse::success($data, __('messages.route.candidates_list'));
    }
}
