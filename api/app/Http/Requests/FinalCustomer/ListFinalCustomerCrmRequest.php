<?php

namespace App\Http\Requests\FinalCustomer;

use Illuminate\Foundation\Http\FormRequest;

class ListFinalCustomerCrmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],

            // Segmentação básica (Fase 6) — filtros simples sobre os totais
            // já agregados, combináveis entre si.
            'min_spent' => ['nullable', 'numeric', 'min:0'],
            'min_purchases' => ['nullable', 'integer', 'min:1'],
            'inactive_days' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
