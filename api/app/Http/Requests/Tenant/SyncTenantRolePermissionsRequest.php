<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class SyncTenantRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permissions' => ['required', 'array'],
            'permissions.*.functionality' => ['required', 'string'],
            'permissions.*.action' => ['required', 'string'],
            // Limite percentual de desconto por perfil (roadmap A1.5) —
            // nullable/opcional, só faz sentido na linha functionality=orders
            // (qualquer outra linha aceita o valor mas ele é ignorado, ver
            // PermissionService::resolveOrderDiscountLimitPercent).
            'permissions.*.discount_limit_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}