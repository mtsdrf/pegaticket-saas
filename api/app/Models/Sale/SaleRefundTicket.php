<?php

namespace App\Models\Sale;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pivot sale_refunds <-> tickets — lista de ingressos afetados por um
 * estorno (obrigatória no parcial). Mesmo padrão de
 * App\Models\Permission\GroupPermission (Pivot + HasUuid + SoftDeletes).
 */
class SaleRefundTicket extends Pivot
{
    use SoftDeletes, HasUuid;

    protected $table = 'sale_refund_tickets';
    public $incrementing = true;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'sale_refund_id',
        'ticket_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'sale_refund_id',
        'ticket_id',
        'created_by',
        'updated_by',
        'deleted_by',
        'deleted_at',
    ];
}
