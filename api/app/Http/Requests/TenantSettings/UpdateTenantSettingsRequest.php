<?php

namespace App\Http\Requests\TenantSettings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTenantSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Regra de negócio nova (configurador de formas de entrega): a loja
     * nunca pode ficar sem NENHUMA forma de entrega ativa — rejeita
     * allow_delivery=false E allow_store_pickup=false na mesma requisição.
     * Só valida quando os dois campos vêm explicitamente no payload
     * (boolean(), não missing()) para não travar update parcial de outro
     * campo com o estado atual salvo no banco.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!$this->has('allow_delivery') || !$this->has('allow_store_pickup')) {
                return;
            }

            if (!$this->boolean('allow_delivery') && !$this->boolean('allow_store_pickup')) {
                $validator->errors()->add(
                    'allow_delivery',
                    __('messages.tenant_settings.no_fulfillment_method_enabled')
                );
            }
        });
    }

    public function rules(): array
    {
        return [
            'send_tracking_link_whatsapp' => ['required', 'boolean'],
            'block_order_without_stock' => ['required', 'boolean'],
            'minimum_order_value' => ['nullable', 'numeric', 'min:0'],
            'estimated_preparation_minutes' => ['nullable', 'integer', 'min:1'],
            'cashback_enabled' => ['nullable', 'boolean'],
            // required_if evita cashback "ligado" sem percentual, que
            // creditaria R$0 silenciosamente (auditoria 2026-07-24).
            'cashback_percentage' => ['nullable', 'required_if:cashback_enabled,true', 'numeric', 'min:0', 'max:100'],
            'cashback_max_per_order' => ['nullable', 'numeric', 'min:0'],
            'cashback_hold_days' => ['nullable', 'integer', 'min:0'],
            'cashback_expiration_days' => ['nullable', 'integer', 'min:1'],
            'cashback_redeem_max_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cashback_name' => ['nullable', 'string', 'max:60'],
            'accepted_payment_methods' => ['nullable', 'array'],
            'accepted_payment_methods.*' => ['string', 'in:cash,pix,credit_card,debit_card'],
            'payment_receiving_method' => ['nullable', 'string', 'in:manual,pix_key'],
            'payment_pix_key' => ['nullable', 'string', 'max:140'],
            'allow_store_pickup' => ['nullable', 'boolean'],
            'allow_delivery' => ['nullable', 'boolean'],
            'storefront_enabled' => ['nullable', 'boolean'],
            'catalog_layout' => ['nullable', 'string', 'in:grid,list'],
        ];
    }

    public function messages(): array
    {
        return [
            'cashback_percentage.required_if' => __('messages.tenant_settings.cashback_percentage_required'),
        ];
    }
}
