<?php

namespace App\Http\Resources\Auth;

use App\Support\MediaUrl;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => MediaUrl::resolvePublic(
                $this->avatar_path,
                $this->avatar_data,
                '/api/v1/users/' . $this->uuid . '/avatar',
                $this->avatar_updated_at,
                'avatar'
            ),
            'pending_email' => $this->pending_email,
        ];
    }
}
