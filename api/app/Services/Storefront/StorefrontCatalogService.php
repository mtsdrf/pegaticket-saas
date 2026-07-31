<?php

namespace App\Services\Storefront;

use App\Models\Event\Event;
use App\Models\Event\EventCategory;
use App\Models\Storefront\EventFavorite;
use App\Models\Tenant\Tenant;
use App\Services\Permission\PermissionService;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Catálogo público da loja (migrado de comércio/delivery para o domínio de
 * ingressos — roadmap PegaTicket seção 2.4/4A, 2026-07-31). Sem
 * tenant/perm — o "escopo" aqui é resolvido por slug, não por JWT/
 * membership. Toda resolução de tenant passa por findTenantBySlug(), que
 * já embute o gate de plano.
 *
 * SIMPLIFICAÇÃO DOCUMENTADA (roadmap seção 6, item 3): a versão anterior
 * (StorefrontCatalogService de Product) tinha selos (new/best_selling/
 * low_stock), promoção por item e favoritos por item — toda essa
 * complexidade foi descartada nesta rodada. O catálogo público agora é
 * apenas: lista de eventos publicados/públicos (paginada, com filtro por
 * categoria/tipo/busca) + detalhe do evento com ticket_types/event_products
 * aninhados. Badges, promoção por ticket type e ordenação por "mais
 * vendido" ficam como pendência explícita para quando o motor de
 * lotes/checkout novo (roadmap seção 2.8) for construído.
 */
class StorefrontCatalogService
{
    public function __construct(
        private PermissionService $permissionService,
    ) {
    }

    public function findTenantBySlug(string $slug): Tenant
    {
        $tenant = $this->findPublicTenantBySlug($slug);

        if (!$this->permissionService->tenantPlanAllowsFunctionality($tenant->id, 'storefront')) {
            abort(404);
        }

        return $tenant;
    }

    public function findPublicTenantBySlug(string $slug): Tenant
    {
        return Tenant::where('slug', $slug)
            ->whereNull('deleted_at')
            ->firstOrFail();
    }

    /**
     * Eventos publicados e públicos do tenant — unidade do catálogo
     * público (substitui produtos). Nunca expõe rascunho/agendado/oculto/
     * privado/exclusivo nesta rodada (visibility=public AND status=
     * publicado, incondicional).
     */
    public function paginateEvents(
        int $tenantId,
        array $filters = [],
        int $perPage = 15,
        ?int $finalCustomerId = null
    ): LengthAwarePaginator {
        $query = Event::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('status', 'publicado')
            ->where('visibility', 'public')
            ->with('category');

        if (!empty($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        if (!empty($filters['event_category_uuid'])) {
            $query->whereHas('category', fn($q) => $q->where('uuid', $filters['event_category_uuid']));
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $page = $query->orderBy('starts_at')->paginate($perPage);

        if ($finalCustomerId !== null) {
            $eventIds = $page->getCollection()->pluck('id')->all();

            $favoritedIds = EventFavorite::where('final_customer_id', $finalCustomerId)
                ->whereIn('event_id', $eventIds)
                ->pluck('event_id')
                ->all();

            $page->getCollection()->each(function (Event $event) use ($favoritedIds) {
                $event->setAttribute('is_favorited', in_array($event->id, $favoritedIds, true));
            });
        }

        return $page;
    }

    /**
     * Detalhe público de um evento publicado — com ticket_types/
     * event_products ativos aninhados (mesmo espírito de
     * Event::DETAIL_RELATIONS, mas restrito a itens vendáveis publicamente).
     */
    public function findPublicEvent(int $tenantId, string $eventSlug): Event
    {
        return Event::where('tenant_id', $tenantId)
            ->where('slug', $eventSlug)
            ->where('status', 'publicado')
            ->where('visibility', 'public')
            ->whereNull('deleted_at')
            ->with([
                'category',
                'ticketTypes' => fn($q) => $q->where('status', 'ativo')->whereNull('deleted_at'),
                'eventProducts' => fn($q) => $q->where('status', 'ativo')->whereNull('deleted_at'),
            ])
            ->firstOrFail();
    }

    /**
     * Categorias com pelo menos 1 evento publicado/público — nunca mostrar
     * uma aba de categoria vazia. Ordenado por `priority` e depois nome.
     */
    public function listAvailableCategories(int $tenantId): \Illuminate\Support\Collection
    {
        return EventCategory::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('events')
                    ->whereColumn('events.event_category_id', 'event_categories.id')
                    ->where('events.status', 'publicado')
                    ->where('events.visibility', 'public')
                    ->whereNull('events.deleted_at');
            })
            ->orderBy('priority')
            ->orderBy('name')
            ->get(['uuid', 'name']);
    }
}
