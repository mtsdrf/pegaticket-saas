<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class AdminReceivableIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_uuid' => ['nullable', 'uuid'],
            'event_uuid' => ['nullable', 'uuid'],
            'status' => ['nullable', 'string', 'in:scheduled,awaiting_release,release_requested,released'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
