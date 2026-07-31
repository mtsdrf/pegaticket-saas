<?php

namespace App\Models\Storefront;

use App\Models\Event\Event;
use App\Models\FinalCustomer\FinalCustomer;
use Illuminate\Database\Eloquent\Model;

/**
 * Favorito de EVENTO pelo cliente final (migrado de ProductFavorite —
 * roadmap PegaTicket seção 4A, 2026-07-31: favoritar passou a ser por
 * Evento, não por ticket type/adicional — menos rework e é a unidade
 * natural pro comprador). Sem BaseModel de propósito (mesmo desvio
 * documentado de FinalCustomerTenantLink/CouponRedemption): sempre
 * criado/removido pelo próprio cliente final via toggle, nunca por staff,
 * sem soft delete/created_by. Só created_at faz sentido (toggle é
 * create/delete, não update) — UPDATED_AT desligado.
 */
class EventFavorite extends Model
{
    public $timestamps = true;

    const UPDATED_AT = null;

    protected $table = 'event_favorites';

    protected $fillable = [
        'final_customer_id',
        'event_id',
    ];

    public function finalCustomer()
    {
        return $this->belongsTo(FinalCustomer::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
