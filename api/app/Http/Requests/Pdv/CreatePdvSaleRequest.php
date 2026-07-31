<?php

namespace App\Http\Requests\Pdv;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CreatePdvSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Sessão de caixa opcional no corpo: sem ela, a venda usa a sessão
            // aberta atual do tenant. Quando informada, precisa ser do tenant.
            'cash_session_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('cash_sessions', 'uuid')
                    ->where(fn($q) => $q->where('tenant_id', app('tenant_id'))->whereNull('deleted_at')),
            ],
            // Cliente opcional (consumidor final sem cadastro). Quando
            // informado, precisa ser do tenant ativo.
            'client_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('clients', 'uuid')
                    ->where(fn($q) => $q->where('tenant_id', app('tenant_id'))->whereNull('deleted_at')),
            ],
            'stock_location_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('stock_locations', 'uuid')
                    ->where(fn($q) => $q->where('tenant_id', app('tenant_id'))->whereNull('deleted_at')),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
            'client_sale_uuid' => ['nullable', 'uuid'],

            // Operador identificado por PIN (roadmap A4, item 15) — resolvido
            // previamente via /pdv/operator-session. Precisa ser staff ativo
            // do tenant atual (mesmo espírito de FK cross-tenant: 422, não
            // 404/500, quando não bate).
            'operator_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('users', 'uuid')->where(function ($q) {
                    $q->whereExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('tenant_users')
                            ->whereColumn('tenant_users.user_id', 'users.id')
                            ->where('tenant_users.tenant_id', app('tenant_id'))
                            ->where('tenant_users.is_active', true)
                            ->whereNull('tenant_users.deleted_at');
                    });
                }),
            ],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_uuid' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],

            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'string', Rule::in(['cash', 'pix', 'card', 'debit', 'credit'])],
            'payments.*.amount' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
