<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class ReceivableIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_uuid' => ['nullable', 'uuid'],
            'settlement_uuid' => ['nullable', 'uuid'],
            'status' => ['nullable', 'string', 'in:scheduled,awaiting_release,release_requested,released'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
