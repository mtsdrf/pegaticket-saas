<?php

namespace App\Http\Requests\Group;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncGroupPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permissions' => ['required', 'array'],
            'permissions.*.functionality_slug' => ['required', 'string', 'max:100'],
            'permissions.*.actions' => ['required', 'array'],
            'permissions.*.actions.*' => ['required', Rule::in(['read', 'create', 'update', 'delete'])],
        ];
    }

    public function messages(): array
    {
        return __('messages.validation.messages');
    }
}