<?php

namespace Tests\Feature\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Subscription\Concerns\CreatesSubscriptionFixtures;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesSubscriptionFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('support-user@test.com');
        $this->grantPermission('support', 'read');
        $this->grantPermission('support', 'create');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    #[Test]
    public function creates_a_ticket_without_diagnostics_by_default(): void
    {
        $response = $this->auth()->postJson('/api/v1/support/tickets', [
            'subject' => 'Erro ao emitir NF-e',
            'description' => 'A emissão fica travada no status pendente.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.subject', 'Erro ao emitir NF-e')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.diagnostics', null);

        $this->assertDatabaseCount('support_tickets', 1);
    }

    #[Test]
    public function attaches_diagnostics_snapshot_when_requested(): void
    {
        $plan = $this->createPlan('ouro');
        $planPrice = $this->createPlanPrice($plan);
        $this->createSubscription([
            'tenant_id' => $this->tenant->id,
            'plan' => $plan,
            'plan_price' => $planPrice,
        ]);

        $response = $this->auth()->postJson('/api/v1/support/tickets', [
            'subject' => 'Diagnóstico',
            'description' => 'Anexar diagnóstico automático.',
            'include_diagnostics' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.diagnostics.plan_name', $plan->name)
            ->assertJsonPath('data.diagnostics.queue_health.status', 'ok');

        $this->assertNotNull($response->json('data.diagnostics.app_version'));
        $this->assertIsArray($response->json('data.diagnostics.recent_audit_logs'));
    }

    #[Test]
    public function lists_only_tickets_from_current_tenant(): void
    {
        $this->auth()->postJson('/api/v1/support/tickets', [
            'subject' => 'Meu chamado',
            'description' => 'Descrição do chamado.',
        ]);

        $response = $this->auth()->getJson('/api/v1/support/tickets');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }
}
