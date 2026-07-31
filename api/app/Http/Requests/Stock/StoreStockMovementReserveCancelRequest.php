<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockMovementReserveCancelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'movement_uuid' => [
                'required',
                'uuid',
                Rule::exists('stock_movements', 'uuid')->where(function ($query) {
                    $query->where('tenant_id', app('tenant_id'))
                        ->where('type', 'reserve')
                        ->whereNull('deleted_at');
                }),
            ],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'movement_uuid.exists' => __('messages.stock.invalid_reserve_movement'),
        ];
    }
}
