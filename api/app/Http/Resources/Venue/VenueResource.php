<?php

namespace App\Http\Resources\Venue;

use App\Support\MediaUrl;
use Illuminate\Http\Resources\Json\JsonResource;

class VenueResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'background_image_url' => MediaUrl::resolvePublic(
                $this->background_image_path,
                $this->background_image_data ?? $this->background_image_mime,
                '/api/v1/venues/' . $this->uuid . '/image',
                null,
                'venue'
            ),
            'width' => $this->width,
            'height' => $this->height,
            'is_active' => $this->is_active,
            'published_map_version' => $this->whenLoaded('publishedMapVersion', fn() => $this->publishedMapVersion ? [
                'uuid' => $this->publishedMapVersion->uuid,
                'version_number' => $this->publishedMapVersion->version_number,
                'is_published' => $this->publishedMapVersion->is_published,
                'created_at' => $this->publishedMapVersion->created_at,
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}
