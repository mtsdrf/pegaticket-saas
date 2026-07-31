<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteTenantUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role_uuid' => [
                'required',
                'uuid',
                Rule::exists('tenant_roles', 'uuid')->where(
                    fn($query) => $query->where('tenant_id', app('tenant_id'))->whereNull('deleted_at')
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return __('messages.validation.messages');
    }
}
