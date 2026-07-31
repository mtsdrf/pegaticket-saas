<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductTypeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'priority' => $this->priority,
            'is_active' => $this->is_active,
            'product_category_uuid' => $this->productCategory->uuid,
            'product_category_name' => $this->productCategory->name,
            'created_at' => $this->created_at,
        ];
    }
}
