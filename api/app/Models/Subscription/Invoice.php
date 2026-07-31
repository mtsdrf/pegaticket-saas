<?php

namespace App\Models\Subscription;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Invoice extends BaseModel
{
    protected $table = 'invoices';

    protected $fillable = [
        'subscription_id',
        'tenant_id',
        'competence_period',
        'due_date',
        'amount_gross',
        'discount_amount',
        'amount_net',
        'status',
        'dispute_deadline_at',
        'fiscal_document_id',
    ];

    protected $casts = [
        'due_date' => 'date',
        'amount_gross' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'amount_net' => 'decimal:2',
        'dispute_deadline_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
        'subscription_id',
        'tenant_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }
}
