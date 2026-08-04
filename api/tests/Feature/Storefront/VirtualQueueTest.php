<?php

namespace Tests\Feature\Storefront;

use App\Console\Commands\AdmitVirtualQueueEntriesCommand;
use App\Models\Storefront\VirtualQueueEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\Feature\Storefront\Concerns\CreatesStorefrontFixtures;
use Tests\TestCase;

/**
 * Fila virtual para alta demanda (roadmap Fase 7) — opt-in por evento via
 * Event::high_demand_mode. Cobre entrada na fila, admissão em lote
 * respeitando o limite configurado, isolamento por evento/tenant e o
 * bloqueio de StorefrontHoldService::createHold() até a admissão.
 */
class VirtualQueueTest extends TestCase
{
    use CreatesSaleFixtures;
    use CreatesStorefrontFixtures;
    use RefreshDatabase;

    #[Test]
    public function entering_the_queue_for_a_non_high_demand_event_returns_admitted_immediately(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createProduct($tenant->id, ['quantity_available' => 5, 'price' => 50]);
        $event = $ticketType->event;

        $response = $this->getJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/fila?session_token=sess-1");

        $response->assertStatus(200)
            ->assertJsonPath('data.high_demand_mode', false)
            ->assertJsonPath('data.status', 'admitted');
    }

    #[Test]
    public function entering_the_queue_for_a_high_demand_event_starts_as_waiting(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createProduct($tenant->id, ['quantity_available' => 5, 'price' => 50]);
        $event = $ticketType->event;
        $event->update(['high_demand_mode' => true, 'virtual_queue_admission_batch_size' => 1]);

        $response = $this->getJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/fila?session_token=sess-a");

        $response->assertStatus(200)
            ->assertJsonPath('data.high_demand_mode', true)
            ->assertJsonPath('data.status', 'waiting')
            ->assertJsonPath('data.position', 1)
            ->assertJsonPath('data.waiting_ahead', 0);
    }

    #[Test]
    public function admission_command_respects_the_configured_batch_size_and_order(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createProduct($tenant->id, ['quantity_available' => 5, 'price' => 50]);
        $event = $ticketType->event;
        $event->update(['high_demand_mode' => true, 'virtual_queue_admission_batch_size' => 2]);

        $this->getJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/fila?session_token=sess-1")->assertStatus(200);
        $this->getJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/fila?session_token=sess-2")->assertStatus(200);
        $this->getJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/fila?session_token=sess-3")->assertStatus(200);

        $this->artisan(AdmitVirtualQueueEntriesCommand::class)->assertExitCode(0);

        $admitted = VirtualQueueEntry::where('event_id', $event->id)
            ->where('status', VirtualQueueEntry::STATUS_ADMITTED)
            ->orderBy('position')
            ->pluck('session_token')
            ->all();

        $this->assertSame(['sess-1', 'sess-2'], $admitted);

        $stillWaiting = VirtualQueueEntry::where('event_id', $event->id)
            ->where('session_token', 'sess-3')
            ->value('status');

        $this->assertSame(VirtualQueueEntry::STATUS_WAITING, $stillWaiting);
    }

    #[Test]
    public function admission_is_isolated_per_event_and_tenant(): void
    {
        $tenantA = $this->createTenantWithStorefrontPlan(true);
        $ticketTypeA = $this->createProduct($tenantA->id, ['quantity_available' => 5, 'price' => 50]);
        $eventA = $ticketTypeA->event;
        $eventA->update(['high_demand_mode' => true, 'virtual_queue_admission_batch_size' => 5]);

        $tenantB = $this->createTenantWithStorefrontPlan(true);
        $ticketTypeB = $this->createProduct($tenantB->id, ['quantity_available' => 5, 'price' => 50]);
        $eventB = $ticketTypeB->event;
        $eventB->update(['high_demand_mode' => true, 'virtual_queue_admission_batch_size' => 5]);

        $this->getJson("/api/v1/loja/{$tenantA->slug}/eventos/{$eventA->slug}/fila?session_token=sess-a1")->assertStatus(200);
        $this->getJson("/api/v1/loja/{$tenantB->slug}/eventos/{$eventB->slug}/fila?session_token=sess-b1")->assertStatus(200);

        $this->artisan(AdmitVirtualQueueEntriesCommand::class)->assertExitCode(0);

        $this->assertSame(
            VirtualQueueEntry::STATUS_ADMITTED,
            VirtualQueueEntry::where('event_id', $eventA->id)->where('session_token', 'sess-a1')->value('status')
        );
        $this->assertSame(
            VirtualQueueEntry::STATUS_ADMITTED,
            VirtualQueueEntry::where('event_id', $eventB->id)->where('session_token', 'sess-b1')->value('status')
        );
    }

    #[Test]
    public function creating_a_hold_is_blocked_until_the_session_is_admitted(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createProduct($tenant->id, ['quantity_available' => 5, 'price' => 50]);
        $event = $ticketType->event;
        $event->update(['high_demand_mode' => true, 'virtual_queue_admission_batch_size' => 50]);

        $sessionToken = 'sess-'.Str::random(10);

        $blocked = $this->postJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/holds", [
            'session_token' => $sessionToken,
            'items' => [
                ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 1],
            ],
        ]);

        $blocked->assertStatus(403);

        $this->getJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/fila?session_token={$sessionToken}")->assertStatus(200);
        $this->artisan(AdmitVirtualQueueEntriesCommand::class)->assertExitCode(0);

        $this->assertSame(
            VirtualQueueEntry::STATUS_ADMITTED,
            VirtualQueueEntry::where('event_id', $event->id)->where('session_token', $sessionToken)->value('status')
        );

        $allowed = $this->postJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/holds", [
            'session_token' => $sessionToken,
            'items' => [
                ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 1],
            ],
        ]);

        $allowed->assertStatus(201);
    }
}
