<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * POST /loja/{slug}/cupons/validar (roadmap Delivery, Fase 3) — prévia
 * pública de cupom, mesmo espírito de StorefrontCheckoutRequest (escopa
 * items.*.product_uuid pelo tenant do slug). authorize() sempre true: rota
 * 100% pública, sem customer.jwt (identidade do cliente ainda não existe
 * nesta etapa do checkout).
 */
class StorefrontValidateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = DB::table('tenants')
            ->where('slug', $this->route('slug'))
            ->whereNull('deleted_at')
            ->value('id');

        return [
            'code' => ['required', 'string', 'max:50'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_uuid' => [
                'required',
                'uuid',
                Rule::exists('products', 'uuid')->where(function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId)->whereNull('deleted_at');
                }),
            ],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.product_uuid.exists' => __('messages.order.invalid_product'),
        ];
    }
}
