<?php

namespace App\Models\Storefront;

use App\Models\FinalCustomer\FinalCustomer;
use Illuminate\Database\Eloquent\Model;

/**
 * Inscrição de Web Push (VAPID) do cliente final — sem BaseModel de
 * propósito (mesmo desvio de ProductFavorite/SaleRating): sempre
 * criada/atualizada pelo próprio cliente final via subscribe(), nunca por
 * staff, sem soft delete/created_by. Só created_at faz sentido (subscribe é
 * upsert por endpoint, não update parcial) — UPDATED_AT desligado, mesmo
 * mecanismo de ProductFavorite/FinalCustomerOtp.
 */
class PushSubscription extends Model
{
    public $timestamps = true;

    const UPDATED_AT = null;

    protected $table = 'push_subscriptions';

    protected $fillable = [
        'final_customer_id',
        'endpoint',
        'p256dh',
        'auth',
    ];

    public function finalCustomer()
    {
        return $this->belongsTo(FinalCustomer::class);
    }
}
