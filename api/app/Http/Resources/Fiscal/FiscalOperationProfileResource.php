<?php

namespace App\Http\Resources\Fiscal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FiscalOperationProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'operation_nature' => $this->operation_nature,
            'document_type' => $this->document_type,
            'default_cfop' => $this->default_cfop,
            'scope' => $this->scope,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
