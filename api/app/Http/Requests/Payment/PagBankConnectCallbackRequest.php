<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Callback público do PagBank Connect (?code=...&state=...) — validação
 * de formato apenas; a legitimidade do `state` é responsabilidade de
 * PagBankConnectService::handleCallback (comparação contra o registro
 * pendente, nunca aqui).
 */
class PagBankConnectCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:128'],
        ];
    }

    public function messages(): array
    {
        return __('messages.validation.messages');
    }
}
