<?php

namespace App\Http\Requests\Auth;

use App\Models\User\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RequestEmailChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user()->id),
            ],
            'current_password' => ['required', 'string'],
        ];
    }

    /**
     * Duas checagens de negócio que dependem de estado do usuário
     * autenticado, não representáveis só com regras declarativas:
     * - senha atual errada (hash contra o usuário logado);
     * - new_email já pendente de confirmação para OUTRO usuário
     *   (Rule::unique acima só cobre users.email, não pending_email).
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

            if ($this->filled('new_email')) {
                $emailPendingElsewhere = User::where('pending_email', $this->input('new_email'))
                    ->where('id', '!=', $this->user()->id)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($emailPendingElsewhere) {
                    $validator->errors()->add(
                        'new_email',
                        __('messages.profile.email_already_pending')
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return __('messages.validation.messages');
    }
}
