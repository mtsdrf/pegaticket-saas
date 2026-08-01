<?php

namespace App\Http\Requests\TenantSettings;

use Illuminate\Foundation\Http\FormRequest;
class UpdateTenantSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'send_tracking_link_whatsapp' => ['required', 'boolean'],
            'minimum_order_value' => ['nullable', 'numeric', 'min:0'],
            'estimated_preparation_minutes' => ['nullable', 'integer', 'min:1'],
            'accepted_payment_methods' => ['nullable', 'array'],
            'accepted_payment_methods.*' => ['string', 'in:cash,pix,credit_card,debit_card'],
            'payment_receiving_method' => ['nullable', 'string', 'in:manual,pix_key'],
            'payment_pix_key' => ['nullable', 'string', 'max:140'],
            'allow_store_pickup' => ['nullable', 'boolean'],
            'storefront_enabled' => ['nullable', 'boolean'],
            'catalog_layout' => ['nullable', 'string', 'in:grid,list'],
        ];
    }
}
