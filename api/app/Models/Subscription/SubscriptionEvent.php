<?php

namespace App\Models\Subscription;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * Log imutável de eventos de assinatura. Sem updated_at/soft delete;
 * created_at é setado manualmente (public $timestamps = false).
 */
class SubscriptionEvent extends Model
{
    use HasUuid;

    protected $table = 'subscription_events';

    public $timestamps = false;

    protected $fillable = [
        'subscription_id',
        'type',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
        'subscription_id',
    ];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}
