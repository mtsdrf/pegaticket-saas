<?php

namespace Tests\Feature\Orders\Concerns;

use App\Models\Event\Event;
use App\Models\Event\EventCategory;
use App\Models\Event\TicketType;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\FinalCustomer\FinalCustomerTenantLink;
use App\Models\Location\Bairro;
use App\Models\Location\Cidade;
use App\Models\Location\Endereco;
use App\Models\Location\Estado;
use App\Models\Stock\StockBalance;
use App\Models\Stock\StockLocation;
use Illuminate\Support\Str;
use Tests\Concerns\GeneratesUniqueUf;

/**
 * Extraído de OrderTest.php (2026-07-12) pra ser reaproveitado também por
 * OrderInstallmentTest.php, sem duplicar os ~80 linhas de fixture de
 * Client/Product/StockLocation — mesma ideia de SetsUpTenantScopedUser,
 * só que específico da árvore de fixtures de Pedido.
 *
 * Migrado de Product para TicketType (roadmap PegaTicket seção 4A,
 * 2026-07-31) — createProduct() mantido como NOME (chamado por dezenas de
 * testes) mas agora cria Event/EventCategory/TicketType por baixo, e
 * retorna um TicketType.
 */
trait CreatesOrderFixtures
{
    use GeneratesUniqueUf;

    protected function createLocation(int $tenantId, array $overrides = []): StockLocation
    {
        return StockLocation::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'name' => 'Location ' . Str::random(6),
            'is_active' => true,
        ], $overrides));
    }

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
     * (FinalCustomer) + o vínculo por-tenant confirmado (FinalCustomerTenantLink,
     * com o endereço) — o retorno continua sendo o que os testes usam pra
     * montar o payload de criação de pedido (agora via `final_customer_uuid`
     * em vez de `client_uuid`).
     */
    protected function createClient(int $tenantId): FinalCustomer
    {
        $estado = Estado::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Estado ' . Str::random(6),
            'uf' => $this->nextUf(),
        ]);

        $cidade = Cidade::create([
            'uuid' => (string) Str::uuid(),
            'estado_id' => $estado->id,
            'name' => 'Cidade ' . Str::random(6),
        ]);

        $bairro = Bairro::create([
            'uuid' => (string) Str::uuid(),
            'cidade_id' => $cidade->id,
            'name' => 'Bairro ' . Str::random(6),
        ]);

        $endereco = Endereco::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'estado_id' => $estado->id,
            'cidade_id' => $cidade->id,
            'bairro_id' => $bairro->id,
            'logradouro' => 'Rua Teste, 123',
            'is_active' => true,
        ]);

        $finalCustomer = FinalCustomer::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Client ' . Str::random(6),
            'email' => 'client-' . Str::random(10) . '@test.com',
        ]);

        FinalCustomerTenantLink::create([
            'uuid' => (string) Str::uuid(),
            'final_customer_id' => $finalCustomer->id,
            'tenant_id' => $tenantId,
            'endereco_id' => $endereco->id,
            'is_trusted' => true,
            'is_active' => true,
            'confirmed_at' => now(),
        ]);

        return $finalCustomer;
    }

    protected function stockEntry(int $tenantId, TicketType $ticketType, StockLocation $location, int $quantity): void
    {
        StockBalance::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'ticket_type_id' => $ticketType->id,
                'location_id' => $location->id,
            ],
            [
                'quantity_on_hand' => $quantity,
                'quantity_reserved' => 0,
                'quantity_blocked' => 0,
            ]
        );
    }
}
