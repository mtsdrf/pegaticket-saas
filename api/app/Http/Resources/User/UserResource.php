<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'groups' => $this->whenLoaded(
                'groups',
                fn() =>
                $this->groups->map(fn($g) => ['uuid' => $g->uuid, 'name' => $g->name, 'slug' => $g->slug])
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}