<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // route('uuid') é o {uuid} da rota de update — ausente no create.
        $ignoreUuid = $this->route('uuid');

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('coupons', 'code')
                    ->where(fn($query) => $query->where('tenant_id', app('tenant_id')))
                    ->whereNull('deleted_at')
                    ->ignore($ignoreUuid, 'uuid'),
            ],
            'type' => ['required', 'string', Rule::in(['percentage', 'fixed', 'free_shipping'])],
            // Achado de code review: `value` era sempre `nullable`, mesmo
            // pra percentage/fixed — um cupom criado sem valor passava em
            // todos os guards do checkout e sempre descontava 0 (silêncio
            // total), consumindo o limite de uso do cliente sem nunca dar
            // desconto nenhum. `required_if` fecha essa brecha; `max:100`
            // pra percentage evita um desconto >100% do subtotal.
            'value' => [
                'nullable',
                'numeric',
                'min:0.01',
                Rule::requiredIf(fn() => \in_array($this->input('type'), ['percentage', 'fixed'], true)),
                Rule::when($this->input('type') === 'percentage', ['max:100']),
            ],
            'minimum_order_value' => ['nullable', 'numeric', 'min:0'],
            'max_uses_total' => ['nullable', 'integer', 'min:1'],
            'max_uses_per_customer' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
            // null/ausente = sem restrição (comportamento atual, vale para
            // todos os meios). Mesma lista fixa de
            // UpdateTenantSettingsRequest::accepted_payment_methods.
            'allowed_payment_methods' => ['nullable', 'array'],
            'allowed_payment_methods.*' => ['string', 'in:cash,pix,credit_card,debit_card'],
        ];
    }
}
