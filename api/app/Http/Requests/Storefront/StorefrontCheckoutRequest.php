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

    public function rules(): array
    {
        // Resolve o tenant pelo slug da rota pra escopar items.*.product_uuid
        // — mesmo espírito de StoreSaleRequest (Rule::exists()->where
        // tenant_id), só que aqui o tenant vem do slug público, não de
        // app('tenant_id') (não há middleware `tenant` nesta rota). Sem esse
        // escopo, um product_uuid de OUTRO tenant passaria a validação e só
        // falharia dentro da transação de SaleService::create() com um 404
        // cru, em vez de um 422 de validação limpo.
        $tenantId = DB::table('tenants')
            ->where('slug', $this->route('slug'))
            ->whereNull('deleted_at')
            ->value('id');

        return [
            'items' => ['required', 'array', 'min:1'],
            'hold_uuid' => ['nullable', 'uuid'],
            'session_token' => ['nullable', 'string', 'max:120'],
            'items.*.ticket_type_uuid' => [
                'required_without:items.*.event_product_uuid',
                'nullable',
                'uuid',
                Rule::exists('ticket_types', 'uuid')->where(function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId)->whereNull('deleted_at');
                }),
            ],
            'items.*.event_product_uuid' => [
                'required_without:items.*.ticket_type_uuid',
                'nullable',
                'uuid',
                Rule::exists('event_products', 'uuid')->where(function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId)->whereNull('deleted_at');
                }),
            ],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            // Observação por item — distinta de `notes` (recado da venda
            // inteiro, abaixo).
            'items.*.notes' => ['nullable', 'string', 'max:200'],

            // Participantes (spec 5.10 Etapa 2) — opcional; quando ausente,
            // os Tickets emitidos ficam com attendee_name/document nulos
            // (SIMPLIFICAÇÃO DOCUMENTADA: participante = comprador nesta
            // rodada). Só faz sentido pra item de ticket_type_uuid.
            'items.*.participants' => ['nullable', 'array'],
            'items.*.participants.*.name' => ['nullable', 'string', 'max:255'],
            'items.*.participants.*.document' => ['nullable', 'string', 'max:30'],

            'client_name' => ['required', 'string', 'max:255'],
            'client_last_name' => ['required', 'string', 'max:255'],
            'client_phone' => ['required', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],

            // Cupom (roadmap Delivery, Fase 3) — só formato aqui;
            // existência/elegibilidade é checada por
            // CouponService::validateForCheckout() dentro da transação do
            // checkout (guard 4), não na validação do request.
            'coupon_code' => ['nullable', 'string', 'max:50'],

            // Fallback de atribuição de afiliado quando o checkout não
            // veio de um hold (o caminho principal é o hold já carregar o
            // affiliate_id capturado em StorefrontHoldService::createHold).
            'affiliate_code' => ['nullable', 'string', 'max:40'],

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
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.ticket_type_uuid.exists' => __('messages.sale.invalid_product'),
            'items.*.event_product_uuid.exists' => __('messages.sale.invalid_product'),
        ];
    }
}
