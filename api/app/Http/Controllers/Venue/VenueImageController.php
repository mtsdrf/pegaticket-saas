<?php

namespace App\Http\Controllers\Venue;

use App\Http\Controllers\Controller;
use App\Models\Venue\Venue;
use App\Services\Media\MediaStorageService;
use Illuminate\Http\Response;

/**
 * Ver App\Http\Controllers\Event\EventImageController — mesmo padrão.
 */
class VenueImageController extends Controller
{
    public function __construct(
        private MediaStorageService $mediaStorage
    ) {
    }

    public function show(Venue $venue): Response
    {
        return $this->mediaStorage->publicMediaResponse(
            $venue->background_image_path,
            $venue->background_image_data,
            $venue->background_image_mime,
            'venue'
        );
    }
}
