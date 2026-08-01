<?php

namespace App\Models\Tenant;

use App\Models\BaseModel;

class TenantSettings extends BaseModel
{
    protected $table = 'tenant_settings';

    protected $fillable = [
        'tenant_id',
        'send_tracking_link_whatsapp',
        'minimum_order_value',
        'estimated_preparation_minutes',
        'accepted_payment_methods',
        'payment_receiving_method',
        'payment_pix_key',
        'service_fee_percent',
        'service_fee_mandatory',
        'allow_store_pickup',
        'storefront_enabled',
        'catalog_layout',
    ];

    protected $casts = [
        'send_tracking_link_whatsapp' => 'boolean',
        'minimum_order_value' => 'float',
        'estimated_preparation_minutes' => 'integer',
        'accepted_payment_methods' => 'array',
        // Chave Pix do tenant criptografada em repouso (cast nativo do
        // Eloquent, sem serviço externo) — roadmap 2A.
        'payment_pix_key' => 'encrypted',
        'service_fee_percent' => 'float',
        'service_fee_mandatory' => 'boolean',
        'allow_store_pickup' => 'boolean',
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
