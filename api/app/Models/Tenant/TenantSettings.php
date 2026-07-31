<?php

namespace App\Models\Tenant;

use App\Models\BaseModel;

class TenantSettings extends BaseModel
{
    protected $table = 'tenant_settings';

    /**
     * allow_delivery=true por padrão (migration) precisa também estar
     * aqui: Eloquent::create() com o campo omitido não repopula o atributo
     * a partir do default de coluna do banco após o INSERT — o cast
     * 'boolean' aplicado a null vira false, o oposto do default real.
     * Mesmo raciocínio não se aplicava a allow_store_pickup (default false
     * já coincide com null->false).
     */
    protected $attributes = [
        'allow_delivery' => true,
    ];

    protected $fillable = [
        'tenant_id',
        'send_tracking_link_whatsapp',
        'block_order_without_stock',
        'minimum_order_value',
        'estimated_preparation_minutes',
        'cashback_enabled',
        'cashback_percentage',
        'cashback_max_per_order',
        'cashback_hold_days',
        'cashback_expiration_days',
        'cashback_redeem_max_percentage',
        'cashback_name',
        'accepted_payment_methods',
        'payment_receiving_method',
        'payment_pix_key',
        'service_fee_percent',
        'service_fee_mandatory',
        'allow_store_pickup',
        'allow_delivery',
        'storefront_enabled',
        'catalog_layout',
    ];

    protected $casts = [
        'send_tracking_link_whatsapp' => 'boolean',
        'block_order_without_stock' => 'boolean',
        'minimum_order_value' => 'float',
        'estimated_preparation_minutes' => 'integer',
        'cashback_enabled' => 'boolean',
        'cashback_percentage' => 'float',
        'cashback_max_per_order' => 'float',
        'cashback_hold_days' => 'integer',
        'cashback_expiration_days' => 'integer',
        'cashback_redeem_max_percentage' => 'float',
        'accepted_payment_methods' => 'array',
        // Chave Pix do tenant criptografada em repouso (cast nativo do
        // Eloquent, sem serviço externo) — roadmap 2A.
        'payment_pix_key' => 'encrypted',
        'service_fee_percent' => 'float',
        'service_fee_mandatory' => 'boolean',
        'allow_store_pickup' => 'boolean',
        'allow_delivery' => 'boolean',
        'storefront_enabled' => 'boolean',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
