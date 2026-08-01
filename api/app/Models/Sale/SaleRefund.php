<?php

namespace App\Models\Sale;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;
use App\Models\Ticket\Ticket;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Estorno externo registrado (spec 5.14) — domínio próprio de venda de
 * ingresso, distinto de App\Models\Subscription\Refund (rail de
 * assinatura clube->PegaTicket). Ver comentário completo na migration
 * `2026_08_01_120000_create_sale_refunds_table`.
 */
class SaleRefund extends BaseModel
{
    protected $table = 'sale_refunds';

    public const TYPE_TOTAL = 'total';
    public const TYPE_PARTIAL = 'parcial';

    public const STATUS_REGISTERED = 'registrado';

    protected $fillable = [
        'tenant_id',
        'order_id',
        'type',
        'amount',
        'reason',
        'refunded_at',
        'external_reference',
        'receipt_path',
        'receipt_mime',
        'notes',
        'release_seats',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'refunded_at' => 'datetime',
        'release_seats' => 'boolean',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'order_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'order_id');
    }

    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class, 'sale_refund_tickets')
            ->withTimestamps();
    }
}
