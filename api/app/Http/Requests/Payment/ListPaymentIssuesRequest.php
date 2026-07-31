<?php

namespace App\Http\Requests\Payment;

use App\Services\Payment\PaymentIssueService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros da listagem administrativa cross-tenant de pendências de
 * pagamento (`GET /payments/issues`, staff interno da PegaTicket).
 */
class ListPaymentIssuesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', Rule::in(PaymentIssueService::TYPES)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
