<?php

namespace App\Models\Sale;

use App\Models\BaseModel;
use App\Models\Event\EventProduct;
use App\Models\Event\TicketBatch;
use App\Models\Event\TicketType;
use App\Models\Tenant\Tenant;
use App\Models\Venue\Seat;

/**
 * Sem Repository/Service próprios: SaleItem é um objeto de valor
 * inteiramente possuído por Sale (sem CRUD/rota independente), criado e
 * lido só através de SaleService. Ver App\Services\Sale\SaleService.
 *
 * `ticket_type_id`/`event_product_id`: exatamente um preenchido por item
 * (ingresso OU adicional/estacionamento) — substitui `product_id` desde a
 * migração PegaTicket (roadmap seção 2.4/4A). Garantido em
 * SaleService::resolveOrderItemLine(), não em constraint de banco.
 */
class SaleItem extends BaseModel
{
    protected $table = 'order_items';

    protected $fillable = [
        'tenant_id',
        'order_id',
        'ticket_type_id',
        'event_product_id',
        'seat_id',
        'ticket_batch_id',
        'quantity',
        'unit_price',
        'line_total',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'order_id',
        'ticket_type_id',
        'event_product_id',
        'seat_id',
        'ticket_batch_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function order()
    {
        return $this->belongsTo(Sale::class, 'order_id');
    }

    public function ticketType()
    {
        return $this->belongsTo(TicketType::class);
    }

    public function eventProduct()
    {
        return $this->belongsTo(EventProduct::class);
    }

    public function seat()
    {
        return $this->belongsTo(Seat::class);
    }

    public function ticketBatch()
    {
        return $this->belongsTo(TicketBatch::class);
    }
}
