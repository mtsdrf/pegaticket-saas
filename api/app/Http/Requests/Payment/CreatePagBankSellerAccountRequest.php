<?php

namespace App\Http\Requests\Payment;

use App\Rules\ValidCpfCnpj;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Caminho Account/Cadastro do PagBank Connect (R2.2) — tenant sem conta
 * PagBank, PegaTicket cria uma SELLER nova (PF ou PJ). `person.tax_id` é
 * sempre CPF (titular/responsável), `company.tax_id` é sempre CNPJ e só é
 * exigido quando `person_type=pj`.
 *
 * `terms_accepted_at` é obrigatório e precisa ser um timestamp real do
 * momento em que o tenant aceitou os termos do PagBank na tela de
 * revisão (frontend, fora do escopo aqui) — nunca um boolean solto,
 * porque `tos_acceptance.date` do payload de Cadastro exige a data.
 */
class CreatePagBankSellerAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isPj = $this->input('person_type') === 'pj';

        return [
            'person_type' => ['required', 'string', 'in:pf,pj'],
            'email' => ['required', 'email', 'max:255'],
            'terms_accepted_at' => ['required', 'date', 'before_or_equal:now'],

            'person.name' => ['required', 'string', 'max:150'],
            'person.birth_date' => ['required', 'date', 'before:today'],
            'person.mother_name' => ['required', 'string', 'max:150'],
            'person.tax_id' => ['required', 'string', new ValidCpfCnpj('cpf')],
            'person.address.street' => ['required', 'string', 'max:160'],
            'person.address.number' => ['required', 'string', 'max:20'],
            'person.address.complement' => ['nullable', 'string', 'max:60'],
            'person.address.locality' => ['required', 'string', 'max:100'],
            'person.address.city' => ['required', 'string', 'max:100'],
            'person.address.region_code' => ['required', 'string', 'size:2'],
            'person.address.postal_code' => ['required', 'string', 'regex:/^\d{5}-?\d{3}$/'],
            'person.address.country' => ['nullable', 'string', 'size:3'],
            'person.phone.country' => ['required', 'string', 'max:3'],
            'person.phone.area' => ['required', 'string', 'max:3'],
            'person.phone.number' => ['required', 'string', 'max:9'],

            'company.name' => [$isPj ? 'required' : 'nullable', 'string', 'max:160'],
            'company.tax_id' => $isPj
                ? ['required', 'string', new ValidCpfCnpj('cnpj')]
                : ['nullable', 'string'],
            'company.address.street' => [$isPj ? 'required' : 'nullable', 'string', 'max:160'],
            'company.address.number' => [$isPj ? 'required' : 'nullable', 'string', 'max:20'],
            'company.address.complement' => ['nullable', 'string', 'max:60'],
            'company.address.locality' => [$isPj ? 'required' : 'nullable', 'string', 'max:100'],
            'company.address.city' => [$isPj ? 'required' : 'nullable', 'string', 'max:100'],
            'company.address.region_code' => [$isPj ? 'required' : 'nullable', 'string', 'size:2'],
            'company.address.postal_code' => [$isPj ? 'required' : 'nullable', 'string', 'regex:/^\d{5}-?\d{3}$/'],
            'company.address.country' => ['nullable', 'string', 'size:3'],
            'company.phone.country' => [$isPj ? 'required' : 'nullable', 'string', 'max:3'],
            'company.phone.area' => [$isPj ? 'required' : 'nullable', 'string', 'max:3'],
            'company.phone.number' => [$isPj ? 'required' : 'nullable', 'string', 'max:9'],
        ];
    }

    public function messages(): array
    {
        return __('messages.validation.messages');
    }
}
