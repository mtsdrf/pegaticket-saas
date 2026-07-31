<?php

namespace App\Models\Subscription;

use App\Models\BaseModel;
use App\Models\Plan\Plan;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends BaseModel
{
    protected $table = 'subscriptions';

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'plan_price_id',
        'billing_period',
        'status',
        'preapproval_id',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'next_charge_at',
        'cancel_at',
        'canceled_at',
        'grace_period_ends_at',
        'auto_renew',
        'accepted_terms_version',
        'accepted_at',
        'accepted_ip',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'next_charge_at' => 'datetime',
        'cancel_at' => 'datetime',
        'canceled_at' => 'datetime',
        'grace_period_ends_at' => 'datetime',
        'accepted_at' => 'datetime',
        'auto_renew' => 'boolean',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'plan_id',
        'plan_price_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function planPrice()
    {
        return $this->belongsTo(PlanPrice::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class);
    }
}
