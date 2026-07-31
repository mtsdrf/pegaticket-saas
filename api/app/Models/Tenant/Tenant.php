<?php

namespace App\Models\Tenant;

use App\Models\BaseModel;
use App\Models\Location\Endereco;

class Tenant extends BaseModel
{
    protected $table = 'tenants';

    protected $fillable = [
        'name',
        'razao_social',
        'slug',
        'plan_id',
        'is_active',
        'trial_ends_at',
        'next_order_code',
        'logo_path',
        'logo_data',
        'logo_mime',
        'logo_updated_at',
        'endereco_id',
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
        'next_order_code' => 'integer',
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

    public function endereco()
    {
        return $this->belongsTo(Endereco::class);
    }
}
