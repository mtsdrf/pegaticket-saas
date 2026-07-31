<?php

namespace Tests\Feature\Subscription;

use App\Models\Subscription\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function webhook_responds_not_implemented(): void
    {
        $this->postJson('/api/v1/webhooks/payments/manual', [
            'external_id' => 'evt_1',
            'kind' => 'payment.confirmed',
        ])->assertStatus(501)
            ->assertJsonPath('code', 'WEBHOOK_NOT_IMPLEMENTED');
    }

    #[Test]
    public function webhook_is_idempotent_by_provider_and_external_id(): void
    {
        $payload = ['external_id' => 'evt_dup', 'kind' => 'payment.confirmed'];

        $this->postJson('/api/v1/webhooks/payments/manual', $payload)->assertStatus(501);
        $this->postJson('/api/v1/webhooks/payments/manual', $payload)->assertStatus(501);

        $this->assertSame(1, WebhookEvent::where('provider', 'manual')->where('external_id', 'evt_dup')->count());
    }

    #[Test]
    public function same_external_id_different_provider_are_distinct(): void
    {
        $this->postJson('/api/v1/webhooks/payments/manual', ['external_id' => 'evt_x'])->assertStatus(501);
        $this->postJson('/api/v1/webhooks/payments/asaas', ['external_id' => 'evt_x'])->assertStatus(501);

        $this->assertSame(2, WebhookEvent::where('external_id', 'evt_x')->count());
    }
}
