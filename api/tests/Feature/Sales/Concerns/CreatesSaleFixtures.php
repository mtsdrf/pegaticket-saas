<?php

namespace Tests\Feature\Sales\Concerns;

use App\Models\Event\Event;
use App\Models\Event\EventCategory;
use App\Models\Event\TicketType;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\FinalCustomer\FinalCustomerTenantLink;
use Illuminate\Support\Str;

/**
 * Extraído de SaleTest.php (2026-07-12) pra ser reaproveitado também por
 * SaleInstallmentTest.php, sem duplicar os ~60 linhas de fixture de
 * Client/Product — mesma ideia de SetsUpTenantScopedUser, só que
 * específico da árvore de fixtures de Pedido.
 *
 * Migrado de Product para TicketType (roadmap PegaTicket seção 4A,
 * 2026-07-31) — createProduct() mantido como NOME (chamado por dezenas de
 * testes) mas agora cria Event/EventCategory/TicketType por baixo, e
 * retorna um TicketType. Estoque (StockLocation/StockBalance) removido do
 * domínio (roadmap seção 7/8, 2026-08-01) — controle de quantidade vendida
 * passa a ser só TicketType.quantity_available/TicketBatch.quantity_sold.
 */
trait CreatesSaleFixtures
{
    protected function createProduct(int $tenantId, array $overrides = []): TicketType
    {
        $category = EventCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'name' => 'Category ' . Str::random(6),
            'is_active' => true,
        ]);

        $event = Event::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'event_category_id' => $category->id,
            'name' => 'Event ' . Str::random(6),
            'slug' => 'event-' . Str::random(10),
            'type' => 'ingresso',
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(4),
            'visibility' => 'public',
            'status' => 'publicado',
        ]);

        return TicketType::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'event_id' => $event->id,
            'name' => 'Ticket Type ' . Str::random(6),
            'price' => 10,
            'status' => 'ativo',
            'unit' => 'un',
            'min_per_order' => 1,
        ], $overrides));
    }

    /**
     * FinalCustomer absorveu Client (2026-07-31): cria a identidade global
     * (FinalCustomer) + o vínculo por-tenant confirmado (FinalCustomerTenantLink)
     * — o retorno continua sendo o que os testes usam pra
     * montar o payload de criação de pedido (agora via `final_customer_uuid`
     * em vez de `client_uuid`).
     */
    protected function createClient(int $tenantId): FinalCustomer
    {
        $finalCustomer = FinalCustomer::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Client ' . Str::random(6),
            'email' => 'client-' . Str::random(10) . '@test.com',
        ]);

        FinalCustomerTenantLink::create([
            'uuid' => (string) Str::uuid(),
            'final_customer_id' => $finalCustomer->id,
            'tenant_id' => $tenantId,
            'cpf_cnpj' => '12345678909',
            'phone_primary' => '11999999999',
            'is_trusted' => true,
            'is_active' => true,
            'confirmed_at' => now(),
        ]);

        return $finalCustomer;
    }
}
