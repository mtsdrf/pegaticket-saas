<?php

namespace Database\Seeders;

use App\DTOs\Order\CreateOrderDTO;
use App\DTOs\Product\CreateProductCategoryDTO;
use App\DTOs\Product\CreateProductDTO;
use App\DTOs\Product\CreateProductTypeDTO;
use App\DTOs\Storefront\CreateCouponDTO;
use App\DTOs\Tenant\CreateTenantDTO;
use App\DTOs\Tenant\CreateTenantRoleDTO;
use App\DTOs\Tenant\CreateTenantUserDTO;
use App\DTOs\Tenant\SyncTenantRolePermissionsDTO;
use App\DTOs\TenantSettings\UpdateTenantSettingsDTO;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\FinalCustomer\FinalCustomerTenantLink;
use App\Models\Plan\Plan;
use App\Models\Product\Product;
use App\Models\Stock\StockLocation;
use App\Models\Tenant\Tenant;
use App\Models\User\User;
use App\Services\Order\OrderService;
use App\Services\Product\ProductCategoryService;
use App\Services\Product\ProductService;
use App\Services\Product\ProductTypeService;
use App\Services\Stock\StockService;
use App\Services\Storefront\CouponService;
use App\Services\Storefront\ProductPromotionService;
use App\Services\Storefront\StoreBusinessHoursService;
use App\Services\Subscription\SubscriptionService;
use App\Services\Tenant\TenantRolePermissionService;
use App\Services\Tenant\TenantRoleService;
use App\Services\Tenant\TenantService;
use App\Services\Tenant\TenantSettingsService;
use App\Services\Tenant\TenantUserService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Carga de DEMONSTRAÇÃO COMERCIAL do plano unico — 1 empresa demo com dono
 * + 1 funcionario de perfil restrito, cobrindo os modulos principais do
 * produto atual. Roda sozinha, sob demanda:
 *   php artisan db:seed --class=DemoPlansPresentationSeeder
 *
 * Mesmo truque de contexto fora do HTTP que DemoTenantSeeder já usa
 * (Auth::setUser + app()->instance('tenant'/'tenant_id'/'tenant_uuid')),
 * reaproveitando Services de domínio (nunca INSERT cru) para que eventos,
 * auditoria e saldo de estoque fiquem idênticos a uma operação real.
 *
 * Idempotência: guard por slug de tenant, POR TENANT (não aborta os outros
     * um se ele já existir) — `buildTenant()` roda na própria
     * DB::transaction.
 */
class DemoPlansPresentationSeeder extends Seeder
{
    private const PASSWORD = 'PegaTicket@2026';

    public function run(): void
    {
        $rows = [];

        foreach ($this->tenantConfigs() as $cfg) {
            if (Tenant::withTrashed()->where('slug', $cfg['slug'])->exists()) {
                $this->command?->warn(
                    "DemoPlansPresentationSeeder: tenant \"{$cfg['slug']}\" já existe — pulando (idempotência por slug, não aborta os demais)."
                );
                continue;
            }

            $rows = array_merge($rows, DB::transaction(fn () => $this->buildTenant($cfg)));
        }

        if ($rows === []) {
            $this->command?->warn('DemoPlansPresentationSeeder: nenhum tenant novo criado (todos já existiam).');
            return;
        }

        $this->command?->table(['Empresa', 'Plano', 'Usuário', 'E-mail', 'Senha'], $rows);
    }

    /** @return array<int, array{slug: string, name: string, plan_slug: string, owner_email: string, owner_name: string, employee_email: string, employee_name: string}> */
    private function tenantConfigs(): array
    {
        return [
            [
                'slug' => 'demo-pegaticket',
                'name' => 'Operacao Demo PegaTicket',
                'plan_slug' => 'pegaticket',
                'owner_email' => 'dono.demo@pegaticket.com',
                'owner_name' => 'Dono — Plano PegaTicket',
                'employee_email' => 'funcionario.demo@pegaticket.com',
                'employee_name' => 'Funcionário — Plano PegaTicket',
            ],
        ];
    }

    /** @return array<int, array{0: string, 1: string, 2: string, 3: string, 4: string}> */
    private function buildTenant(array $cfg): array
    {
        $owner = User::updateOrCreate(
            ['email' => $cfg['owner_email']],
            ['name' => $cfg['owner_name'], 'password' => Hash::make(self::PASSWORD), 'is_active' => true]
        );
        Auth::setUser($owner);

        $plan = Plan::where('slug', $cfg['plan_slug'])->firstOrFail();

        $tenant = app(TenantService::class)->create(CreateTenantDTO::fromArray([
            'name' => $cfg['name'],
            'slug' => $cfg['slug'],
            'plan_uuid' => $plan->uuid,
            'is_active' => true,
        ]), $owner->id, $owner->id);

        app()->instance('tenant', $tenant);
        app()->instance('tenant_id', $tenant->id);
        app()->instance('tenant_uuid', $tenant->uuid);

        $catalog = $this->setupCatalog($tenant);
        $location = $this->resolveDefaultStockLocation($tenant);
        $this->stockUp($catalog['products'], $location);

        $clients = $this->setupClients($tenant);
        $this->setupStorefront($tenant);
        $this->createBaselineOrders($tenant, $clients, $catalog['products'], $location);

        $this->setupEmployee($tenant, $cfg);

        $this->setupCoupon($tenant);
        $this->setupPromotion($tenant, $catalog['products']);
        $this->setupSubscription($tenant, $plan);

        return [
            [$cfg['name'], 'PegaTicket', $cfg['owner_name'], $cfg['owner_email'], self::PASSWORD],
            [$cfg['name'], 'PegaTicket', $cfg['employee_name'], $cfg['employee_email'], self::PASSWORD],
        ];
    }

    /**
     * Catálogo comum a todos os planos (cafeteria/restaurante): categoria
     * "Bebidas" (3 produtos) + "Lanches" (3 produtos), sem acoplamento com
     * módulos legados de atendimento interno na carga de demonstração.
     *
     * @return array{products: array<int, array{model: Product, price: float}>}
     */
    private function setupCatalog(Tenant $tenant): array
    {
        $categoriesSpec = [
            'Bebidas' => [
                'type' => 'Bebida',
                'products' => [
                    ['name' => 'Refrigerante Lata 350ml', 'price' => 6.00, 'sku' => 'BEB-REF-350'],
                    ['name' => 'Suco Natural 300ml', 'price' => 8.00, 'sku' => 'BEB-SUC-300'],
                    ['name' => 'Água Mineral 500ml', 'price' => 4.00, 'sku' => 'BEB-AGU-500'],
                ],
            ],
            'Lanches' => [
                'type' => 'Lanche',
                'products' => [
                    ['name' => 'Sanduíche Natural', 'price' => 15.00, 'sku' => 'LAN-SAN-001'],
                    ['name' => 'Coxinha', 'price' => 7.00, 'sku' => 'LAN-COX-001'],
                    ['name' => 'Bolo Fatia', 'price' => 9.00, 'sku' => 'LAN-BOL-001'],
                ],
            ],
        ];

        $products = [];

        foreach ($categoriesSpec as $categoryName => $spec) {
            $category = app(ProductCategoryService::class)->create(CreateProductCategoryDTO::fromArray([
                'name' => $categoryName,
                'priority' => 1,
                'is_active' => true,
            ], $tenant->id));

            $type = app(ProductTypeService::class)->create(CreateProductTypeDTO::fromArray([
                'name' => $spec['type'],
                'product_category_uuid' => $category->uuid,
                'priority' => 1,
                'is_active' => true,
            ], $tenant->id));

            foreach ($spec['products'] as $p) {
                $product = app(ProductService::class)->create(CreateProductDTO::fromArray([
                    'name' => $p['name'],
                    'product_type_uuid' => $type->uuid,
                    'price' => $p['price'],
                    'description' => $p['name'],
                    'is_available' => true,
                    'sku' => $p['sku'],
                    'unit' => 'un',
                    'min_stock' => 10,
                ], $tenant->id));

                $products[] = ['model' => $product, 'price' => $p['price']];
            }
        }

        return ['products' => $products];
    }

    private function resolveDefaultStockLocation(Tenant $tenant): StockLocation
    {
        // "Depósito Principal" já nasce sozinho via listener CreateDefaultStockLocation
        // (evento TenantCreated) — não criar um segundo local aqui.
        return StockLocation::where('tenant_id', $tenant->id)
            ->where('is_default', true)
            ->firstOrFail();
    }

    /**
     * Estoque necessário SEMPRE (Order::create reserva estoque de verdade
     * independente do plano expor ou não a UI de "Estoque" — reserve()
     * lança InsufficientStockException sem saldo), não é exclusivo de
     * extras comerciais antigas.
     *
     * @param array<int, array{model: Product, price: float}> $products
     */
    private function stockUp(array $products, StockLocation $location): void
    {
        foreach ($products as $p) {
            app(StockService::class)->entry(
                $p['model'],
                $location,
                200.0,
                'Carga inicial de estoque — abertura da demonstração',
            );
        }
    }

    /**
     * FinalCustomer absorveu Client (2026-07-31): cliente demo passa a ser
     * um FinalCustomer global + FinalCustomerTenantLink (registro por-tenant
     * com endereço/telefone), em vez de um Client tenant-scoped.
     *
     * @return array<int, FinalCustomer>
     */
    private function setupClients(Tenant $tenant): array
    {
        $spec = [
            ['name' => 'Mercadinho Bom Preço', 'phone' => '(16) 99101-2233'],
            ['name' => 'Bar do Zé', 'phone' => '(16) 99202-3344'],
            ['name' => 'Padaria Pão Dourado', 'phone' => '(16) 99404-5566'],
        ];

        $created = [];
        foreach ($spec as $c) {
            $finalCustomer = FinalCustomer::create([
                'uuid' => (string) Str::uuid(),
                'name' => $c['name'],
                'email' => Str::slug($c['name']) . '-' . $tenant->id . '@demo.pegaticket.com',
            ]);

            FinalCustomerTenantLink::create([
                'uuid' => (string) Str::uuid(),
                'final_customer_id' => $finalCustomer->id,
                'tenant_id' => $tenant->id,
                'phone_primary' => $c['phone'],
                'is_trusted' => true,
                'is_active' => true,
                'confirmed_at' => now(),
            ]);

            $created[] = $finalCustomer;
        }

        return $created;
    }

    private function setupStorefront(Tenant $tenant): void
    {
        $days = [];
        foreach (range(0, 6) as $day) {
            $days[] = ['day_of_week' => $day, 'opens_at' => '00:00:00', 'closes_at' => '23:59:59', 'is_closed' => false];
        }
        app(StoreBusinessHoursService::class)->replaceForTenant($tenant->id, $days);

        app(TenantSettingsService::class)->update($tenant->id, UpdateTenantSettingsDTO::fromArray([
            'send_tracking_link_whatsapp' => false,
            'block_order_without_stock' => false,
            'minimum_order_value' => 0,
            'estimated_preparation_minutes' => 20,
        ]));
    }

    /**
     * 5-8 pedidos com mix de origem/status, o suficiente pra dashboard e
     * relatórios não ficarem vazios: 4 entregues+pagos (staff, histórico),
     * 2 vindos da bilheteria online parados na fila de aprovação, 1 confirmado
     * ainda não entregue (candidato à tela de Rotas).
     *
     * @param array<int, array{model: Product, price: float}> $products
     */
    private function createBaselineOrders(Tenant $tenant, array $clients, array $products, StockLocation $location): void
    {
        $orderService = app(OrderService::class);

        for ($i = 0; $i < 4; $i++) {
            $client = $clients[array_rand($clients)];
            $items = $this->randomItems($products, 1, 3);

            $order = $orderService->create(CreateOrderDTO::fromArray([
                'final_customer_uuid' => $client->uuid,
                'stock_location_uuid' => $location->uuid,
                'items' => $items,
                'origin' => 'staff',
                'status' => 'confirmed',
                'mark_as_delivered' => true,
                'mark_as_paid' => true,
            ], $tenant->id));

            $createdAt = Carbon::now()->subDays(random_int(2, 30))->subHours(random_int(0, 10));
            DB::table('orders')->where('id', $order->id)->update([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
                'delivered_at' => $createdAt,
                'paid_at' => $createdAt,
            ]);
        }

        for ($i = 0; $i < 2; $i++) {
            $client = $clients[array_rand($clients)];
            $items = $this->randomItems($products, 1, 2);

            $orderService->create(CreateOrderDTO::fromArray([
                'final_customer_uuid' => $client->uuid,
                'stock_location_uuid' => $location->uuid,
                'items' => $items,
                'origin' => 'storefront',
                'status' => 'pending_approval',
            ], $tenant->id));
        }

        $client = $clients[array_rand($clients)];
        $orderService->create(CreateOrderDTO::fromArray([
            'final_customer_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'items' => $this->randomItems($products, 1, 2),
            'origin' => 'staff',
            'status' => 'confirmed',
            'expected_delivery_date' => Carbon::now()->addDay()->toDateString(),
        ], $tenant->id));
    }

    /** @param array<int, array{model: Product, price: float}> $products */
    private function randomItems(array $products, int $min, int $max): array
    {
        $picked = collect($products)->random(random_int($min, $max));

        return $picked->map(fn (array $p) => [
            'product_uuid' => $p['model']->uuid,
            'quantity' => 1.0,
            'unit_price' => $p['price'],
        ])->values()->all();
    }

    /**
     * Funcionário de perfil restrito, permissões crescentes por plano (ver
     * plano aprovado). `perm.action` já existem via ActionsSeeder —
     * conferido antes de escrever este seeder, nenhuma action nova
     * precisou ser adaptada.
     */
    private function setupEmployee(Tenant $tenant, array $cfg): void
    {
        $permissions = [
            ['functionality' => 'orders', 'action' => 'read'],
            ['functionality' => 'orders', 'action' => 'create'],
            ['functionality' => 'products', 'action' => 'read'],
            ['functionality' => 'dashboard', 'action' => 'read'],
            ['functionality' => 'storefront-orders', 'action' => 'read'],
            ['functionality' => 'stock', 'action' => 'read'],
        ];

        $role = app(TenantRoleService::class)->create(CreateTenantRoleDTO::fromArray([
            'name' => 'Funcionário',
            'slug' => 'funcionario',
            'is_active' => true,
        ], $tenant->id));

        app(TenantRolePermissionService::class)->syncPermissions(
            $role,
            SyncTenantRolePermissionsDTO::fromArray(['permissions' => $permissions])
        );

        $employee = User::updateOrCreate(
            ['email' => $cfg['employee_email']],
            ['name' => $cfg['employee_name'], 'password' => Hash::make(self::PASSWORD), 'is_active' => true]
        );

        app(TenantUserService::class)->create(CreateTenantUserDTO::fromArray([
            'user_uuid' => $employee->uuid,
            'tenant_uuid' => $tenant->uuid,
            'role_uuid' => $role->uuid,
            'is_active' => true,
        ]));
    }

    private function setupCoupon(Tenant $tenant): void
    {
        app(CouponService::class)->create($tenant->id, CreateCouponDTO::fromArray([
            'code' => 'DEMO10',
            'type' => 'percentage',
            'value' => 10,
            'minimum_order_value' => 20,
            'is_active' => true,
        ]));
    }

    /** @param array<int, array{model: Product, price: float}> $products */
    private function setupPromotion(Tenant $tenant, array $products): void
    {
        $product = $products[0]['model'];

        app(ProductPromotionService::class)->upsert(
            $tenant->id,
            $product->uuid,
            round($products[0]['price'] * 0.8, 2),
            now()->toDateTimeString(),
            now()->addDays(30)->toDateTimeString()
        );
    }

    /**
     * Assinatura ativa (não trial) com período coerente com "hoje" — o
     * fluxo normal (SubscriptionService::create) sempre nasce trialing;
     * forceFill direto no model pra representar uma assinatura já paga na
     * demonstração.
     */
    private function setupSubscription(Tenant $tenant, Plan $plan): void
    {
        $subscription = app(SubscriptionService::class)->create($tenant->id, $plan->uuid, 'monthly', '127.0.0.1');

        $subscription->forceFill([
            'status' => 'active',
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
            'next_charge_at' => now()->addMonth()->startOfMonth(),
        ])->save();
    }

}
