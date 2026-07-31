<?php

namespace App\Services\Storefront;

use App\Models\Event\Event;
use App\Models\Storefront\EventFavorite;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Favoritos de EVENTO pelo cliente final (migrado de ProductFavoriteService
 * — roadmap PegaTicket seção 4A). Sem Repository dedicado — mesma decisão
 * já usada para CouponRedemption/FinalCustomerOtp: tabela auxiliar
 * simples, sem BaseModel/soft delete, Service acessa o Model direto.
 */
class EventFavoriteService
{
    /**
     * Idempotente: favorito existente é removido (unfavorite), inexistente
     * é criado (favorite). 404 se o uuid não corresponder a um evento
     * ativo (Event usa soft delete via BaseModel).
     */
    public function toggle(int $finalCustomerId, string $eventUuid): array
    {
        $event = Event::where('uuid', $eventUuid)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $favorite = EventFavorite::where('final_customer_id', $finalCustomerId)
            ->where('event_id', $event->id)
            ->first();

        if ($favorite) {
            $favorite->delete();

            return ['favorited' => false];
        }

        // Dois toques concorrentes no coração podem ambos passar pelo
        // `first()` acima sem achar nada e tentar criar — a unique composta
        // (final_customer_id, event_id) protege o dado, mas sem esse catch
        // o segundo vira 500 cru em vez de responder favorited=true
        // normalmente (mesmo padrão de FinalCustomerTenantLink/Fase 1).
        try {
            EventFavorite::create([
                'final_customer_id' => $finalCustomerId,
                'event_id' => $event->id,
            ]);
        } catch (QueryException $e) {
            if ((int) $e->getCode() !== 23000) {
                throw $e;
            }
        }

        return ['favorited' => true];
    }

    /**
     * Eventos favoritados do cliente, mais recentes primeiro.
     */
    public function list(int $finalCustomerId, int $perPage = 15): LengthAwarePaginator
    {
        $page = Event::query()
            ->join('event_favorites', 'event_favorites.event_id', '=', 'events.id')
            ->where('event_favorites.final_customer_id', $finalCustomerId)
            ->whereNull('events.deleted_at')
            ->with(['category'])
            ->select('events.*')
            ->orderByDesc('event_favorites.created_at')
            ->paginate($perPage);

        $page->getCollection()->each(
            fn(Event $event) => $event->setAttribute('is_favorited', true)
        );

        return $page;
    }
}
