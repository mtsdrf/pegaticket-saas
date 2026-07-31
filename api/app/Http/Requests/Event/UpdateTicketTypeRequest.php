<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'max:5120'],
            'event_uuid' => [
                'sometimes',
                'uuid',
                Rule::exists('events', 'uuid')->where(function ($query) {
                    $query->where('tenant_id', app('tenant_id'))->whereNull('deleted_at');
                }),
            ],
            'quantity_available' => ['nullable', 'integer', 'min:0'],
            'min_per_order' => ['nullable', 'integer', 'min:1'],
            'max_per_order' => ['nullable', 'integer', 'min:1'],
            'sales_start_at' => ['nullable', 'date'],
            'sales_end_at' => ['nullable', 'date', 'after_or_equal:sales_start_at'],
            'status' => ['sometimes', 'string', Rule::in(['rascunho', 'ativo', 'pausado', 'esgotado', 'encerrado'])],
            'sku' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('ticket_types', 'sku')
                    ->where(function ($query) {
                        $query->where('tenant_id', app('tenant_id'))->whereNull('deleted_at');
                    })
                    ->ignore($this->route('ticketType')?->id),
            ],
            'barcode' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'event_uuid.exists' => __('messages.ticket_type.invalid_event'),
            'sku.unique' => __('messages.ticket_type.sku_exists'),
        ];
    }
}
