<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Resources\Json\JsonResource;

class TenantUserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'tenant_uuid' => $this->tenant->uuid,
            'user_uuid' => $this->user->uuid,
            'role_uuid' => $this->role->uuid,
            'tenant_name' => $this->tenant->name,
            'user_name' => $this->user->name,
            'user_email' => $this->user->email,
            'role_name' => $this->role->name,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
