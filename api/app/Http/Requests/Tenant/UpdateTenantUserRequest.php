<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role_uuid' => ['sometimes', 'uuid'],
            'is_active' => ['sometimes', 'boolean']
        ];
    }
}