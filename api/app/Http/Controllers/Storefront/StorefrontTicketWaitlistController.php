<?php

namespace App\Http\Controllers\Storefront;

use App\DTOs\TicketTypeWaitlist\CreateTicketTypeWaitlistEntryDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\TicketTypeWaitlist\CreateTicketTypeWaitlistEntryRequest;
use App\Services\APIResponse;
use App\Services\Security\AntiBotGuardService;
use App\Services\Storefront\StorefrontCatalogService;
use App\Services\TicketTypeWaitlist\TicketTypeWaitlistService;

/**
 * POST /loja/{slug}/lista-espera — 100% público, mesmo espírito das demais
 * rotas /loja/{slug}/*: resolve o tenant pelo slug via
 * StorefrontCatalogService::findTenantBySlug() e não vaza se o e-mail já
 * estava cadastrado (ver TicketTypeWaitlistService::create()).
 */
class StorefrontTicketWaitlistController extends Controller
{
    public function __construct(
        private TicketTypeWaitlistService $service,
        private StorefrontCatalogService $catalogService,
        private AntiBotGuardService $antiBotGuard,
    ) {}

    public function store(string $slug, CreateTicketTypeWaitlistEntryRequest $request)
    {
        $this->antiBotGuard->assertHuman(
            $request->validated('website'),
            $request->validated('form_rendered_at')
        );

        $tenant = $this->catalogService->findTenantBySlug($slug);

        $dto = CreateTicketTypeWaitlistEntryDTO::fromArray($request->validated());

        $this->service->create($tenant->id, $dto);

        return APIResponse::success(
            null,
            __('messages.ticket_type_waitlist.entry_created'),
            201
        );
    }
}
