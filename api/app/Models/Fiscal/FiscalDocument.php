<?php

namespace App\Models\Fiscal;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Documento fiscal (roadmap Fiscal D0). Máquina de estados pronta pra um
 * FiscalProvider de emissão real plugar depois — nesta fatia nenhum
 * documento é autorizado de verdade (ManualFiscalProvider só grava
 * status=pending). Polimórfico (Invoice de assinatura OU Order futuramente),
 * mesma filosofia de payments/refunds.
 */
class FiscalDocument extends BaseModel
{
    protected $table = 'fiscal_documents';

    protected $fillable = [
        'tenant_id',
        'documentable_type',
        'documentable_id',
        'document_type',
        'series',
        'document_number',
        'status',
        'provider',
        'provider_document_id',
        'payload_snapshot',
        'provider_response_payload',
        'xml_content',
        'pdf_path',
        'access_key',
        'submitted_at',
        'provider_status_checked_at',
        'authorized_at',
        'rejected_at',
        'canceled_at',
        'rejection_reason',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'provider_status_checked_at' => 'datetime',
        'authorized_at' => 'datetime',
        'rejected_at' => 'datetime',
        'canceled_at' => 'datetime',
        'payload_snapshot' => 'array',
        'provider_response_payload' => 'array',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'documentable_id',
        'xml_content',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function providerMessages(): HasMany
    {
        return $this->hasMany(FiscalProviderMessage::class, 'fiscal_document_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(FiscalDocumentAttempt::class, 'fiscal_document_id');
    }
}
