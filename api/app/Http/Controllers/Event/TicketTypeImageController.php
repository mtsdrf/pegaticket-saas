<?php

namespace App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use App\Models\Event\TicketType;
use App\Services\Media\MediaStorageService;
use Illuminate\Http\Response;

class TicketTypeImageController extends Controller
{
    public function __construct(
        private MediaStorageService $mediaStorage
    ) {
    }

    public function show(TicketType $ticketType): Response
    {
        return $this->mediaStorage->publicMediaResponse(
            $ticketType->image_path,
            $ticketType->image_data,
            $ticketType->image_mime,
            'ticket_type'
        );
    }
}
