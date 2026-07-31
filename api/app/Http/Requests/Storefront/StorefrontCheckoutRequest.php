<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * authorize() sempre true — a autorização real é o middleware customer.jwt
 * na rota (identidade do cliente final), não uma permissão de staff.
 */
class StorefrontCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Retirada na loja (roadmap Delivery) — cliente antigo do frontend que
     * ainda não manda fulfillment_type continua tratado como 'delivery',
     * preservando 100% a validação de endereço já existente.
     */
    protected function prepareForValidation(): void
    {
        if (!$this->filled('fulfillment_type')) {
            $this->merge(['fulfillment_type' => 'delivery']);
        }
    }

    public function rules(): array
    {
        // Resolve o tenant pelo slug da rota pra escopar items.*.product_uuid
        // — mesmo espírito de StoreOrderRequest (Rule::exists()->where
        // tenant_id), só que aqui o tenant vem do slug público, não de
        // app('tenant_id') (não há middleware `tenant` nesta rota). Sem esse
        // escopo, um product_uuid de OUTRO tenant passaria a validação e só
        // falharia dentro da transação de OrderService::create() com um 404
        // cru, em vez de um 422 de validação limpo.
        $tenantId = DB::table('tenants')
            ->where('slug', $this->route('slug'))
            ->whereNull('deleted_at')
            ->value('id');

        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_uuid' => [
                'required',
                'uuid',
                Rule::exists('products', 'uuid')->where(function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId)->whereNull('deleted_at');
                }),
            ],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            // Observação por item (ex: "sem cebola") — distinta de `notes`
            // (recado do pedido inteiro, abaixo).
            'items.*.notes' => ['nullable', 'string', 'max:200'],
            'items.*.options' => ['nullable', 'array'],
            'items.*.options.*.product_option_uuid' => [
                'required',
                'uuid',
                Rule::exists('product_options', 'uuid')->where(function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId)->whereNull('deleted_at');
                }),
            ],
            'items.*.options.*.quantity' => ['nullable', 'integer', 'min:1'],

            'client_name' => ['required', 'string', 'max:255'],
            'client_last_name' => ['required', 'string', 'max:255'],
            'client_phone' => ['required', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],

            // Retirada na loja (roadmap Delivery) — quando pickup, o
            // endereço de entrega deixa de ser obrigatório (ver Guard de
            // StorefrontCheckoutService::checkout(), que pula a taxa de
            // entrega por bairro nesse caso).
            'fulfillment_type' => ['required', 'string', Rule::in(['delivery', 'pickup'])],

            // Endereço de entrega — mesmo shape de campo já usado por
            // CreateClientDTO/ClientService (estado/cidade/bairro globais,
            // sem tenant_id). required_if:fulfillment_type,delivery.
            'estado_uuid' => [
                'nullable',
                'required_if:fulfillment_type,delivery',
                'uuid',
                Rule::exists('estados', 'uuid')->whereNull('deleted_at'),
            ],
            'cidade_uuid' => [
                'nullable',
                'required_if:fulfillment_type,delivery',
                'uuid',
                Rule::exists('cidades', 'uuid')->whereNull('deleted_at'),
            ],
            'bairro_uuid' => [
                'nullable',
                'required_if:fulfillment_type,delivery',
                'uuid',
                Rule::exists('bairros', 'uuid')->whereNull('deleted_at'),
            ],
            'logradouro' => ['nullable', 'required_if:fulfillment_type,delivery', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'cep' => ['nullable', 'string', 'max:9'],

            // Cupom (roadmap Delivery, Fase 3) — só formato aqui;
            // existência/elegibilidade é checada por
            // CouponService::validateForCheckout() dentro da transação do
            // checkout (guard 4), não na validação do request.
            'coupon_code' => ['nullable', 'string', 'max:50'],

            // Meio de pagamento pretendido (roadmap cupom por meio de
            // pagamento) — só formato aqui, nullable: nem todo checkout usa
            // cupom restrito, não adiciona fricção por padrão. A Service
            // decide se é exigido de fato (só quando o cupom aplicado
            // restringe por meio).
            'payment_method' => ['nullable', 'string', Rule::in(['cash', 'pix', 'credit_card', 'debit_card'])],

            // Troco (pagamento em dinheiro) — só formato aqui; quando
            // needs_change=true, change_for_amount passa a ser obrigatório
            // (o valor que o cliente vai entregar em espécie).
            'needs_change' => ['nullable', 'boolean'],
            'change_for_amount' => ['nullable', 'numeric', 'min:0', 'required_if:needs_change,true'],

            // Cashback (roadmap Delivery, Fase 5) — só um booleano: usa o
            // teto elegível calculado no servidor ou não. Valor efetivo é
            // sempre recalculado por CashbackService::reserveRedemption()
            // contra o saldo real, nunca confia num valor vindo do request.
            'use_cashback' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.product_uuid.exists' => __('messages.order.invalid_product'),
        ];
    }
}
