<?php

namespace App\Http\Resources\Ticket;

use App\DTOs\Ticket\CheckinResultDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shape de resposta de POST /tickets/checkin. `ticket`/`checkin` vêm null
 * quando result=nao_encontrado (nada foi localizado/persistido).
 */
class CheckinResultResource extends JsonResource
{
    public function __construct(private CheckinResultDTO $dto)
    {
        parent::__construct($dto);
    }

    public function toArray($request): array
    {
        return [
            'result' => $this->dto->result,
            'ticket' => $this->dto->ticket ? new TicketResource($this->dto->ticket) : null,
            'checkin' => $this->dto->checkin ? new TicketCheckinResource($this->dto->checkin) : null,
        ];
    }
}
