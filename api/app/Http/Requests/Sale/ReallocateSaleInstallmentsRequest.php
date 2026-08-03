<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Substituição em lote das parcelas NÃO PAGAS de uma venda — resolve a
 * limitação matemática de POST/PUT/DELETE individuais (soma validada a
 * cada chamada isolada torna redistribuição entre parcelas impossível
 * sem 422 intermediário). Aqui a soma só é validada UMA VEZ, no final da
 * operação inteira. Ver App\Services\Sale\SaleInstallmentService::reallocate()
 * e .claude/memory/architecture-decisions.md.
 */
class ReallocateSaleInstallmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Array pode ser vazio (excluir todas as parcelas não pagas é
            // uma operação válida, ainda que rara — a validação final de
            // soma continua se aplicando: só passa se as parcelas pagas
            // já existentes, sozinhas, cobrirem o total).
            'installments' => ['present', 'array'],

            // uuid ausente/null = parcela nova; presente = edita a
            // parcela não paga correspondente (mesmas regras de valor dos
            // endpoints individuais, StoreSaleInstallmentRequest).
            'installments.*.uuid' => ['nullable', 'uuid'],
            'installments.*.installment_number' => ['required', 'integer', 'min:1'],
            'installments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'installments.*.due_date' => ['required', 'date'],
        ];
    }
}
