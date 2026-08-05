<?php

namespace App\Http\Requests\Storefront;

use App\Models\Storefront\StorefrontFunnelEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Público — mesmo espírito de StoreCartEventRequest: sem `authorize()`
 * ligado a usuário/staff, escopo é o slug/eventSlug da rota. `step`
 * restrito à lista fechada de StorefrontFunnelEvent::STEPS.
 */
class StoreFunnelEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_id' => ['required', 'string', 'max:100'],
            'step' => ['required', 'string', Rule::in(StorefrontFunnelEvent::STEPS)],
        ];
    }
}
