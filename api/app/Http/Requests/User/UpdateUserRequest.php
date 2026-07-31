<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userUuid = $this->route('user')?->uuid;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user')?->id)
            ],
            'password' => ['sometimes', 'string', Password::defaults()],
            'is_active' => ['sometimes', 'boolean'],
            'group_uuids' => ['sometimes', 'array'],
            'group_uuids.*' => ['uuid'],
        ];
    }

    public function messages(): array
    {
        return __('messages.validation.messages');
    }
}
