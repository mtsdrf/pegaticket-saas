<?php

namespace App\Http\Resources\Group;

use Illuminate\Http\Resources\Json\JsonResource;

class GroupResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'is_active' => $this->is_active,
            'users' => $this->whenLoaded('users', function () {
                return $this->users->map(fn($u) => [
                    'uuid' => $u->uuid,
                    'name' => $u->name,
                    'email' => $u->email,
                ]);
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}