<?php

namespace App\Http\Requests\Payment;

use App\Services\Payment\PaymentIssueService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `type` desambigua a fonte da `reference` (uuid de Payment, de
 * PaymentIdempotencyKey ou de Invoice) — necessário porque o mesmo
 * formato de uuid pode existir em mais de uma tabela.
 */
class ReprocessPaymentIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(PaymentIssueService::TYPES)],
        ];
    }
}
