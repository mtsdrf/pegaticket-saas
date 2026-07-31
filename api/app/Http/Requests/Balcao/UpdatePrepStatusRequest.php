<?php

namespace App\Http\Requests\Balcao;

use App\Models\Balcao\ComandaItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePrepStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // 'queued' não é destino válido (só estado inicial). 'sent_to_station'
        // é aceito aqui e roteado para o fluxo com baixa de estoque no
        // ComandaItemService; os demais são transições puras da máquina de
        // estados. A validade da TRANSIÇÃO em si (origem→destino) é checada
        // no Service (máquina de estados), não aqui.
        return [
            'prep_status' => [
                'required',
                Rule::in([
                    ComandaItem::STATUS_SENT_TO_STATION,
                    ComandaItem::STATUS_PREPARING,
                    ComandaItem::STATUS_READY,
                    ComandaItem::STATUS_DELIVERED_TO_TABLE,
                    ComandaItem::STATUS_CANCELLED,
                ]),
            ],
            'cancelled_reason' => ['required_if:prep_status,' . ComandaItem::STATUS_CANCELLED, 'nullable', 'string', 'max:1000'],
        ];
    }
}
