<?php

namespace App\Http\Requests\Group;

use Illuminate\Foundation\Http\FormRequest;

class SyncGroupUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_uuids' => ['required', 'array'],
            'user_uuids.*' => ['uuid'],
        ];
    }

    public function messages(): array
    {
        return __('messages.validation.messages');
    }
}