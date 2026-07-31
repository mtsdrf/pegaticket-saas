<?php

namespace App\Http\Controllers\Location;

use App\Http\Controllers\Controller;
use App\Http\Requests\Location\ReverseGeocodeRequest;
use App\Services\APIResponse;
use App\Services\Location\ReverseGeocodeService;

class LocationController extends Controller
{
    public function __construct(
        private ReverseGeocodeService $service
    ) {
    }

    public function reverseGeocode(ReverseGeocodeRequest $request)
    {
        $validated = $request->validated();

        $result = $this->service->reverse((float) $validated['lat'], (float) $validated['lng']);

        return APIResponse::success(
            $result,
            __('messages.location.reverse_geocoded')
        );
    }
}
