<?php

namespace Tests\Feature\Storefront;

use App\Models\Event\Event;
use App\Models\Event\EventCategory;
use App\Models\Event\TicketType;
use App\Models\Inventory\InventoryHoldItem;
use App\Models\Venue\Seat;
use App\Models\Venue\Venue;
use App\Models\Venue\VenueMapVersion;
use App\Services\Storefront\SeatAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Storefront\Concerns\CreatesStorefrontFixtures;
use Tests\TestCase;

/**
 * "Melhor assento disponível" / "assentos contíguos" (roadmap Fase 3) —
 * SeatAllocationService isolado (unit) + fluxo de hold via loja pública
 * escolhendo `sector_name` em vez de `seat_uuid` (feature).
 */
class SeatAutoSelectionTest extends TestCase
{
    use CreatesStorefrontFixtures;
    use RefreshDatabase;

    private function makeSeat(VenueMapVersion $mapVersion, string $sector, float $x, float $y, array $overrides = []): Seat
    {
        return Seat::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $mapVersion->tenant_id,
            'venue_map_version_id' => $mapVersion->id,
            'sector_name' => $sector,
            'label' => $sector.'-'.$x,
            'kind' => 'assento',
            'capacity' => 1,
            'pos_x' => $x,
            'pos_y' => $y,
            'status' => 'disponivel',
        ], $overrides));
    }

    private function makeMapVersion(): VenueMapVersion
    {
        $tenantId = $this->createTenantWithStorefrontPlan(false)->id;

        return VenueMapVersion::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'venue_id' => Venue::create(['uuid' => (string) Str::uuid(), 'tenant_id' => $tenantId, 'name' => 'Teatro'])->id,
            'version_number' => 1,
            'is_published' => true,
        ]);
    }

    #[Test]
    public function selects_a_contiguous_block_within_the_same_row(): void
    {
        $mapVersion = $this->makeMapVersion();

        // Fileira 1: A1..A5 espaçados por 10, com A3 já ocupado (fora da lista de disponíveis).
        $seats = collect([
            $this->makeSeat($mapVersion, 'Plateia', 0, 0),
            $this->makeSeat($mapVersion, 'Plateia', 10, 0),
            $this->makeSeat($mapVersion, 'Plateia', 30, 0),
            $this->makeSeat($mapVersion, 'Plateia', 40, 0),
            $this->makeSeat($mapVersion, 'Plateia', 50, 0),
        ]);

        $selected = app(SeatAllocationService::class)->selectBest($seats, 2);

        $this->assertCount(2, $selected);
        $this->assertEqualsCanonicalizing([0.0, 10.0], $selected->map(fn (Seat $s) => (float) $s->pos_x)->all());
    }

    #[Test]
    public function falls_back_to_closest_seats_when_no_contiguous_block_fits(): void
    {
        $mapVersion = $this->makeMapVersion();

        $seats = collect([
            $this->makeSeat($mapVersion, 'Plateia', 0, 0),
            $this->makeSeat($mapVersion, 'Plateia', 100, 0),
            $this->makeSeat($mapVersion, 'Plateia', 200, 0),
        ]);

        $selected = app(SeatAllocationService::class)->selectBest($seats, 2);

        $this->assertCount(2, $selected);
    }

    #[Test]
    public function returns_empty_when_not_enough_available_seats(): void
    {
        $mapVersion = $this->makeMapVersion();

        $seats = collect([$this->makeSeat($mapVersion, 'Plateia', 0, 0)]);

        $this->assertTrue(app(SeatAllocationService::class)->selectBest($seats, 3)->isEmpty());
    }

    private function createSeatedEvent(int $tenantId): array
    {
        $venue = Venue::create(['uuid' => (string) Str::uuid(), 'tenant_id' => $tenantId, 'name' => 'Teatro']);
        $mapVersion = VenueMapVersion::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'venue_id' => $venue->id,
            'version_number' => 1,
            'is_published' => true,
        ]);

        $category = EventCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'name' => 'Category '.Str::random(6),
            'is_active' => true,
        ]);

        $event = Event::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'event_category_id' => $category->id,
            'venue_map_version_id' => $mapVersion->id,
            'name' => 'Event '.Str::random(6),
            'slug' => 'event-'.Str::random(10),
            'type' => 'assento',
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(4),
            'visibility' => 'public',
            'status' => 'publicado',
        ]);

        $ticketType = TicketType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'event_id' => $event->id,
            'name' => 'Ingresso Plateia',
            'price' => 80,
            'quantity_available' => 10,
            'status' => 'ativo',
            'unit' => 'un',
            'min_per_order' => 1,
        ]);

        foreach ([0, 10, 20] as $x) {
            $this->makeSeat($mapVersion, 'Plateia', $x, 0);
        }

        return [$event, $ticketType];
    }

    #[Test]
    public function creating_a_hold_with_sector_name_auto_selects_contiguous_seats(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        [$event, $ticketType] = $this->createSeatedEvent($tenant->id);

        $response = $this->postJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/holds", [
            'session_token' => 'sess-'.Str::random(10),
            'items' => [
                ['ticket_type_uuid' => $ticketType->uuid, 'sector_name' => 'Plateia', 'quantity' => 2],
            ],
        ])->assertStatus(201);

        $holdUuid = $response->json('data.uuid');
        $items = InventoryHoldItem::whereHas('hold', fn ($q) => $q->where('uuid', $holdUuid))->get();

        $this->assertCount(2, $items);
        $this->assertTrue($items->every(fn (InventoryHoldItem $item) => $item->seat_id !== null && $item->quantity === 1));
    }

    #[Test]
    public function rejects_the_hold_when_the_sector_does_not_have_enough_free_seats(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        [$event, $ticketType] = $this->createSeatedEvent($tenant->id);

        $this->postJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/holds", [
            'session_token' => 'sess-'.Str::random(10),
            'items' => [
                ['ticket_type_uuid' => $ticketType->uuid, 'sector_name' => 'Plateia', 'quantity' => 10],
            ],
        ])->assertStatus(422);
    }
}
