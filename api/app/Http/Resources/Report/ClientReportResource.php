<?php

namespace App\Http\Resources\Report;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Específico do relatório de clientes (GET /reports/clients): embute os
 * pedidos ativos pagos+entregues do cliente (pré-filtrados no
 * ReportService via with()), diferente do ClientResource padrão do CRUD
 * de Cliente, que nunca embute pedidos. Mantido separado para não mudar
 * o contrato do ClientResource usado pelo CRUD.
 */
class ClientReportResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'phone_primary' => $this->phone_primary,
            'phone_secondary' => $this->phone_secondary,
            'endereco' => $this->endereco ? [
                'logradouro' => $this->endereco->logradouro,
                'cep' => $this->endereco->cep,
                'estado_name' => $this->endereco->estado?->name,
                'cidade_name' => $this->endereco->cidade?->name,
                'bairro_name' => $this->endereco->bairro?->name,
            ] : null,
            'orders' => $this->whenLoaded('orders', fn() => $this->orders->map(fn($order) => [
                'uuid' => $order->uuid,
                'total_amount' => $order->total_amount,
                'is_paid' => $order->is_paid,
                'paid_at' => $order->paid_at,
                'is_delivered' => $order->is_delivered,
                'delivered_at' => $order->delivered_at,
                'created_at' => $order->created_at,
            ])),
        ];
    }
}
