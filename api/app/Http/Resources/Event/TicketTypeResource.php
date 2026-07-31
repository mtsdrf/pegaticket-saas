<?php

namespace App\Http\Resources\Event;

use App\Services\Permission\PermissionService;
use App\Support\MediaUrl;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class TicketTypeResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'image_url' => MediaUrl::resolvePublic(
                $this->image_path,
                $this->image_data ?? $this->image_mime,
                '/api/v1/ticket-types/' . $this->uuid . '/image',
                $this->image_updated_at,
                'ticket_type'
            ),
            'quantity_available' => $this->quantity_available,
            'min_per_order' => $this->min_per_order,
            'max_per_order' => $this->max_per_order,
            'sales_start_at' => $this->sales_start_at,
            'sales_end_at' => $this->sales_end_at,
            'status' => $this->status,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'brand' => $this->brand,
            'unit' => $this->unit,
            // Derivado da soma de StockBalance.quantity_on_hand — mesmo
            // padrão de ProductResource (campo legado, não fonte de verdade).
            'stock_quantity' => (float) ($this->stock_balances_sum_quantity_on_hand ?? 0),
            'is_lot_controlled' => $this->is_lot_controlled,
            'is_expiry_controlled' => $this->is_expiry_controlled,
            'is_serial_controlled' => $this->is_serial_controlled,
            'min_stock' => $this->min_stock !== null ? (float) $this->min_stock : null,
            'max_stock' => $this->max_stock !== null ? (float) $this->max_stock : null,
            'reorder_point' => $this->reorder_point !== null ? (float) $this->reorder_point : null,
            'reorder_qty' => $this->reorder_qty !== null ? (float) $this->reorder_qty : null,
            'event' => $this->whenLoaded('event', fn() => [
                'uuid' => $this->event->uuid,
                'name' => $this->event->name,
            ]),
            'created_at' => $this->created_at,
        ];

        if (Auth::id() && app(PermissionService::class)->userCanViaGroups(Auth::id(), 'stock', 'view_costs')) {
            $data['last_purchase_cost'] = $this->last_purchase_cost !== null ? (float) $this->last_purchase_cost : null;
        }

        return $data;
    }
}
