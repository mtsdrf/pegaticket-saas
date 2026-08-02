<?php

namespace Tests\Feature\Onboarding;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\FinalCustomer\FinalCustomerTenantLink;
use App\Models\Event\Event;
use App\Models\Event\EventCategory;
use App\Models\Event\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class OnboardingChecklistTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('onboarding-user@test.com');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    #[Test]
    public function checklist_starts_fully_pending_for_a_new_tenant(): void
    {
        $response = $this->auth()
            ->getJson('/api/v1/onboarding/checklist')
            ->assertStatus(200);

        $data = $response->json('data');

        $this->assertFalse($data['has_product']);
        $this->assertFalse($data['has_client']);
        $this->assertFalse($data['has_first_sale']);
        $this->assertFalse($data['is_dismissed']);
        $this->assertNull($data['dismissed_at']);
        $this->assertEquals(0, $data['completed']);
        $this->assertEquals(3, $data['total']);
        $this->assertSame(['has_product', 'has_client', 'has_first_sale'], array_column($data['steps'], 'key'));
    }

    #[Test]
    public function checklist_reflects_existing_product_and_client(): void
    {
        $category = EventCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Bebidas',
            'is_active' => true,
        ]);

        $event = Event::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'event_category_id' => $category->id,
            'name' => 'Evento Teste',
            'slug' => 'evento-' . Str::random(10),
            'type' => 'ingresso',
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(4),
            'visibility' => 'public',
            'status' => 'publicado',
        ]);

        TicketType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'event_id' => $event->id,
            'name' => 'Coca-Cola',
            'price' => 10.00,
            'status' => 'ativo',
            'unit' => 'un',
        ]);

        $finalCustomer = FinalCustomer::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Cliente Teste',
            'email' => 'cliente-teste-' . Str::random(8) . '@test.com',
        ]);

        FinalCustomerTenantLink::create([
            'uuid' => (string) Str::uuid(),
            'final_customer_id' => $finalCustomer->id,
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'confirmed_at' => now(),
        ]);

        $data = $this->auth()
            ->getJson('/api/v1/onboarding/checklist')
            ->assertStatus(200)
            ->json('data');

        $this->assertTrue($data['has_product']);
        $this->assertTrue($data['has_client']);
        $this->assertFalse($data['has_first_sale']);
        $this->assertEquals(2, $data['completed']);
    }

    #[Test]
    public function checklist_can_be_dismissed_and_restored_per_tenant_user(): void
    {
        $dismissed = $this->auth()
            ->postJson('/api/v1/onboarding/checklist/dismiss')
            ->assertStatus(200)
            ->json('data');

        $this->assertTrue($dismissed['is_dismissed']);
        $this->assertNotNull($dismissed['dismissed_at']);

        $restored = $this->auth()
            ->deleteJson('/api/v1/onboarding/checklist/dismiss')
            ->assertStatus(200)
            ->json('data');

        $this->assertFalse($restored['is_dismissed']);
        $this->assertNull($restored['dismissed_at']);
    }

    #[Test]
    public function checklist_ignores_soft_deleted_records(): void
    {
        $finalCustomer = FinalCustomer::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Cliente Removido',
            'email' => 'cliente-removido-' . Str::random(8) . '@test.com',
        ]);

        $link = FinalCustomerTenantLink::create([
            'uuid' => (string) Str::uuid(),
            'final_customer_id' => $finalCustomer->id,
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'confirmed_at' => now(),
        ]);
        $link->delete();

        $data = $this->auth()
            ->getJson('/api/v1/onboarding/checklist')
            ->assertStatus(200)
            ->json('data');

        $this->assertFalse($data['has_client']);
    }

    #[Test]
    public function checklist_is_scoped_per_tenant(): void
    {
        $category = EventCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Bebidas',
            'is_active' => true,
        ]);

        $event = Event::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'event_category_id' => $category->id,
            'name' => 'Evento Teste',
            'slug' => 'evento-' . Str::random(10),
            'type' => 'ingresso',
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(4),
            'visibility' => 'public',
            'status' => 'publicado',
        ]);

        TicketType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'event_id' => $event->id,
            'name' => 'Coca-Cola',
            'price' => 10.00,
            'status' => 'ativo',
            'unit' => 'un',
        ]);

        // Novo tenant/usuário (setUpTenantScopedUser cria um Tenant novo a
        // cada chamada) — reaproveita o helper em vez de montar um segundo
        // tenant/vínculo manualmente.
        $this->setUpTenantScopedUser('onboarding-other-user@test.com');

        $data = $this->auth()
            ->getJson('/api/v1/onboarding/checklist')
            ->assertStatus(200)
            ->json('data');

        $this->assertFalse($data['has_product']);
    }

    #[Test]
    public function unauthenticated_request_is_rejected(): void
    {
        // SetsUpTenantScopedUser::setUpTenantScopedUser() troca de tenant
        // via $this->withHeader(...)->postJson(...), o que deixa o
        // Authorization Bearer setado em $this->defaultHeaders pro RESTO
        // do teste (withHeader() não é "por chamada", é acumulativo na
        // instância) — sem remover aqui, esta requisição "sem auth"
        // sairia autenticada por acidente.
        $this->withoutHeader('Authorization')
            ->getJson('/api/v1/onboarding/checklist')
            ->assertStatus(401);
    }
}
