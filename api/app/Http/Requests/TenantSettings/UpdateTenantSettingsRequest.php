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
            'accepted_payment_methods' => ['nullable', 'array'],
            'accepted_payment_methods.*' => ['string', 'in:cash,pix,credit_card,debit_card'],
            'payment_receiving_method' => ['nullable', 'string', 'in:manual,pix_key'],
            'payment_pix_key' => ['nullable', 'string', 'max:140'],
            'storefront_enabled' => ['nullable', 'boolean'],
            'catalog_layout' => ['nullable', 'string', 'in:grid,list'],
            'hold_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:120'],
        ];
    }
}
