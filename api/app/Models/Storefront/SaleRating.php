<?php

namespace App\Models\Storefront;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Sale\Sale;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Avaliação do cliente final sobre uma venda entregue — sem BaseModel de
 * propósito (mesmo desvio de ProductFavorite/CouponRedemption). Unique em
 * sale_id (schema) garante 1 avaliação por compra; SaleRatingService checa
 * antes de deixar o DB estourar. Só created_at faz sentido (sem edição de
 * avaliação nesta fatia) — UPDATED_AT desligado.
 */
class SaleRating extends Model
{
    public $timestamps = true;

    const UPDATED_AT = null;

    protected $table = 'sale_ratings';

    protected $fillable = [
        'tenant_id',
        'sale_id',
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

    public function sale()
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function finalCustomer()
    {
        return $this->belongsTo(FinalCustomer::class);
    }
}
