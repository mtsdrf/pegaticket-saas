<?php

namespace App\Models\Storefront;

use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Evento anônimo de telemetria de carrinho (roadmap A3.14). Extends `Model`
 * puro, não `BaseModel` — mesmo desvio documentado de `WebhookEvent`: sem
 * soft delete (dado analítico append-only) e sem created_by/updated_by
 * (quem gera é um visitante anônimo da loja pública, não staff
 * autenticado). `uuid` é preenchido manualmente no `creating` (BaseModel
 * normalmente faz isso via HasUuid, mas esta model não usa a trait).
 */
class CartEvent extends Model
{
    protected $table = 'cart_events';

    protected $fillable = [
        'tenant_id',
        'session_id',
        'event_type',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (CartEvent $event) {
            if (empty($event->uuid)) {
                $event->uuid = (string) Str::uuid();
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
