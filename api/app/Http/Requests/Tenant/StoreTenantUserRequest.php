<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreTenantUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_uuid' => ['nullable', 'uuid', 'required_without:user'],
            'tenant_uuid' => ['required', 'uuid'],
            'role_uuid' => ['required', 'uuid'],
            'is_active' => ['boolean'],
            'user' => ['nullable', 'array', 'required_without:user_uuid'],
            'user.name' => ['required_with:user', 'string', 'max:255'],
            'user.email' => ['required_with:user', 'email', 'max:255', 'unique:users,email'],
            'user.password' => ['required_with:user', 'string', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return __('messages.validation.messages');
    }

    public function attributes(): array
    {
        return __('messages.validation.attributes');
    }
}
