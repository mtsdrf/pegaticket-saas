<?php

namespace App\Http\Resources\Report;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * CMV real (roadmap A3.13). `cmv` já vem calculado (média ponderada) ou
 * null do ReportService::cmv() — aqui só formata e deriva `margin_percent`/
 * `has_cost_data`, que diferencia "produto sem histórico de custo" (null)
 * de "margem zero" (0.0, custo conhecido igual ao preço de venda).
 */
class CmvResource extends JsonResource
{
    public function toArray($request): array
    {
        $cmv = $this->cmv !== null ? round((float) $this->cmv, 2) : null;
        $salePrice = (float) $this->sale_price;

        $marginPercent = ($cmv !== null && $salePrice > 0)
            ? round((($salePrice - $cmv) / $salePrice) * 100, 2)
            : null;

        return [
            'ticket_type_uuid' => $this->ticket_type_uuid,
            'ticket_type_name' => $this->ticket_type_name,
            'sale_price' => number_format($salePrice, 2, '.', ''),
            'cmv' => $cmv !== null ? number_format($cmv, 2, '.', '') : null,
            'margin_percent' => $marginPercent,
            'has_cost_data' => $cmv !== null,
        ];
    }
}
