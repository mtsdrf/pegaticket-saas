<?php

namespace App\Models\Subscription;

use App\Models\BaseModel;
use App\Models\Plan\Plan;

class PlanPrice extends BaseModel
{
    protected $table = 'plan_prices';

    protected $fillable = [
        'plan_id',
        'billing_period',
        'amount',
        'discount_percent',
        'valid_from',
        'valid_to',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'id',
        'plan_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
