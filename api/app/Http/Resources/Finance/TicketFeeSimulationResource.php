<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wrap simples: o Service já monta o array final no formato de saída
 * (dado 100% computado, sem model por trás) — segue o mesmo padrão
 * genérico de resultado agregado já usado por AnalyticsController, só que
 * com um Resource dedicado para manter o fluxo
 * Request→Controller→Service→Resource do restante do projeto.
 */
class TicketFeeSimulationResource extends JsonResource
{
    public function toArray($request): array
    {
        return $this->resource;
    }
}
