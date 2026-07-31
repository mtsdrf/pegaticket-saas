<?php

namespace App\Http\Requests\Privacy;

use Illuminate\Foundation\Http\FormRequest;

class CreatePrivacyRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'requester_name' => ['required', 'string', 'max:255'],
            'requester_email' => ['nullable', 'email', 'max:255'],
            'requester_role' => ['required', 'string', 'in:empresa,usuario_interno,titular_final,outro'],
            'request_type' => ['required', 'string', 'in:acesso,correcao,exclusao,anonimizacao,oposicao,outro'],
            'channel' => ['nullable', 'string', 'in:email,whatsapp,telefone,atendimento_interno,outro'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
        ];
    }
}
