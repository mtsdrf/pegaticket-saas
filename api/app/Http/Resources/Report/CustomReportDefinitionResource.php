<?php

namespace App\Http\Resources\Report;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomReportDefinitionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'data_source' => $this->data_source,
            'dimensions' => $this->dimensions,
            'metrics' => $this->metrics,
            'calculated_metrics' => $this->calculated_metrics ?? [],
            'filters' => $this->filters ?? [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
