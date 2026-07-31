<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'alpha_dash', 'max:255', 'unique:tenants,slug'],
            'plan_uuid' => ['nullable', 'uuid', 'exists:plans,uuid'],
            'is_active' => ['sometimes', 'boolean'],
            'logo' => ['nullable', 'image', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('messages.tenant.name_required'),
            'slug.required' => __('messages.tenant.slug_required'),
            'slug.unique' => __('messages.tenant.slug_exists'),
        ];
    }
}
