<?php

namespace App\Models\Subscription;

use App\Models\BaseModel;

class Refund extends BaseModel
{
    protected $table = 'refunds';

    protected $fillable = [
        'tenant_id',
        'payment_id',
        'reason',
        'amount',
        'type',
        'requested_by',
        'protocol',
        'provider_refund_id',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'payment_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
