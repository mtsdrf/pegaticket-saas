<?php

namespace Tests\Feature\Subscription;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Subscription\Concerns\CreatesSubscriptionFixtures;
use Tests\TestCase;

/**
 * Gate de acesso por status de assinatura (roadmap 1B): assinatura
 * suspensa/cancelada bloqueia o acesso operacional, mas a própria tela de
 * assinatura continua acessível para reativar.
 */
class SubscriptionAccessGateTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesSubscriptionFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenantScopedUser('gate-owner@example.com', 'owner', 'Proprietário');
        $this->grantPermission('sales', 'read');
        $this->grantPermission('subscription', 'read');
    }

    private function auth(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    #[Test]
    public function suspended_subscription_blocks_operational_endpoint(): void
    {
        $this->createSubscription(['tenant_id' => $this->tenant->id, 'status' => 'suspended']);

        $this->withHeaders($this->auth())
            ->getJson('/api/v1/sales')
            ->assertStatus(403)
            ->assertJsonPath('code', 'SUBSCRIPTION_SUSPENDED');
    }

    #[Test]
    public function suspended_subscription_still_allows_subscription_screen(): void
    {
        $this->createSubscription(['tenant_id' => $this->tenant->id, 'status' => 'suspended']);

        $this->withHeaders($this->auth())
            ->getJson('/api/v1/subscription')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'suspended');
    }

    #[Test]
    public function non_owner_cannot_open_the_subscription_screen_even_with_permission(): void
    {
        $this->tenantRole->update(['slug' => 'member', 'name' => 'Membro']);

        $this->withHeaders($this->auth())
            ->getJson('/api/v1/subscription')
            ->assertStatus(403)
            ->assertJsonPath('code', 'TENANT_OWNER_REQUIRED');
    }

    #[Test]
    public function tenant_without_subscription_is_not_blocked(): void
    {
        $this->withHeaders($this->auth())
            ->getJson('/api/v1/sales')
            ->assertStatus(200);
    }

    #[Test]
    public function active_subscription_does_not_block(): void
    {
        $this->createSubscription(['tenant_id' => $this->tenant->id, 'status' => 'active']);

        $this->withHeaders($this->auth())
            ->getJson('/api/v1/sales')
            ->assertStatus(200);
    }
}
