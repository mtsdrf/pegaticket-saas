<?php

namespace App\Http\Requests\Stock;

use App\Http\Requests\Stock\Concerns\ValidatesStockMovementBase;
use Illuminate\Foundation\Http\FormRequest;

class StoreStockMovementEntryRequest extends FormRequest
{
    use ValidatesStockMovementBase;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge($this->baseMovementRules(), [
            // CMV real (roadmap A3.13): custo unitário opcional desta
            // entrada, usado no cálculo de custo médio ponderado do
            // produto. Só existe em `entry` — as demais movimentações
            // continuam sem custo.
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    public function messages(): array
    {
        return $this->baseMovementMessages();
    }
}
