<?php

namespace App\Models\Storefront;

use App\Models\BaseModel;
use App\Models\Event\TicketType;
use App\Models\Tenant\Tenant;

class ProductPromotion extends BaseModel
{
    protected $table = 'product_promotions';

    protected $fillable = [
        'tenant_id',
        'ticket_type_id',
        'promo_price',
        'discount_type',
        'discount_percentage',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'promo_price' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'ticket_type_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function ticketType()
    {
        return $this->belongsTo(TicketType::class);
    }

    /**
     * Preço final aplicado no catálogo/checkout. `fixed_price` (default,
     * comportamento original "de/por"): retorna `promo_price` congelado,
     * igual sempre foi. `percentage`: calcula em cima do `$basePrice`
     * VIGENTE passado pelo chamador (nunca congelado) — decisão consciente,
     * já que é um desconto percentual sobre um preço que pode mudar
     * (TicketType.price), diferente do "de/por" que é o preço de venda público
     * que o tenant decidiu fixar. Ver .claude/memory/architecture-decisions.md.
     */
    public function effectivePrice(float $basePrice): float
    {
        if ($this->discount_type === 'percentage') {
            $percentage = (float) $this->discount_percentage;

            return round($basePrice * (1 - ($percentage / 100)), 2);
        }

        return (float) $this->promo_price;
    }
}
