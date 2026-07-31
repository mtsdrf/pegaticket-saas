<?php

namespace App\Http\Requests\Privacy;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePrivacyRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:open,in_progress,completed,rejected'],
            'resolution_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
