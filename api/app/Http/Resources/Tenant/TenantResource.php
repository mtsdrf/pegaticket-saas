<?php

namespace App\Http\Resources\Tenant;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'plan_uuid' => $this->plan?->uuid ?? $this->plan_uuid ?? null,
            'plan_name' => $this->plan?->name ?? $this->plan_name ?? null,
            'logo_url' => MediaUrl::resolvePublic(
                $this->logo_path,
                $this->logo_data ?? $this->logo_mime,
                '/api/v1/tenants/' . $this->uuid . '/logo',
                $this->logo_updated_at,
                'tenant'
            ),
            'is_active' => $this->is_active,
            'trial_ends_at' => optional($this->trial_ends_at)?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
