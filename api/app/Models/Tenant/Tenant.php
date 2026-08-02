<?php

namespace App\Models\Tenant;

use App\Models\BaseModel;

class Tenant extends BaseModel
{
    protected $table = 'tenants';

    protected $fillable = [
        'name',
        'cnpj',
        'razao_social',
        'slug',
        'plan_id',
        'is_active',
        'trial_ends_at',
        'next_sale_code',
        'logo_path',
        'logo_data',
        'logo_mime',
        'logo_updated_at',
        // Contato do estabelecimento (migração de dados legados)
        'email',
        'phone',
        'mobile_phone',
        'whatsapp',
        'facebook',
        'instagram',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'trial_ends_at' => 'datetime',
        'logo_updated_at' => 'datetime',
        'next_sale_code' => 'integer',
    ];

    protected $hidden = [
        'id',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function plan()
    {
        return $this->belongsTo(\App\Models\Plan\Plan::class, 'plan_id');
    }
}
