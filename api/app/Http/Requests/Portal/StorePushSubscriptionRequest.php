<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Formato padrão de uma PushSubscription do navegador
 * ({endpoint, keys: {p256dh, auth}}), roadmap Delivery, Fase 4 — Web Push.
 */
class StorePushSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'string', 'url'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return __('messages.validation.messages');
    }
}
