<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_uuid' => [
                'required',
                'uuid',
                Rule::exists('clients', 'uuid')->where(function ($query) {
                    $query->where('tenant_id', app('tenant_id'))->whereNull('deleted_at');
                }),
            ],
            'stock_location_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('stock_locations', 'uuid')->where(function ($query) {
                    $query->where('tenant_id', app('tenant_id'))->whereNull('deleted_at');
                }),
            ],
            'is_installment' => ['required', 'boolean'],
            'installments_count' => ['required_if:is_installment,true', 'nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],

            // Informativo, capturado só na criação (Order é imutável depois
            // — sem update genérico). Distinto de delivered_at, que só
            // deliver()/a cascata de payInstallment() setam.
            'expected_delivery_date' => ['nullable', 'date'],

            // Ecoam o default do formulário legado ("entregue"/"pago" já
            // marcados na criação) sem reabrir update genérico: disparam a
            // mesma lógica interna de deliver()/pay() dentro da transação
            // de criação. mark_as_paid não faz sentido pra pedido parcelado
            // (pagamento é sempre por parcela) — bloqueado via prohibited_if.
            'mark_as_delivered' => ['nullable', 'boolean'],
            'mark_as_paid' => ['nullable', 'boolean', 'prohibited_if:is_installment,true'],

            // Retirada na loja (roadmap Delivery) — mesma semântica do
            // checkout público, mas sem guard próprio aqui: staff já
            // escolhe manualmente client_uuid/stock_location_uuid, não há
            // conceito de endereço de entrega obrigatório neste fluxo.
            // Omitido = 'delivery' (comportamento atual).
            'fulfillment_type' => ['nullable', 'string', Rule::in(['delivery', 'pickup'])],

            // Meio de pagamento pretendido — só formato aqui, mesmo shape de
            // StorefrontCheckoutRequest. Hoje o fluxo do staff NÃO valida
            // cupom por código (CreateOrderDTO::couponId já vem resolvido,
            // sem CouponService envolvido nesta Request/Controller) — campo
            // aceito por simetria/futuro, sem efeito colateral nenhum ainda.
            // Ver .claude/memory/architecture-decisions.md.
            'payment_method' => ['nullable', 'string', Rule::in(['cash', 'pix', 'credit_card', 'debit_card'])],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_uuid' => [
                'required',
                'uuid',
                Rule::exists('products', 'uuid')->where(function ($query) {
                    $query->where('tenant_id', app('tenant_id'))->whereNull('deleted_at');
                }),
            ],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            // Opcional: preço praticado do item (desconto/acréscimo por
            // linha), igual ao legado. Omitido = comportamento atual
            // (usa Product.price atual, nunca confiado no request).
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            // Observação por item (ex: "sem cebola") — distinta de `notes`
            // (recado do pedido inteiro, acima).
            'items.*.notes' => ['nullable', 'string', 'max:200'],
            'items.*.options' => ['nullable', 'array'],
            'items.*.options.*.product_option_uuid' => [
                'required',
                'uuid',
                Rule::exists('product_options', 'uuid')->where(function ($query) {
                    $query->where('tenant_id', app('tenant_id'))->whereNull('deleted_at');
                }),
            ],
            'items.*.options.*.quantity' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'client_uuid.exists' => __('messages.order.invalid_client'),
            'stock_location_uuid.exists' => __('messages.order.invalid_stock_location'),
            'items.*.product_uuid.exists' => __('messages.order.invalid_product'),
            'installments_count.required_if' => __('messages.order.installments_count_required'),
            'mark_as_paid.prohibited_if' => __('messages.order.mark_as_paid_requires_non_installment'),
        ];
    }
}
