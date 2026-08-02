<?php

namespace App\Models\Tenant;

use App\Models\BaseModel;

class TenantSettings extends BaseModel
{
    protected $table = 'tenant_settings';

    protected $fillable = [
        'tenant_id',
        'accepted_payment_methods',
        'payment_receiving_method',
        'payment_pix_key',
        'service_fee_percent',
        'service_fee_mandatory',
        'storefront_enabled',
        'catalog_layout',
        'hold_duration_minutes',
    ];

    protected $casts = [
        'accepted_payment_methods' => 'array',
        // Chave Pix do tenant criptografada em repouso (cast nativo do
        // Eloquent, sem serviço externo) — roadmap 2A.
        'payment_pix_key' => 'encrypted',
        'service_fee_percent' => 'float',
        'service_fee_mandatory' => 'boolean',
        'storefront_enabled' => 'boolean',
        'hold_duration_minutes' => 'integer',
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
