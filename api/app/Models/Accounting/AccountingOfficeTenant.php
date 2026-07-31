<?php

namespace App\Models\Accounting;

use App\Enums\Accounting\AccountingAccessStatus;
use App\Models\Tenant\Tenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Vínculo N:N contador <-> tenant com aprovação (roadmap 2C). Sem BaseModel
 * (mesmo desvio de FinalCustomerTenantLink). `scopes` é a lista de permissões
 * de leitura concedidas pelo tenant na aprovação (ex: ['financial.read']).
 */
class AccountingOfficeTenant extends Model
{
    use HasUuid;

    protected $table = 'accounting_office_tenant';

    protected $fillable = [
        'uuid',
        'accounting_office_id',
        'tenant_id',
        'status',
        'scopes',
        'requested_at',
        'approved_at',
        'revoked_at',
        'approved_by',
    ];

    protected $casts = [
        'scopes' => 'array',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
        'accounting_office_id',
    ];

    public function isApproved(): bool
    {
        return $this->status === AccountingAccessStatus::Approved->value;
    }

    public function accountingOffice(): BelongsTo
    {
        return $this->belongsTo(AccountingOffice::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AccountingRequestMessage::class);
    }
}
