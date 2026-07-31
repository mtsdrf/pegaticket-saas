<?php

namespace App\Http\Requests\Stock;

use App\Http\Requests\Stock\Concerns\ValidatesStockMovementBase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockMovementTransferRequest extends FormRequest
{
    use ValidatesStockMovementBase;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge($this->baseMovementRules(), [
            'destination_location_uuid' => [
                'required',
                'uuid',
                'different:location_uuid',
                Rule::exists('stock_locations', 'uuid')->where(function ($query) {
                    $query->where('tenant_id', app('tenant_id'))
                        ->whereNull('deleted_at');
                }),
            ],
        ]);
    }

    public function messages(): array
    {
        return array_merge($this->baseMovementMessages(), [
            'destination_location_uuid.exists' => __('messages.stock.invalid_location'),
            'destination_location_uuid.different' => __('messages.stock.transfer_same_location'),
        ]);
    }
}
