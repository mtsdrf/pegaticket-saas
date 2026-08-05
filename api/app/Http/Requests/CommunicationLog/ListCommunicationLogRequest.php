<?php

namespace App\Http\Requests\CommunicationLog;

use Illuminate\Foundation\Http\FormRequest;

class ListCommunicationLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'string', 'in:type,status,recipient_email,created_at'],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],

            'type' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'in:sent,failed'],
            'recipient_email' => ['nullable', 'string', 'max:255'],
        ];
    }
}
