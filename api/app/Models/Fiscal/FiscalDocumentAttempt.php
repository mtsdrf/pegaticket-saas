<?php

namespace App\Models\Fiscal;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class FiscalDocumentAttempt extends Model
{
    use HasUuid;

    protected $table = 'fiscal_document_attempts';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'fiscal_document_id',
        'operation_type',
        'status',
        'provider',
        'provider_reference',
        'idempotency_key',
        'payload_hash',
        'response_hash',
        'attempt_number',
        'payload',
        'response_payload',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'response_payload' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'fiscal_document_id',
    ];

    public function fiscalDocument()
    {
        return $this->belongsTo(FiscalDocument::class, 'fiscal_document_id');
    }
}
