<?php

namespace App\Http\Requests\Stock;

use App\Http\Requests\Stock\Concerns\ValidatesStockMovementBase;
use Illuminate\Foundation\Http\FormRequest;

class StoreStockMovementReturnRequest extends FormRequest
{
    use ValidatesStockMovementBase;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->baseMovementRules();
    }

    public function messages(): array
    {
        return $this->baseMovementMessages();
    }
}
