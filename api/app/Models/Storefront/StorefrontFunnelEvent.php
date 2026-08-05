<?php

namespace App\Models\Storefront;

use App\Models\Event\Event;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Evento anônimo de funil de conversão (roadmap A2) — mesmo desvio de
 * `CartEvent`: extends `Model` puro (sem soft delete, sem
 * created_by/updated_by, ator é visitante anônimo). Sem `UPDATED_AT`, é
 * append-only.
 */
class StorefrontFunnelEvent extends Model
{
    const UPDATED_AT = null;

    protected $table = 'storefront_funnel_events';

    protected $fillable = [
        'tenant_id',
        'event_id',
        'session_id',
        'step',
    ];

    public const STEP_EVENT_VIEWED = 'event_viewed';

    public const STEP_TICKET_SELECTION_STARTED = 'ticket_selection_started';

    public const STEP_HOLD_CREATED = 'hold_created';

    public const STEP_CHECKOUT_STARTED = 'checkout_started';

    public const STEP_PAYMENT_CONFIRMED = 'payment_confirmed';

    public const STEPS = [
        self::STEP_EVENT_VIEWED,
        self::STEP_TICKET_SELECTION_STARTED,
        self::STEP_HOLD_CREATED,
        self::STEP_CHECKOUT_STARTED,
        self::STEP_PAYMENT_CONFIRMED,
    ];

    protected static function booted(): void
    {
        static::creating(function (StorefrontFunnelEvent $event) {
            if (empty($event->uuid)) {
                $event->uuid = (string) Str::uuid();
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
