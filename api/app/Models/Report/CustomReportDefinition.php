<?php

namespace App\Models\Report;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;

/**
 * Definição salva de relatório personalizado (roadmap 5.6). Guarda só
 * chaves whitelisted — nunca SQL nem nome de coluna livre. Ver
 * App\Support\Report\CustomReportFieldWhitelist (fonte de verdade dos
 * campos permitidos) e App\Services\Report\CustomReportQueryBuilder
 * (tradução chave->query, sempre com tenant_id injetado no nível mais
 * baixo, nunca vindo do payload do usuário).
 */
class CustomReportDefinition extends BaseModel
{
    protected $table = 'custom_report_definitions';

    protected $fillable = [
        'tenant_id',
        'name',
        'data_source',
        'dimensions',
        'metrics',
        'calculated_metrics',
        'filters',
    ];

    protected $casts = [
        'dimensions' => 'array',
        'metrics' => 'array',
        'calculated_metrics' => 'array',
        'filters' => 'array',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
