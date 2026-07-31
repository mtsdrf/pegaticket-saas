<?php

namespace App\Models\Storefront;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Model;

/**
 * Favorito de produto pelo cliente final — sem BaseModel de propósito (mesmo
 * desvio de FinalCustomerTenantLink/CouponRedemption): sempre criado/removido
 * pelo próprio cliente final via toggle, nunca por staff, sem soft
 * delete/created_by. Só created_at faz sentido (toggle é create/delete, não
 * update) — UPDATED_AT desligado, mesmo mecanismo de FinalCustomerOtp.
 */
class ProductFavorite extends Model
{
    public $timestamps = true;

    const UPDATED_AT = null;

    protected $table = 'product_favorites';

    protected $fillable = [
        'final_customer_id',
        'product_id',
    ];

    public function finalCustomer()
    {
        return $this->belongsTo(FinalCustomer::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
