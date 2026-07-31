<?php

namespace App\Http\Requests\Subscription;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'billing_period' => ['required', 'string', 'in:monthly,quarterly,yearly'],
            'accepted_terms' => ['required', 'accepted'],
            // Opcional: permite escolher qualquer plano ativo já na primeira
            // contratação (não fica preso ao plan_id padrão do cadastro do
            // tenant). Mesma regra de existência usada em
            // ChangeSubscriptionPlanRequest.
            'plan_id' => [
                'nullable',
                'string',
                Rule::exists('plans', 'uuid')->where(fn ($query) => $query
                    ->whereNull('deleted_at')
                    ->where('is_active', true)),
            ],
            // Nullable aqui: a obrigatoriedade real depende do preço do
            // plano contratado (planos gratuitos não exigem cartão), o que
            // só o Service sabe calcular — SubscriptionService::create()
            // barra explicitamente quando o plano é pago e o token não
            // veio.
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
