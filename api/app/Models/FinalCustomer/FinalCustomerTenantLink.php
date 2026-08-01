<?php

namespace App\Models\FinalCustomer;

use App\Models\Tenant\Tenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vínculo explícito entre FinalCustomer e uma loja (tenant) — é o registro
 * POR-TENANT do cliente final (absorveu os campos de App\Models\Client\Client
 * descontinuado em 2026-07-31: documento/telefone/notes/flags).
 * Sem BaseModel de propósito (mesmo desvio de FinalCustomer).
 */
class FinalCustomerTenantLink extends Model
{
    use HasUuid;

    protected $table = 'final_customer_tenant_links';

    protected $fillable = [
        'uuid',
        'final_customer_id',
        'tenant_id',
        'cpf_cnpj',
        'ie',
        'ie_indicator',
        'phone_primary',
        'phone_secondary',
        'notes',
        'is_trusted',
        'is_active',
        'confirmed_at',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'is_trusted' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'id',
        'final_customer_id',
    ];

    public function finalCustomer(): BelongsTo
    {
        return $this->belongsTo(FinalCustomer::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
