<?php

namespace Tests\Feature\Sales;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
use App\Services\Storefront\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

/**
 * Web Push real (VAPID), roadmap Delivery Fase 4 — última fatia. Cobre a
 * fiação dos 3 listeners novos (SendPushOnOrder{Approved,Rejected,
 * Delivered}) sobre os eventos já existentes: PushNotificationService é
 * mockado no container (nunca a chamada real de WebPush aqui — isso já é
 * coberto por PushNotificationServiceTest) só para provar QUANDO o
 * listener decide notificar.
 */
class SalePushNotificationTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesSaleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('push-user@test.com');
        $this->grantPermission('sales', 'create');
        $this->grantPermission('sales', 'update');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    private function createPendingApprovalOrder(FinalCustomer $client, array $overrides = []): Sale
    {
        $product = $this->createProduct($this->tenant->id);

        $order = Sale::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'final_customer_id' => $client->id,
            'is_installment' => false,
            'total_amount' => 30,
            'is_paid' => false,
            'status' => 'pending_approval',
            'origin' => 'storefront',
        ], $overrides));

        SaleItem::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'sale_id' => $order->id,
            'ticket_type_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 10,
            'line_total' => 30,
        ]);

        return $order;
    }

    #[Test]
    public function approving_a_storefront_order_with_a_confirmed_link_sends_a_push(): void
    {
        // createClient() (CreatesSaleFixtures) já cria o FinalCustomer com
        // um FinalCustomerTenantLink CONFIRMADO pra este tenant — desde que
        // FinalCustomer absorveu Client (2026-07-31), o "cliente" do pedido
        // JÁ É o customer notificável, sem indireção por um Client
        // separado como antes.
        $client = $this->createClient($this->tenant->id);
        $order = $this->createPendingApprovalOrder($client);

        $this->mock(PushNotificationService::class, function ($mock) use ($client, $order) {
            $mock->shouldReceive('notifyFinalCustomer')
                ->once()
                ->with(
                    $client->id,
                    __('messages.push.order_approved_title'),
                    __('messages.push.order_approved_body'),
                    '/rastreio/' . $order->uuid
                );
        });

        $this->auth()->postJson('/api/v1/sales/' . $order->uuid . '/approve')
            ->assertStatus(200);
    }

    #[Test]
    public function rejecting_a_storefront_order_with_a_confirmed_link_sends_a_push(): void
    {
        $client = $this->createClient($this->tenant->id);
        $order = $this->createPendingApprovalOrder($client);

        $this->mock(PushNotificationService::class, function ($mock) use ($client, $order) {
            $mock->shouldReceive('notifyFinalCustomer')
                ->once()
                ->with(
                    $client->id,
                    __('messages.push.order_rejected_title'),
                    __('messages.push.order_rejected_body'),
                    '/rastreio/' . $order->uuid
                );
        });

        $this->auth()->postJson('/api/v1/sales/' . $order->uuid . '/reject', ['reason' => 'teste'])
            ->assertStatus(200);
    }

    #[Test]
    public function does_not_notify_when_order_origin_is_staff(): void
    {
        $client = $this->createClient($this->tenant->id);
        $order = $this->createPendingApprovalOrder($client, ['origin' => 'staff']);

        $this->mock(PushNotificationService::class, function ($mock) {
            $mock->shouldNotReceive('notifyFinalCustomer');
        });

        $this->auth()->postJson('/api/v1/sales/' . $order->uuid . '/approve')
            ->assertStatus(200);
    }

    #[Test]
    public function stays_silent_when_there_is_no_confirmed_link(): void
    {
        // FinalCustomer SEM FinalCustomerTenantLink pra este tenant —
        // diferente de createClient() (CreatesSaleFixtures), que sempre
        // cria o link já confirmado.
        $client = FinalCustomer::create([
            'uuid' => (string) Str::uuid(),
            'email' => 'sem-link-' . Str::random(6) . '@test.com',
        ]);
        $order = $this->createPendingApprovalOrder($client);

        $this->mock(PushNotificationService::class, function ($mock) {
            $mock->shouldNotReceive('notifyFinalCustomer');
        });

        $this->auth()->postJson('/api/v1/sales/' . $order->uuid . '/approve')
            ->assertStatus(200);
    }
}
