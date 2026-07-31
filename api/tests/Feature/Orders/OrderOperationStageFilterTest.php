<?php

namespace Tests\Feature\Orders;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\FinalCustomer\FinalCustomerTenantLink;
use App\Models\Location\Bairro;
use App\Models\Location\Cidade;
use App\Models\Location\Endereco;
use App\Models\Location\Estado;
use App\Models\Order\Order;
use App\Models\Plan\Plan;
use App\Models\Functionality\Functionality;
use App\Models\Stock\StockLocation;
use App\Models\Tenant\Tenant;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class OrderOperationStageFilterTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private array $headers;
    private FinalCustomer $client;
    private StockLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $plan = Plan::firstOrCreate(
            ['slug' => 'audit-stage-plan'],
            [
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'name' => 'Audit Stage Plan',
                'description' => 'Plano de teste',
                'sort_order' => 10,
                'is_active' => true,
            ]
        );

        $ordersFunctionality = Functionality::where('slug', 'orders')->firstOrFail();
        \Illuminate\Support\Facades\DB::table('plan_functionalities')->updateOrInsert(
            ['plan_id' => $plan->id, 'functionality_id' => $ordersFunctionality->id],
            [
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->tenant = Tenant::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Tenant Operacao',
            'slug' => 'tenant-operacao',
            'plan_id' => $plan->id,
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);
        $this->user = User::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Operador Teste',
            'email' => 'operacao-' . uniqid() . '@pegaticket.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $group = \App\Models\Group\Group::where('slug', 'administrators')->firstOrFail();
        $this->user->groups()->attach($group->id);

        $ownerRole = \App\Models\Tenant\TenantRole::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Proprietário',
            'slug' => 'owner',
            'description' => 'Owner',
            'is_active' => true,
        ]);

        \App\Models\Tenant\TenantUser::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'tenant_role_id' => $ownerRole->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->user);
        app()->instance('tenant_id', $this->tenant->id);
        app()->instance('tenant_uuid', $this->tenant->uuid);
        app()->instance('tenant', $this->tenant);

        $token = JWTAuth::fromUser($this->user, [
            'tenant_id' => $this->tenant->id,
            'tenant_uuid' => $this->tenant->uuid,
        ]);

        $this->headers = ['Authorization' => "Bearer {$token}"];

        $estado = Estado::firstOrCreate(
            ['uf' => 'SP'],
            ['name' => 'SP', 'is_active' => true]
        );
        $cidade = Cidade::firstOrCreate(
            ['estado_id' => $estado->id, 'name' => 'Sao Paulo'],
            ['is_active' => true]
        );
        $bairro = Bairro::firstOrCreate(
            ['cidade_id' => $cidade->id, 'name' => 'Centro'],
            ['is_active' => true]
        );
        $endereco = Endereco::create([
            'tenant_id' => $this->tenant->id,
            'estado_id' => $estado->id,
            'cidade_id' => $cidade->id,
            'bairro_id' => $bairro->id,
            'logradouro' => 'Rua A',
            'numero' => '10',
            'cep' => '01000000',
            'is_active' => true,
        ]);

        $this->client = FinalCustomer::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Cliente Teste',
            'email' => 'cliente-teste-' . uniqid() . '@pegaticket.test',
        ]);

        FinalCustomerTenantLink::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'final_customer_id' => $this->client->id,
            'tenant_id' => $this->tenant->id,
            'endereco_id' => $endereco->id,
            'is_active' => true,
            'confirmed_at' => now(),
        ]);

        $this->location = StockLocation::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Principal',
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    public function test_it_filters_orders_by_operational_stage(): void
    {
        $approval = $this->makeOrder(['status' => 'pending_approval', 'origin' => 'storefront']);
        $production = $this->makeOrder(['status' => 'confirmed', 'is_out_for_delivery' => false, 'is_delivered' => false]);
        $dispatch = $this->makeOrder(['status' => 'confirmed', 'is_out_for_delivery' => true, 'is_delivered' => false]);
        $financialPending = $this->makeOrder(['status' => 'confirmed', 'is_delivered' => true, 'is_paid' => false]);
        $this->makeOrder(['status' => 'confirmed', 'is_delivered' => true, 'is_paid' => true]);
        $this->makeOrder(['status' => 'rejected']);
        $this->makeOrder(['status' => 'confirmed', 'cancelled_at' => now()]);

        $this->getJson('/api/v1/orders?stage=approval', $this->headers)
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonFragment(['uuid' => $approval->uuid]);

        $this->getJson('/api/v1/orders?stage=production', $this->headers)
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonFragment(['uuid' => $production->uuid]);

        $this->getJson('/api/v1/orders?stage=dispatch', $this->headers)
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonFragment(['uuid' => $dispatch->uuid]);

        $this->getJson('/api/v1/orders?stage=financial_pending', $this->headers)
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonFragment(['uuid' => $financialPending->uuid]);
    }

    private function makeOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'final_customer_id' => $this->client->id,
            'stock_location_id' => $this->location->id,
            'codigo' => (string) random_int(1000, 9999),
            'is_installment' => false,
            'total_amount' => 100,
            'delivery_fee' => 0,
            'service_fee' => 0,
            'discount_amount' => 0,
            'is_paid' => false,
            'paid_amount' => null,
            'paid_at' => null,
            'is_delivered' => false,
            'delivered_at' => null,
            'expected_delivery_date' => null,
            'due_date' => null,
            'notes' => null,
            'status' => 'confirmed',
            'origin' => 'staff',
            'is_out_for_delivery' => false,
            'out_for_delivery_at' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'fulfillment_type' => 'delivery',
            'stock_reserved' => false,
        ], $overrides));
    }
}
