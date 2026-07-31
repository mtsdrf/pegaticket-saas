<?php

namespace App\Http\Requests\Stock;

use App\Http\Requests\Stock\Concerns\ValidatesStockMovementBase;
use Illuminate\Foundation\Http\FormRequest;

class StoreStockMovementAdjustmentRequest extends FormRequest
{
    use ValidatesStockMovementBase;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge($this->baseMovementRules(), [
            'direction' => ['required', 'string', 'in:increase,decrease'],
        ]);
    }

    public function messages(): array
    {
        return $this->baseMovementMessages();
    }
}
