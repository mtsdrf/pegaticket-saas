<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * POST /loja/{slug}/cupons/validar — prévia pública de cupom, mesmo
 * espírito de StorefrontCheckoutRequest (escopa items.*.ticket_type_uuid/
 * event_product_uuid pelo tenant do slug — exatamente um por item).
 * authorize() sempre true: rota 100% pública, sem customer.jwt (identidade
 * do cliente ainda não existe nesta etapa do checkout).
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
            'items.*.ticket_type_uuid' => [
                'required_without:items.*.event_product_uuid',
                'nullable',
                'uuid',
                Rule::exists('ticket_types', 'uuid')->where(function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId)->whereNull('deleted_at');
                }),
            ],
            'items.*.event_product_uuid' => [
                'required_without:items.*.ticket_type_uuid',
                'nullable',
                'uuid',
                Rule::exists('event_products', 'uuid')->where(function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId)->whereNull('deleted_at');
                }),
            ],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.ticket_type_uuid.exists' => __('messages.sale.invalid_product'),
            'items.*.event_product_uuid.exists' => __('messages.sale.invalid_product'),
        ];
    }
}
