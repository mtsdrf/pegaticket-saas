<?php

namespace App\Models\Ticket;

use App\Models\BaseModel;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Subscription\Payment;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Revenda oficial verificada de um Ticket (roadmap Fase 4). Fechamento da
 * revenda reaproveita TicketService::transfer() — este model é só o
 * anúncio/estado da revenda, nunca dono da lógica de rotação de QR.
 */
class TicketResaleListing extends BaseModel
{
    protected $table = 'ticket_resale_listings';

    protected $fillable = [
        'tenant_id',
        'ticket_id',
        'seller_final_customer_id',
        'buyer_final_customer_id',
        'original_unit_price',
        'asking_price',
        'status',
        'seller_payout_amount',
        'seller_payout_status',
        'seller_payout_released_at',
        'seller_payout_released_by',
        'payment_id',
        'sold_at',
        'cancelled_at',
    ];

    protected $casts = [
        'original_unit_price' => 'decimal:2',
        'asking_price' => 'decimal:2',
        'seller_payout_amount' => 'decimal:2',
        'seller_payout_released_at' => 'datetime',
        'sold_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'ticket_id',
        'seller_final_customer_id',
        'buyer_final_customer_id',
        'payment_id',
        'seller_payout_released_by',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(FinalCustomer::class, 'seller_final_customer_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(FinalCustomer::class, 'buyer_final_customer_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
