<?php

namespace App\Models\Fiscal;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;

/**
 * Regra tributária parametrizada e versionada por vigência (roadmap Fiscal
 * D0). Cadastro puro — nenhum cálculo de imposto sobre pedido acontece aqui.
 * Ver a migration create_tax_rules_table para o shape de `scope`.
 */
class TaxRule extends BaseModel
{
    protected $table = 'tax_rules';

    protected $fillable = [
        'tenant_id',
        'tax_type',
        'scope',
        'rate_percent',
        'valid_from',
        'valid_to',
        'is_active',
    ];

    protected $casts = [
        'scope' => 'array',
        'rate_percent' => 'decimal:4',
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
        'is_active' => 'boolean',
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
