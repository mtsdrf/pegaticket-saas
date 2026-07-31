<?php

namespace Tests\Feature\Webhook;

use App\Models\Webhook\WebhookSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class WebhookSubscriptionTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('webhook-user@test.com');
        $this->grantPermission('api-access', 'read');
        $this->grantPermission('api-access', 'create');
        $this->grantPermission('api-access', 'update');
        $this->grantPermission('api-access', 'delete');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    #[Test]
    public function creates_a_subscription_and_returns_secret_only_once(): void
    {
        $response = $this->auth()->postJson('/api/v1/webhook-subscriptions', [
            'url' => 'https://example.com/webhooks/maskats',
            'event_types' => ['order.created', 'order.paid'],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.url', 'https://example.com/webhooks/maskats')
            ->assertJsonPath('data.event_types.0', 'order.created');

        $secret = $response->json('data.secret');
        $this->assertNotNull($secret);

        $stored = WebhookSubscription::first();
        $this->assertEquals($secret, $stored->secret);

        $show = $this->auth()->getJson('/api/v1/webhook-subscriptions/' . $stored->uuid);
        $show->assertStatus(200)->assertJsonMissingPath('data.secret');
    }

    #[Test]
    public function rejects_unsupported_event_type(): void
    {
        $this->auth()->postJson('/api/v1/webhook-subscriptions', [
            'url' => 'https://example.com/webhooks/maskats',
            'event_types' => ['product.deleted'],
        ])->assertStatus(422);
    }

    #[Test]
    public function updates_and_deletes_a_subscription(): void
    {
        $create = $this->auth()->postJson('/api/v1/webhook-subscriptions', [
            'url' => 'https://example.com/webhooks/maskats',
            'event_types' => ['order.created'],
        ]);
        $uuid = $create->json('data.uuid');

        $this->auth()->putJson("/api/v1/webhook-subscriptions/{$uuid}", [
            'url' => 'https://example.com/webhooks/maskats-v2',
            'event_types' => ['order.created', 'order.cancelled'],
            'is_active' => false,
        ])->assertStatus(200)
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.url', 'https://example.com/webhooks/maskats-v2');

        $this->auth()->deleteJson("/api/v1/webhook-subscriptions/{$uuid}")->assertStatus(204);

        $this->assertSoftDeleted('webhook_subscriptions', ['uuid' => $uuid]);
    }

    #[Test]
    public function lists_only_subscriptions_from_current_tenant(): void
    {
        $this->auth()->postJson('/api/v1/webhook-subscriptions', [
            'url' => 'https://example.com/webhooks/maskats',
            'event_types' => ['order.created'],
        ]);

        $this->auth()->getJson('/api/v1/webhook-subscriptions')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }
}
