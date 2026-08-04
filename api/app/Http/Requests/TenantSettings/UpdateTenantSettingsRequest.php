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
            'payment_receiving_method' => ['nullable', 'string', 'in:manual,pix_key,pagbank_token'],
            'payment_pix_key' => ['nullable', 'string', 'max:140'],
            'pagbank_integration_mode' => ['nullable', 'string', 'in:disabled,manual_token,connect'],
            'pagbank_environment' => ['nullable', 'string', 'in:sandbox,production'],
            'pagbank_access_token' => ['nullable', 'string', 'max:4096'],
            'pagbank_receiver_account_id' => ['nullable', 'string', 'max:80'],
            'storefront_enabled' => ['nullable', 'boolean'],
            'catalog_layout' => ['nullable', 'string', 'in:grid,list'],
            'hold_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:120'],
            'affiliate_default_commission_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            // Pixels de marketing (Fase 6, fatia 3) — só formato aqui;
            // provedor decide o formato do ID, não validamos regex
            // específica pra não travar tenant com pixel de outro país/conta.
            'meta_pixel_id' => ['nullable', 'string', 'max:40'],
            'google_analytics_id' => ['nullable', 'string', 'max:40'],
        ];
    }
}
