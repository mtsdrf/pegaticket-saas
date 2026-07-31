<?php

namespace Tests\Feature\Webhook;

use App\Exceptions\WebhookDeliveryFailedException;
use App\Jobs\Webhook\SendWebhookJob;
use App\Models\Webhook\WebhookDelivery;
use App\Models\Webhook\WebhookSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class WebhookDispatchTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesOrderFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('webhook-dispatch-user@test.com');
        $this->grantPermission('orders', 'create');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    private function createSubscription(array $eventTypes = ['order.created']): WebhookSubscription
    {
        return WebhookSubscription::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'url' => 'https://example.com/webhooks/maskats',
            'event_types' => $eventTypes,
            'secret' => 'test-secret-value',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function order_created_dispatches_a_correctly_signed_webhook(): void
    {
        Http::fake([
            'example.com/*' => Http::response(['ok' => true], 200),
        ]);

        $subscription = $this->createSubscription(['order.created']);

        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);
        $this->stockEntry($this->tenant->id, $product, $location, 10);

        $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 1],
            ],
        ])->assertStatus(201);

        Http::assertSent(function ($request) use ($subscription) {
            if ($request->url() !== $subscription->url) {
                return false;
            }

            $body = $request->body();
            $expectedSignature = 'sha256=' . hash_hmac('sha256', $body, $subscription->secret);

            return $request->hasHeader('X-Maskats-Signature', $expectedSignature)
                && $request->hasHeader('X-Maskats-Event', 'order.created')
                && json_decode($body, true)['event'] === 'order.created';
        });

        $this->assertDatabaseCount('webhook_deliveries', 1);
        $delivery = WebhookDelivery::first();
        $this->assertTrue($delivery->success);
        $this->assertEquals(200, $delivery->response_status);
        $this->assertEquals($this->tenant->id, $delivery->tenant_id);
    }

    #[Test]
    public function inactive_subscription_is_not_notified(): void
    {
        Http::fake();

        $this->createSubscription(['order.created'])
            ->forceFill(['is_active' => false])
            ->save();

        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);
        $this->stockEntry($this->tenant->id, $product, $location, 10);

        $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 1],
            ],
        ])->assertStatus(201);

        Http::assertNothingSent();
        $this->assertDatabaseCount('webhook_deliveries', 0);
    }

    #[Test]
    public function subscription_not_subscribed_to_event_is_not_notified(): void
    {
        Http::fake();

        $this->createSubscription(['order.paid']);

        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);
        $this->stockEntry($this->tenant->id, $product, $location, 10);

        $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => 1],
            ],
        ])->assertStatus(201);

        Http::assertNothingSent();
    }

    #[Test]
    public function job_throws_and_records_failed_delivery_on_non_2xx_response(): void
    {
        Http::fake([
            'example.com/*' => Http::response(['error' => 'boom'], 500),
        ]);

        $subscription = $this->createSubscription(['order.created']);

        $job = new SendWebhookJob(
            $subscription->id,
            'order.created',
            ['uuid' => 'fake-order-uuid'],
            (string) Str::uuid()
        );

        $this->expectException(WebhookDeliveryFailedException::class);

        try {
            $job->handle();
        } finally {
            $this->assertDatabaseCount('webhook_deliveries', 1);
            $delivery = WebhookDelivery::first();
            $this->assertFalse($delivery->success);
            $this->assertEquals(500, $delivery->response_status);
            $this->assertEquals(1, $delivery->attempt);
        }
    }

    #[Test]
    public function job_records_failed_delivery_on_connection_error(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        $subscription = $this->createSubscription(['order.created']);

        $job = new SendWebhookJob(
            $subscription->id,
            'order.created',
            ['uuid' => 'fake-order-uuid'],
            (string) Str::uuid()
        );

        $this->expectException(WebhookDeliveryFailedException::class);

        try {
            $job->handle();
        } finally {
            $delivery = WebhookDelivery::first();
            $this->assertFalse($delivery->success);
            $this->assertNull($delivery->response_status);
            $this->assertNotNull($delivery->error);
        }
    }

    #[Test]
    public function job_does_nothing_when_subscription_no_longer_active(): void
    {
        Http::fake();

        $subscription = $this->createSubscription(['order.created']);
        $subscription->forceFill(['is_active' => false])->save();

        $job = new SendWebhookJob(
            $subscription->id,
            'order.created',
            ['uuid' => 'fake-order-uuid'],
            (string) Str::uuid()
        );

        $job->handle();

        Http::assertNothingSent();
        $this->assertDatabaseCount('webhook_deliveries', 0);
    }

    #[Test]
    public function job_has_five_tries_and_exponential_backoff(): void
    {
        $job = new SendWebhookJob(1, 'order.created', [], (string) Str::uuid());

        $this->assertEquals(5, $job->tries);
        $this->assertEquals([10, 30, 120, 600, 1800], $job->backoff);
    }
}
