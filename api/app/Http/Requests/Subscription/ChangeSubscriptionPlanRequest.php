<?php

namespace App\Http\Requests\Subscription;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeSubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => [
                'required',
                'string',
                Rule::exists('plans', 'uuid')->where(fn ($query) => $query
                    ->whereNull('deleted_at')
                    ->where('is_active', true)),
            ],
            'billing_period' => ['required', 'string', 'in:monthly,quarterly,yearly'],
            // Nullable aqui: obrigatório de fato só quando o NOVO plano é
            // pago — SubscriptionService::changePlan() barra explicitamente
            // (só o Service conhece o preço do plano).
            'card_token' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return __('messages.validation.messages');
    }

    public function attributes(): array
    {
        return __('messages.validation.attributes');
    }
}
