<?php

namespace Tests\Feature\Storefront;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Storefront\PushSubscription;
use App\Services\Storefront\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\WebPush;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;

/**
 * Web Push real (VAPID), roadmap Delivery Fase 4 — última fatia.
 * `WebPush` é sempre mockado via container aqui — nenhum teste bate no
 * provedor de push real (mesma cautela já usada para Nominatim/e-mail).
 */
class PushNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createCustomer(string $email = 'cliente@test.com'): FinalCustomer
    {
        return FinalCustomer::create(['email' => $email]);
    }

    private function createSubscription(FinalCustomer $customer, string $endpoint = 'https://push.example.com/1'): PushSubscription
    {
        return PushSubscription::create([
            'final_customer_id' => $customer->id,
            'endpoint' => $endpoint,
            'p256dh' => 'p256dh-value',
            'auth' => 'auth-value',
        ]);
    }

    #[Test]
    public function sends_notification_successfully_without_throwing(): void
    {
        $customer = $this->createCustomer();
        $this->createSubscription($customer);

        $report = new MessageSentReport(\Mockery::mock(RequestInterface::class), null, true);

        $this->mock(WebPush::class, function ($mock) use ($report) {
            $mock->shouldReceive('sendOneNotification')->once()->andReturn($report);
        });

        app(PushNotificationService::class)->notifyFinalCustomer(
            $customer->id,
            'Pedido aprovado',
            'Seu pedido foi aprovado!',
            '/rastreio/abc'
        );

        $this->assertDatabaseCount('push_subscriptions', 1);
    }

    #[Test]
    public function a_send_failure_exception_never_propagates(): void
    {
        $customer = $this->createCustomer();
        $this->createSubscription($customer);

        $this->mock(WebPush::class, function ($mock) {
            $mock->shouldReceive('sendOneNotification')->once()->andThrow(new \ErrorException('timeout'));
        });

        app(PushNotificationService::class)->notifyFinalCustomer(
            $customer->id,
            'Pedido recusado',
            'Seu pedido foi recusado.',
            '/rastreio/abc'
        );

        // Chegou até aqui sem exceção propagada — subscription não é
        // removida por falha transitória (só por expiração 404/410).
        $this->assertDatabaseCount('push_subscriptions', 1);
    }

    #[Test]
    public function an_expired_subscription_is_removed_after_a_404_or_410_response(): void
    {
        $customer = $this->createCustomer();
        $subscription = $this->createSubscription($customer);

        $report = new MessageSentReport($this->mock(RequestInterface::class), null, false);
        $report->setResponse(new \GuzzleHttp\Psr7\Response(410));

        $this->mock(WebPush::class, function ($mock) use ($report) {
            $mock->shouldReceive('sendOneNotification')->once()->andReturn($report);
        });

        app(PushNotificationService::class)->notifyFinalCustomer(
            $customer->id,
            'Pedido entregue',
            'Seu pedido foi entregue!',
            '/rastreio/abc'
        );

        $this->assertDatabaseMissing('push_subscriptions', ['id' => $subscription->id]);
    }

    #[Test]
    public function does_nothing_when_customer_has_no_subscriptions(): void
    {
        $customer = $this->createCustomer();

        $this->mock(WebPush::class, function ($mock) {
            $mock->shouldNotReceive('sendOneNotification');
        });

        app(PushNotificationService::class)->notifyFinalCustomer(
            $customer->id,
            'Pedido aprovado',
            'Seu pedido foi aprovado!',
            '/rastreio/abc'
        );

        $this->assertTrue(true);
    }
}
