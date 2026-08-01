<?php

namespace App\Http\Resources\Event;

use App\Support\MediaUrl;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description_short' => $this->description_short,
            'description_full' => $this->description_full,
            'cover_image_url' => MediaUrl::resolvePublic(
                $this->cover_image_path,
                $this->cover_image_data ?? $this->cover_image_mime,
                '/api/v1/events/' . $this->uuid . '/image',
                $this->cover_image_updated_at,
                'event'
            ),
            'type' => $this->type,
            'location_name' => $this->location_name,
            'location_address' => $this->location_address,
            'location_lat' => $this->location_lat !== null ? (float) $this->location_lat : null,
            'location_lng' => $this->location_lng !== null ? (float) $this->location_lng : null,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'visibility' => $this->visibility,
            'status' => $this->status,
            'category' => $this->whenLoaded('category', fn() => $this->category ? [
                'uuid' => $this->category->uuid,
                'name' => $this->category->name,
            ] : null),
            'venue' => $this->whenLoaded('venueMapVersion', function () {
                if (!$this->venueMapVersion || !$this->venueMapVersion->relationLoaded('venue') || !$this->venueMapVersion->venue) {
                    return null;
                }

                return [
                    'uuid' => $this->venueMapVersion->venue->uuid,
                    'name' => $this->venueMapVersion->venue->name,
                    'map_version_uuid' => $this->venueMapVersion->uuid,
                    'map_version_number' => $this->venueMapVersion->version_number,
                ];
            }),
            'ticket_types' => $this->whenLoaded('ticketTypes', fn() => \App\Http\Resources\Event\TicketTypeResource::collection($this->ticketTypes)),
            'event_products' => $this->whenLoaded('eventProducts', fn() => \App\Http\Resources\Event\EventProductResource::collection($this->eventProducts)),
            // Atributo dinâmico (setAttribute), setado por
            // StorefrontCatalogService::paginateEvents() (customer.jwt.optional)
            // e EventFavoriteService::list() — só aparece quando o chamador
            // populou explicitamente, nunca como false por omissão.
            'is_favorited' => $this->when($this->resource->offsetExists('is_favorited'), fn() => $this->is_favorited),
            'created_at' => $this->created_at,
        ];
    }
}
