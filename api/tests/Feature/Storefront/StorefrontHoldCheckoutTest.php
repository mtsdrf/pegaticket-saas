<?php

namespace Tests\Feature\Storefront;

use App\Models\Event\TicketBatch;
use App\Models\Event\TicketType;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Inventory\InventoryHold;
use App\Models\Inventory\InventoryHoldItem;
use App\Models\Sale\Sale;
use App\Models\Ticket\Ticket;
use App\Models\Tenant\Tenant;
use App\Services\Auth\CustomerJWTService;
use App\Services\Ticket\TicketIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\Feature\Storefront\Concerns\CreatesStorefrontFixtures;
use Tests\TestCase;

/**
 * Fluxo de compra de ingresso ponta a ponta (spec 5.9 Carrinho/reserva,
 * 5.10 Checkout) — hold reduz disponibilidade, hold expirado libera
 * automaticamente, checkout confirmado consome o hold e gera Sale+
 * SaleItems, checkout com hold expirado é rejeitado, e o pagamento
 * confirmado (mecanismo existente SalePaid -> TicketIssuanceService) emite
 * os Tickets já usando os participantes informados no checkout.
 */
class StorefrontHoldCheckoutTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSaleFixtures;
    use CreatesStorefrontFixtures;

    private function authenticatedCustomer(string $email = 'comprador@test.com'): array
    {
        $customer = FinalCustomer::create(['email' => $email]);
        $token = app(CustomerJWTService::class)->issueAccessToken($customer);

        return [$customer, $token];
    }

    private function createTicketType(Tenant $tenant, array $overrides = []): TicketType
    {
        return $this->createProduct($tenant->id, array_merge([
            'quantity_available' => 5,
            'price' => 50,
        ], $overrides));
    }

    private function createBatch(TicketType $ticketType, array $overrides = []): TicketBatch
    {
        return TicketBatch::create(array_merge([
            'tenant_id' => $ticketType->tenant_id,
            'ticket_type_id' => $ticketType->id,
            'name' => 'Lote ' . Str::random(6),
            'price' => 50,
            'quantity' => 10,
            'quantity_sold' => 0,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'priority' => 1,
            'auto_advance' => true,
            'status' => 'ativo',
        ], $overrides));
    }

    #[Test]
    public function creating_a_hold_reduces_the_effectively_available_quantity(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createTicketType($tenant, ['quantity_available' => 3]);
        $event = $ticketType->event;

        $before = $this->getJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/disponibilidade")
            ->assertStatus(200)
            ->json('data');

        $this->assertSame(3, $before['ticket_types'][0]['available_quantity']);

        $this->postJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/holds", [
            'session_token' => 'sess-' . Str::random(10),
            'items' => [
                ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 2],
            ],
        ])->assertStatus(201);

        $after = $this->getJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/disponibilidade")
            ->assertStatus(200)
            ->json('data');

        $this->assertSame(1, $after['ticket_types'][0]['available_quantity']);
    }

    #[Test]
    public function an_expired_hold_no_longer_reserves_quantity(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createTicketType($tenant, ['quantity_available' => 2]);
        $event = $ticketType->event;

        $holdUuid = $this->postJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/holds", [
            'session_token' => 'sess-' . Str::random(10),
            'items' => [
                ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 2],
            ],
        ])->assertStatus(201)->json('data.uuid');

        // Disponibilidade zerada enquanto o hold está ativo.
        $reserved = $this->getJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/disponibilidade")
            ->json('data');
        $this->assertSame(0, $reserved['ticket_types'][0]['available_quantity']);

        // Simula expiração (sem depender de job/cron — cálculo é feito na
        // hora da consulta, ver StorefrontHoldService::expireHolds()).
        InventoryHold::where('uuid', $holdUuid)->update(['expires_at' => now()->subMinute()]);

        $afterExpiry = $this->getJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/disponibilidade")
            ->json('data');
        $this->assertSame(2, $afterExpiry['ticket_types'][0]['available_quantity']);

        $this->assertSame(InventoryHold::STATUS_EXPIRED, InventoryHold::where('uuid', $holdUuid)->value('status'));
    }

    #[Test]
    public function availability_advances_to_the_next_batch_when_the_current_one_is_sold_out_and_auto_advance_is_enabled(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createTicketType($tenant, ['quantity_available' => 20, 'price' => 120]);
        $event = $ticketType->event;

        $this->createBatch($ticketType, [
            'name' => 'Primeiro lote',
            'price' => 120,
            'quantity' => 2,
            'quantity_sold' => 2,
            'priority' => 1,
            'auto_advance' => true,
        ]);

        $nextBatch = $this->createBatch($ticketType, [
            'name' => 'Segundo lote',
            'price' => 150,
            'quantity' => 5,
            'quantity_sold' => 1,
            'priority' => 2,
        ]);

        $availability = $this->getJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/disponibilidade")
            ->assertStatus(200)
            ->json('data.ticket_types.0');

        $this->assertEquals(150.0, $availability['effective_price']);
        $this->assertSame(4, $availability['available_quantity']);
        $this->assertSame($nextBatch->uuid, $availability['active_batch']['uuid']);
        $this->assertSame('Segundo lote', $availability['active_batch']['name']);
    }

    #[Test]
    public function hold_keeps_the_current_batch_when_auto_advance_is_disabled(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createTicketType($tenant, ['quantity_available' => 20, 'price' => 120]);
        $event = $ticketType->event;

        $currentBatch = $this->createBatch($ticketType, [
            'name' => 'Primeiro lote',
            'price' => 120,
            'quantity' => 2,
            'quantity_sold' => 2,
            'priority' => 1,
            'auto_advance' => false,
        ]);

        $this->createBatch($ticketType, [
            'name' => 'Segundo lote',
            'price' => 150,
            'quantity' => 5,
            'quantity_sold' => 0,
            'priority' => 2,
            'auto_advance' => true,
        ]);

        $availability = $this->getJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/disponibilidade")
            ->assertStatus(200)
            ->json('data.ticket_types.0');

        $this->assertEquals(120.0, $availability['effective_price']);
        $this->assertSame(0, $availability['available_quantity']);
        $this->assertSame($currentBatch->uuid, $availability['active_batch']['uuid']);

        $this->postJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/holds", [
            'session_token' => 'sess-' . Str::random(10),
            'items' => [
                ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 1],
            ],
        ])->assertStatus(422);
    }

    #[Test]
    public function hold_uses_the_next_batch_price_when_auto_advance_moves_to_the_next_batch(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createTicketType($tenant, ['quantity_available' => 20, 'price' => 120]);
        $event = $ticketType->event;

        $this->createBatch($ticketType, [
            'name' => 'Primeiro lote',
            'price' => 120,
            'quantity' => 2,
            'quantity_sold' => 2,
            'priority' => 1,
            'auto_advance' => true,
        ]);

        $nextBatch = $this->createBatch($ticketType, [
            'name' => 'Segundo lote',
            'price' => 150,
            'quantity' => 5,
            'quantity_sold' => 0,
            'priority' => 2,
        ]);

        $holdUuid = $this->postJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/holds", [
            'session_token' => 'sess-' . Str::random(10),
            'items' => [
                ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 2],
            ],
        ])->assertStatus(201)->json('data.uuid');

        $holdItem = InventoryHoldItem::query()
            ->whereHas('hold', fn($query) => $query->where('uuid', $holdUuid))
            ->firstOrFail();

        $this->assertSame($nextBatch->id, $holdItem->ticket_batch_id);
        $this->assertSame('150.00', number_format((float) $holdItem->unit_price, 2, '.', ''));
    }

    #[Test]
    public function checkout_with_an_expired_hold_is_rejected(): void
    {
        [$customer, $token] = $this->authenticatedCustomer();
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createTicketType($tenant);
        $event = $ticketType->event;
        $sessionToken = 'sess-' . Str::random(10);

        $hold = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/holds", [
                'session_token' => $sessionToken,
                'items' => [
                    ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 1],
                ],
            ])->assertStatus(201)->json('data');

        InventoryHold::where('uuid', $hold['uuid'])->update(['expires_at' => now()->subMinute()]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/v1/loja/{$tenant->slug}/checkout", [
                'hold_uuid' => $hold['uuid'],
                'session_token' => $sessionToken,
                'items' => [
                    ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 1],
                ],
                'client_name' => 'Comprador',
                'client_last_name' => 'Teste',
                'client_phone' => '11999999999',
            ]);

        $response->assertStatus(422);
        $this->assertSame(0, Sale::where('tenant_id', $tenant->id)->count());
    }

    #[Test]
    public function confirmed_checkout_consumes_the_hold_and_creates_a_sale_matching_it(): void
    {
        [$customer, $token] = $this->authenticatedCustomer();
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createTicketType($tenant, ['price' => 75]);
        $event = $ticketType->event;
        $sessionToken = 'sess-' . Str::random(10);

        $hold = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/holds", [
                'session_token' => $sessionToken,
                'items' => [
                    ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 2],
                ],
            ])->assertStatus(201)->json('data');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/v1/loja/{$tenant->slug}/checkout", [
                'hold_uuid' => $hold['uuid'],
                'session_token' => $sessionToken,
                'items' => [
                    [
                        'ticket_type_uuid' => $ticketType->uuid,
                        'quantity' => 2,
                        'participants' => [
                            ['name' => 'Fulano', 'document' => '11122233344'],
                            ['name' => 'Ciclana', 'document' => '55566677788'],
                        ],
                    ],
                ],
                'client_name' => 'Comprador',
                'client_last_name' => 'Teste',
                'client_phone' => '11999999999',
            ]);

        $response->assertStatus(201);
        $saleUuid = $response->json('data.sale.uuid');

        $order = Sale::where('uuid', $saleUuid)->firstOrFail();
        $this->assertSame($tenant->id, $order->tenant_id);
        $this->assertSame('pending_approval', $order->status);
        $this->assertSame('150.00', number_format((float) $order->total_amount, 2, '.', ''));

        $item = $order->items()->firstOrFail();
        $this->assertSame(2, (int) $item->quantity);
        $this->assertSame($ticketType->id, $item->ticket_type_id);
        $this->assertCount(2, $item->attendee_data);
        $this->assertSame('Fulano', $item->attendee_data[0]['name']);

        $this->assertSame(
            InventoryHold::STATUS_CONVERTED,
            InventoryHold::where('uuid', $hold['uuid'])->value('status')
        );
        $this->assertSame($order->id, InventoryHold::where('uuid', $hold['uuid'])->value('converted_sale_id'));
    }

    #[Test]
    public function paying_a_checkout_generated_sale_issues_tickets_with_the_informed_participants(): void
    {
        [$customer, $token] = $this->authenticatedCustomer();
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createTicketType($tenant, ['quantity_available' => 3, 'price' => 30]);
        $event = $ticketType->event;
        $sessionToken = 'sess-' . Str::random(10);

        $hold = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/holds", [
                'session_token' => $sessionToken,
                'items' => [
                    ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 1],
                ],
            ])->assertStatus(201)->json('data');

        $checkout = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/v1/loja/{$tenant->slug}/checkout", [
                'hold_uuid' => $hold['uuid'],
                'session_token' => $sessionToken,
                'items' => [
                    [
                        'ticket_type_uuid' => $ticketType->uuid,
                        'quantity' => 1,
                        'participants' => [['name' => 'Beltrano', 'document' => '99988877766']],
                    ],
                ],
                'client_name' => 'Comprador',
                'client_last_name' => 'Teste',
                'client_phone' => '11999999999',
            ])->assertStatus(201)->json('data.sale');

        $order = Sale::where('uuid', $checkout['uuid'])->firstOrFail();

        // Reaproveita o mecanismo já existente (SalePaid ->
        // IssueTicketsOnSalePaid -> TicketIssuanceService), sem criar novo
        // fluxo de pagamento — mesma simulação usada em TicketIssuanceTest.
        app(TicketIssuanceService::class)->issueForSale($order, null);

        $ticket = Ticket::whereHas('saleItem', fn($q) => $q->where('sale_id', $order->id))->firstOrFail();
        $this->assertSame('Beltrano', $ticket->attendee_name);
        $this->assertSame('99988877766', $ticket->attendee_document);
    }
}
