<?php

namespace App\Models\Storefront;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Order\Order;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Avaliação do cliente final sobre um pedido entregue — sem BaseModel de
 * propósito (mesmo desvio de ProductFavorite/CouponRedemption). Unique em
 * order_id (schema) garante 1 avaliação por pedido; OrderRatingService checa
 * antes de deixar o DB estourar. Só created_at faz sentido (sem edição de
 * avaliação nesta fatia) — UPDATED_AT desligado.
 */
class OrderRating extends Model
{
    public $timestamps = true;

    const UPDATED_AT = null;

    protected $table = 'order_ratings';

    protected $fillable = [
        'tenant_id',
        'order_id',
        'final_customer_id',
        'rating',
        'comment',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function finalCustomer()
    {
        return $this->belongsTo(FinalCustomer::class);
    }
}
