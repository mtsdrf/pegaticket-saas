<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Público (bilheteria online) — mesmo espírito de StorefrontValidateCouponRequest:
 * sem `authorize()` ligado a usuário/staff, o "escopo" é o slug do tenant na
 * rota. `event_type` restrito a uma lista fechada para não virar um sumidouro
 * de dado arbitrário do cliente.
 */
class StoreCartEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_id' => ['required', 'string', 'max:100'],
            'event_type' => ['required', 'string', 'in:cart_viewed,cart_updated,cart_abandoned,cart_recovered'],
            'items' => ['nullable', 'array'],
            'items.*.product_uuid' => ['nullable', 'string'],
            'items.*.name' => ['nullable', 'string'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }

}
