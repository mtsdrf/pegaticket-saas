<?php

namespace App\Models\Report;

use App\Models\BaseModel;
use App\Models\Order\Order;
use App\Models\Order\OrderInstallment;
use App\Models\Tenant\Tenant;
use App\Models\User;

class ReceivableInteraction extends BaseModel
{
    protected $table = 'receivable_interactions';

    protected $fillable = [
        'tenant_id',
        'order_id',
        'order_installment_id',
        'interaction_type',
        'channel',
        'notes',
        'promised_amount',
        'promised_due_date',
        'contacted_at',
    ];

    protected $casts = [
        'promised_amount' => 'decimal:2',
        'promised_due_date' => 'date',
        'contacted_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'order_id',
        'order_installment_id',
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
        return $this->belongsTo(Order::class);
    }

    public function installment()
    {
        return $this->belongsTo(OrderInstallment::class, 'order_installment_id');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
