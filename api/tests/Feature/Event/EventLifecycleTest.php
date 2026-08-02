<?php

namespace Tests\Feature\Event;

use App\Models\AuditLog;
use App\Models\Event\Event;
use App\Models\Event\EventCategory;
use App\Models\Event\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class EventLifecycleTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('event-lifecycle-user@test.com');
        $this->grantPermission('events', 'create');
        $this->grantPermission('events', 'read');
        $this->grantPermission('events', 'update');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    #[Test]
    public function it_publishes_a_draft_event_when_sellable_inventory_exists(): void
    {
        $event = $this->createEvent('rascunho');
        $this->createActiveTicketType($event);

        $this->auth()
            ->postJson('/api/v1/events/' . $event->uuid . '/publish')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'publicado');

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => 'publicado',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'event_status_changed',
        ]);
    }

    #[Test]
    public function it_rejects_publish_without_active_sellable_inventory(): void
    {
        $event = $this->createEvent('rascunho');

        $this->auth()
            ->postJson('/api/v1/events/' . $event->uuid . '/publish')
            ->assertStatus(422);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => 'rascunho',
        ]);
    }

    #[Test]
    public function it_pauses_and_resumes_sales_with_valid_transitions(): void
    {
        $event = $this->createEvent('publicado');

        $this->auth()
            ->postJson('/api/v1/events/' . $event->uuid . '/pause-sales')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'vendas_pausadas');

        $this->auth()
            ->postJson('/api/v1/events/' . $event->uuid . '/resume-sales')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'publicado');
    }

    #[Test]
    public function it_closes_then_archives_an_event(): void
    {
        $event = $this->createEvent('publicado');

        $this->auth()
            ->postJson('/api/v1/events/' . $event->uuid . '/close-sales')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'encerrado');

        $this->auth()
            ->postJson('/api/v1/events/' . $event->uuid . '/archive')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'arquivado');
    }

    #[Test]
    public function it_cancels_an_event_and_blocks_invalid_archival_transition_before_cancel_or_close(): void
    {
        $event = $this->createEvent('agendado');

        $this->auth()
            ->postJson('/api/v1/events/' . $event->uuid . '/archive')
            ->assertStatus(422);

        $this->auth()
            ->postJson('/api/v1/events/' . $event->uuid . '/cancel')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelado');
    }

    private function createEvent(string $status): Event
    {
        $category = EventCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Categoria ' . Str::random(6),
            'is_active' => true,
        ]);

        return Event::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'event_category_id' => $category->id,
            'name' => 'Evento ' . Str::random(6),
            'slug' => 'evento-' . Str::random(10),
            'type' => 'ingresso',
            'starts_at' => now()->addDays(15),
            'ends_at' => now()->addDays(15)->addHours(5),
            'visibility' => 'public',
            'status' => $status,
        ]);
    }

    private function createActiveTicketType(Event $event): void
    {
        TicketType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->id,
            'name' => 'Ingresso ativo',
            'price' => 99.90,
            'status' => 'ativo',
            'unit' => 'un',
            'min_per_order' => 1,
        ]);
    }
}
