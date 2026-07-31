<?php

namespace App\Models\Fiscal;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class FiscalProviderMessage extends Model
{
    use HasUuid;

    protected $table = 'fiscal_provider_messages';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'fiscal_document_id',
        'provider',
        'provider_document_id',
        'message_type',
        'level',
        'provider_status',
        'summary',
        'payload',
        'received_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'received_at' => 'datetime',
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
