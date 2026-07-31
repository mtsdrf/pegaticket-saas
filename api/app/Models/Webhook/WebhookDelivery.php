<?php

namespace App\Models\Webhook;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\HasUuid;

/**
 * Log técnico de entrega (1 linha por tentativa de POST) — não estende
 * BaseModel de propósito: não tem created_by/updated_by/soft delete, é
 * gerado por Job, não por ação de usuário staff (mesmo raciocínio de
 * AuditLog, que também é `Model` puro + HasUuid).
 */
class WebhookDelivery extends Model
{
    use HasUuid;

    protected $table = 'webhook_deliveries';

    protected $guarded = ['id'];

    protected $casts = [
        'payload' => 'array',
        'success' => 'boolean',
        'attempted_at' => 'datetime',
    ];

    public function webhookSubscription(): BelongsTo
    {
        return $this->belongsTo(WebhookSubscription::class);
    }
}
