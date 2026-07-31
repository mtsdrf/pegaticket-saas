<?php

namespace App\Models\Webhook;

use App\Models\BaseModel;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * `secret` reversível (precisa ser lido de volta pra assinar HMAC a cada
 * delivery) — cast `encrypted`, nunca hash. Ver
 * .claude/memory/security-standards.md (regra de segredo reversível vs
 * irreversível) e AuditLog::$sensitive (denylist por nome exato de coluna).
 */
class WebhookSubscription extends BaseModel
{
    protected $table = 'webhook_subscriptions';

    protected $fillable = [
        'tenant_id',
        'url',
        'event_types',
        'secret',
        'is_active',
    ];

    protected $casts = [
        'event_types' => 'array',
        'secret' => 'encrypted',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'id',
        'secret',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function subscribesTo(string $eventType): bool
    {
        return in_array($eventType, $this->event_types ?? [], true);
    }
}
