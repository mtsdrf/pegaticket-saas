<?php

namespace Tests\Feature\Storefront;

use App\Models\Event\TicketType;
use App\Models\Inventory\InventoryHold;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\Feature\Storefront\Concerns\CreatesStorefrontFixtures;
use Tests\TestCase;

/**
 * Varredura proativa de holds vencidos (ExpireInventoryHoldsCommand) — rede
 * de segurança sobre o gap do "expire on read" (StorefrontHoldService só
 * expira holds do tenant+evento consultado). Ver docblock do Command.
 */
class ExpireInventoryHoldsCommandTest extends TestCase
{
    use CreatesSaleFixtures;
    use CreatesStorefrontFixtures;
    use RefreshDatabase;

    private function createTicketType(Tenant $tenant, array $overrides = []): TicketType
    {
        return $this->createProduct($tenant->id, array_merge([
            'quantity_available' => 5,
            'price' => 50,
        ], $overrides));
    }

    #[Test]
    public function expires_reserved_holds_past_their_deadline_across_tenants(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createTicketType($tenant, ['quantity_available' => 3]);
        $event = $ticketType->event;

        $expiredHoldUuid = $this->postJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/holds", [
            'session_token' => 'sess-'.Str::random(10),
            'items' => [['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 1]],
        ])->assertStatus(201)->json('data.uuid');

        $activeHoldUuid = $this->postJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/holds", [
            'session_token' => 'sess-'.Str::random(10),
            'items' => [['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 1]],
        ])->assertStatus(201)->json('data.uuid');

        InventoryHold::where('uuid', $expiredHoldUuid)->update(['expires_at' => now()->subMinute()]);

        $this->artisan('inventory:expire-holds')->assertExitCode(0);

        $this->assertSame(InventoryHold::STATUS_EXPIRED, InventoryHold::where('uuid', $expiredHoldUuid)->value('status'));
        $this->assertSame(InventoryHold::STATUS_RESERVED, InventoryHold::where('uuid', $activeHoldUuid)->value('status'));
    }
}
