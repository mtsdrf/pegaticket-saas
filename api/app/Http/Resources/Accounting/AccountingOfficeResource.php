<?php

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Resources\Json\JsonResource;

class AccountingOfficeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'cnpj' => $this->cnpj,
            'company_name' => $this->company_name,
            'responsible_name' => $this->responsible_name,
            'email' => $this->email,
            'totp_enabled' => $this->isTotpEnabled(),
            'totp_enabled_at' => optional($this->totp_enabled_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
