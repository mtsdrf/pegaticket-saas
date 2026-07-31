<?php

namespace App\Models\Tenant;

use App\Models\BaseModel;
use App\Models\Location\Endereco;

class Tenant extends BaseModel
{
    protected $table = 'tenants';

    protected $fillable = [
        'name',
        'cnpj',
        'ie',
        'im',
        'cnae',
        'tax_regime',
        'fiscal_environment',
        'ibge_city_code',
        'fiscal_provider',
        'fiscal_nfe_series',
        'fiscal_nfce_series',
        'fiscal_nfse_series',
        'fiscal_next_nfe_number',
        'fiscal_next_nfce_number',
        'fiscal_next_nfse_number',
        'fiscal_nfce_csc_id',
        'fiscal_nfce_csc_code',
        'fiscal_provider_api_token',
        'fiscal_certificate_a1_data',
        'fiscal_certificate_a1_name',
        'fiscal_certificate_a1_mime',
        'fiscal_certificate_a1_password',
        'fiscal_certificate_a1_updated_at',
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
        'fiscal_certificate_a1_updated_at' => 'datetime',
        'next_order_code' => 'integer',
        'fiscal_next_nfe_number' => 'integer',
        'fiscal_next_nfce_number' => 'integer',
        'fiscal_next_nfse_number' => 'integer',
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
