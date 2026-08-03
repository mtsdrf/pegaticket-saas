<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'final_customer_uuid' => [
                'required',
                'uuid',
                Rule::exists('final_customers', 'uuid'),
                function ($attribute, $value, $fail) {
                    $exists = \App\Models\FinalCustomer\FinalCustomerTenantLink::query()
                        ->where('tenant_id', app('tenant_id'))
                        ->whereHas('finalCustomer', fn($q) => $q->where('uuid', $value))
                        ->exists();

                    if (!$exists) {
                        $fail(__('messages.sale.invalid_client'));
                    }
                },
            ],
            'is_installment' => ['required', 'boolean'],
            'installments_count' => ['required_if:is_installment,true', 'nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],

            // Meio de pagamento pretendido — só formato aqui, mesmo shape de
            // StorefrontCheckoutRequest. Hoje o fluxo do staff NÃO valida
            // cupom por código (CreateSaleDTO::couponId já vem resolvido,
            // sem CouponService envolvido nesta Request/Controller) — campo
            // aceito por simetria/futuro, sem efeito colateral nenhum ainda.
            // Ver .claude/memory/architecture-decisions.md.
            'payment_method' => ['nullable', 'string', Rule::in(['cash', 'pix', 'credit_card', 'debit_card'])],

            'items' => ['required', 'array', 'min:1'],
            'items.*.ticket_type_uuid' => [
                'required_without:items.*.event_product_uuid',
                'nullable',
                'uuid',
                Rule::exists('ticket_types', 'uuid')->where(function ($query) {
                    $query->where('tenant_id', app('tenant_id'))->whereNull('deleted_at');
                }),
            ],
            'items.*.event_product_uuid' => [
                'required_without:items.*.ticket_type_uuid',
                'nullable',
                'uuid',
                Rule::exists('event_products', 'uuid')->where(function ($query) {
                    $query->where('tenant_id', app('tenant_id'))->whereNull('deleted_at');
                }),
            ],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            // Opcional: preço praticado do item (desconto/acréscimo por
            // linha), igual ao legado. Omitido = comportamento atual (usa
            // o preço atual do ingresso/produto do evento, nunca confiado
            // no request).
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            // Observação por item — distinta de `notes` (recado da venda
            // inteiro, acima).
            'items.*.notes' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'final_customer_uuid.exists' => __('messages.sale.invalid_client'),
            'items.*.ticket_type_uuid.exists' => __('messages.sale.invalid_product'),
            'items.*.event_product_uuid.exists' => __('messages.sale.invalid_product'),
            'installments_count.required_if' => __('messages.sale.installments_count_required'),
        ];
    }
}
