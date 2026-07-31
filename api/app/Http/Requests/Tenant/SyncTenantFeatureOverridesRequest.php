<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class SyncTenantFeatureOverridesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'overrides' => ['required', 'array'],
            'overrides.*.functionality' => ['required', 'string', 'exists:functionalities,slug'],
            'overrides.*.is_enabled' => ['required', 'boolean'],
        ];
    }
}
