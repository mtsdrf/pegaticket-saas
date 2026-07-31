<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeliveryFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bairro_uuid' => ['required', 'uuid', 'exists:bairros,uuid'],
            'fee' => ['required', 'numeric', 'min:0'],
        ];
    }
}
