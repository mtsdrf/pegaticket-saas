<?php

namespace App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use App\Models\Event\Event;
use App\Services\Media\MediaStorageService;
use Illuminate\Http\Response;

/**
 * Ver App\Http\Controllers\Event\TicketTypeImageController — mesmo padrão.
 */
class EventImageController extends Controller
{
    public function __construct(
        private MediaStorageService $mediaStorage
    ) {
    }

    public function show(Event $event): Response
    {
        return $this->mediaStorage->publicMediaResponse(
            $event->cover_image_path,
            $event->cover_image_data,
            $event->cover_image_mime,
            'event'
        );
    }
}
