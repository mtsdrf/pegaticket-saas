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
        $this->grantPermission('sales', 'deliver');
        $this->grantPermission('sales', 'pay');
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
            'is_delivered' => false,
            'status' => 'pending_approval',
            'origin' => 'storefront',
        ], $overrides));

        SaleItem::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'ticket_type_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 10,
            'line_total' => 30,
        ]);

        return $order;
    }

    /**
     * Mesmo padrão de SaleApprovalQueueTest: cria um pedido de verdade via
     * POST /orders (reserva real de estoque) e simula que ele "nasceu" da
     * loja, virando pending_approval — necessário aqui especificamente
     * porque deliver() sempre converte a reserva em saída física
     * (exit()), que exige saldo de estoque real (diferente de
     * approve()/reject(), que não tocam saldo físico).
     */
    private function createPendingApprovalOrderWithRealReservation(FinalCustomer $client): Sale
    {
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);

        $response = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 3],
            ],
        ])->assertStatus(201);

        $order = Sale::where('uuid', $response->json('data.uuid'))->firstOrFail();
        $order->status = 'pending_approval';
        $order->origin = 'storefront';
        $order->save();

        return $order->fresh();
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
    public function delivering_a_storefront_order_with_a_confirmed_link_sends_a_push(): void
    {
        $client = $this->createClient($this->tenant->id);
        $order = $this->createPendingApprovalOrderWithRealReservation($client);

        $this->auth()->postJson('/api/v1/sales/' . $order->uuid . '/approve')->assertStatus(200);

        $this->mock(PushNotificationService::class, function ($mock) use ($client, $order) {
            $mock->shouldReceive('notifyFinalCustomer')
                ->once()
                ->with(
                    $client->id,
                    __('messages.push.order_delivered_title'),
                    __('messages.push.order_delivered_body'),
                    '/rastreio/' . $order->uuid
                );
        });

        $this->auth()->patchJson('/api/v1/sales/' . $order->uuid . '/deliver')
            ->assertStatus(200);
    }

    #[Test]
    public function delivering_an_order_does_not_500_when_vapid_keys_are_missing(): void
    {
        // Regressão do bug real de 2026-07-17: ambiente novo cujo .env
        // ainda não recebeu as chaves VAPID reais. O listener de push é
        // resolvido pelo container de forma síncrona dentro da mesma
        // transação de deliver() — sem o fix em
        // AppServiceProvider::register() (try/catch em torno do binding de
        // WebPush::class), Minishlink\WebPush\VAPID::validate() lançava
        // ErrorException e o pedido inteiro dava rollback + 500, mesmo sem
        // nenhum FinalCustomer vinculado (a resolução do container já
        // quebra antes de qualquer lógica de "pra quem notificar" rodar).
        config([
            'services.vapid.public_key' => null,
            'services.vapid.private_key' => null,
            'services.vapid.subject' => null,
        ]);

        $client = $this->createClient($this->tenant->id);
        $order = $this->createPendingApprovalOrderWithRealReservation($client);

        $this->auth()->postJson('/api/v1/sales/' . $order->uuid . '/approve')->assertStatus(200);

        $this->auth()->patchJson('/api/v1/sales/' . $order->uuid . '/deliver')
            ->assertStatus(200);

        $this->assertTrue($order->fresh()->is_delivered);
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
