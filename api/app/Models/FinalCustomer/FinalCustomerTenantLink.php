<?php

namespace App\Models\FinalCustomer;

use App\Models\Client\Client;
use App\Models\Tenant\Tenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vínculo explícito entre FinalCustomer e uma loja (tenant+client) — ver
 * migration para a regra de negócio. Sem BaseModel de propósito (mesmo
 * desvio de FinalCustomer).
 */
class FinalCustomerTenantLink extends Model
{
    use HasUuid;

    protected $table = 'final_customer_tenant_links';

    protected $fillable = [
        'uuid',
        'final_customer_id',
        'tenant_id',
        'client_id',
        'confirmed_at',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
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

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
