<?php

namespace App\Http\Resources\Tenant;

use App\Support\MediaUrl;
use Illuminate\Http\Resources\Json\JsonResource;

class MyTenantResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'tenant_uuid' => $this->tenant_uuid,
            'tenant_name' => $this->tenant_name,
            'logo_url' => MediaUrl::resolvePublic(
                $this->logo_path,
                $this->logo_mime,
                '/api/v1/tenants/' . $this->tenant_uuid . '/logo',
                $this->logo_updated_at,
                'tenant'
            ),
            'role' => $this->role,
            'plan_slug' => $this->plan_slug,
            'plan_name' => $this->plan_name,
            'send_tracking_link_whatsapp' => (bool) $this->send_tracking_link_whatsapp,
        ];
    }
}
