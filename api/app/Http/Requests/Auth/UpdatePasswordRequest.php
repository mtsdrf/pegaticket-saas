<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ];
    }

    /**
     * Senha atual errada é regra de negócio (depende do hash já salvo do
     * usuário autenticado), não formato de campo — checada aqui, mesmo
     * padrão de withValidator/after() já usado em
     * SyncProductCategoryPricesRequest, pra dar 422 VALIDATION_ERROR
     * consistente em vez de uma exceção dedicada só pra isso.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->filled('current_password') && !Hash::check($this->input('current_password'), $this->user()->password)) {
                $validator->errors()->add(
                    'current_password',
                    __('messages.profile.invalid_current_password')
                );
            }
        });
    }

    public function messages(): array
    {
        return __('messages.validation.messages');
    }
}
