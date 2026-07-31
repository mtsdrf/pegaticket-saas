<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductCategoryPriceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'client_category_uuid' => $this->clientCategory->uuid,
            'client_category_name' => $this->clientCategory->name,
            'price' => $this->price,
        ];
    }
}
