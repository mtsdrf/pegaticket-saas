<?php

namespace Database\Seeders;

use App\DTOs\Balcao\AddComandaItemDTO;
use App\DTOs\Balcao\CloseComandaDTO;
use App\DTOs\Balcao\CreateStationDTO;
use App\DTOs\Balcao\CreateTableDTO;
use App\DTOs\Balcao\OpenComandaDTO;
use App\DTOs\Balcao\UpdatePrepStatusDTO;
use App\DTOs\Client\CreateClientDTO;
use App\DTOs\Location\CreateBairroDTO;
use App\DTOs\Location\CreateCidadeDTO;
use App\DTOs\Order\CreateOrderDTO;
use App\DTOs\Pdv\CreatePdvSaleDTO;
use App\DTOs\Pdv\OpenCashSessionDTO;
use App\DTOs\Product\CreateProductCategoryDTO;
use App\DTOs\Product\CreateProductDTO;
use App\DTOs\Product\CreateProductTypeDTO;
use App\DTOs\Storefront\CreateCouponDTO;
use App\DTOs\Tenant\CreateTenantDTO;
use App\DTOs\Tenant\CreateTenantRoleDTO;
use App\DTOs\Tenant\CreateTenantUserDTO;
use App\DTOs\Tenant\SyncTenantRolePermissionsDTO;
use App\DTOs\TenantSettings\UpdateTenantSettingsDTO;
use App\Models\Balcao\Station;
use App\Models\Client\Client;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\FinalCustomer\FinalCustomerTenantLink;
use App\Models\Location\Bairro;
use App\Models\Location\Cidade;
use App\Models\Location\Estado;
use App\Models\Plan\Plan;
use App\Models\Product\Product;
use App\Models\Stock\StockLocation;
use App\Models\Tenant\Tenant;
use App\Models\User\User;
use App\Services\Balcao\ComandaItemService;
use App\Services\Balcao\ComandaService;
use App\Services\Balcao\StationService;
use App\Services\Balcao\TableService;
use App\Services\Client\ClientService;
use App\Services\Location\BairroService;
use App\Services\Location\CidadeService;
use App\Services\Order\OrderService;
use App\Services\Pdv\CashSessionService;
use App\Services\Pdv\PdvSaleService;
use App\Services\Product\ProductCategoryService;
use App\Services\Product\ProductService;
use App\Services\Product\ProductTypeService;
use App\Services\Stock\StockService;
use App\Services\Storefront\CouponService;
use App\Services\Storefront\ProductPromotionService;
use App\Services\Storefront\StoreBusinessHoursService;
use App\Services\Storefront\StoreDeliveryFeeService;
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
 * Carga de DEMONSTRAÇÃO COMERCIAL segmentada por plano — 3 empresas
 * (`demo-prata`/`demo-ouro`/`demo-diamante`), uma por plano, cada uma com
 * dono + 1 funcionário de perfil restrito, com dados em todos os módulos
 * que aquele plano libera. Roda sozinha, sob demanda:
 *   php artisan db:seed --class=DemoPlansPresentationSeeder
 *
 * Mesmo truque de contexto fora do HTTP que DemoTenantSeeder já usa
 * (Auth::setUser + app()->instance('tenant'/'tenant_id'/'tenant_uuid')),
 * reaproveitando Services de domínio (nunca INSERT cru) para que eventos,
 * auditoria e saldo de estoque fiquem idênticos a uma operação real.
 *
 * Idempotência: guard por slug de tenant, POR TENANT (não aborta os outros
 * dois se um já existir) — cada `buildTenant()` roda na própria
 * DB::transaction.
 */
class DemoPlansPresentationSeeder extends Seeder
{
    private const PASSWORD = 'Maskats@2026';

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

    /** @return array<int, array{slug: string, name: string, plan_slug: string, tier: string, owner_email: string, owner_name: string, employee_email: string, employee_name: string}> */
    private function tenantConfigs(): array
    {
        return [
            [
                'slug' => 'demo-prata',
                'name' => 'Cafeteria Prata Demo',
                'plan_slug' => 'prata',
                'tier' => 'prata',
                'owner_email' => 'dono.prata@maskats.com',
                'owner_name' => 'Dono — Plano Prata',
                'employee_email' => 'funcionario.prata@maskats.com',
                'employee_name' => 'Funcionário — Plano Prata',
            ],
            [
                'slug' => 'demo-ouro',
                'name' => 'Cafeteria Ouro Demo',
                'plan_slug' => 'ouro',
                'tier' => 'ouro',
                'owner_email' => 'dono.ouro@maskats.com',
                'owner_name' => 'Dono — Plano Ouro',
                'employee_email' => 'funcionario.ouro@maskats.com',
                'employee_name' => 'Funcionário — Plano Ouro',
            ],
            [
                'slug' => 'demo-diamante',
                'name' => 'Restaurante Diamante Demo',
                'plan_slug' => 'diamante',
                'tier' => 'diamante',
                'owner_email' => 'dono.diamante@maskats.com',
                'owner_name' => 'Dono — Plano Diamante',
                'employee_email' => 'funcionario.diamante@maskats.com',
                'employee_name' => 'Funcionário — Plano Diamante',
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

        [$estado, $cidade, $bairros] = $this->setupGeography();

        $catalog = $this->setupCatalog($tenant, $cfg['tier']);
        $location = $this->resolveDefaultStockLocation($tenant);
        $this->stockUp($catalog['products'], $location);

        $clients = $this->setupClients($tenant, $estado, $cidade, $bairros);
        $this->setupStorefront($tenant, $bairros);
        $this->createBaselineOrders($tenant, $clients, $catalog['products'], $location);

        $this->setupEmployee($tenant, $cfg);

        if (in_array($cfg['tier'], ['ouro', 'diamante'], true)) {
            $this->setupCashbackDemo($tenant, $clients[0]);
            $this->setupPdvDemo($tenant, $catalog['products']);
            $this->setupCoupon($tenant);
            $this->setupPromotion($tenant, $catalog['products']);
        }

        if ($cfg['tier'] === 'diamante') {
            $this->setupSubscription($tenant, $plan);
            $this->setupBalcao($tenant, $catalog);
        }

        return [
            [$cfg['name'], ucfirst($cfg['tier']), $cfg['owner_name'], $cfg['owner_email'], self::PASSWORD],
            [$cfg['name'], ucfirst($cfg['tier']), $cfg['employee_name'], $cfg['employee_email'], self::PASSWORD],
        ];
    }

    /**
     * Geografia (Estado/Cidade/Bairro) reaproveitada entre os 3 tenants via
     * firstOr, mesmo truque do DemoTenantSeeder — evita colisão de `uf`
     * (bairros/cidades são globais, não tenant-scoped).
     *
     * @return array{0: Estado, 1: Cidade, 2: array<string, Bairro>}
     */
    private function setupGeography(): array
    {
        $estado = Estado::where('uf', 'SP')->firstOrFail();

        $cidade = Cidade::where('estado_id', $estado->id)->where('name', 'Araraquara')->first()
            ?? app(CidadeService::class)->create(CreateCidadeDTO::fromArray([
                'name' => 'Araraquara',
                'estado_uuid' => $estado->uuid,
                'is_active' => true,
            ]));

        $bairroNames = ['Centro' => '14801-320', 'Vila Xavier' => '14810-020', 'Jardim América' => '14802-060'];

        $bairros = [];
        foreach ($bairroNames as $name => $cep) {
            $bairros[$name] = Bairro::where('cidade_id', $cidade->id)->where('name', $name)->first()
                ?? app(BairroService::class)->create(CreateBairroDTO::fromArray([
                    'name' => $name,
                    'cidade_uuid' => $cidade->uuid,
                    'is_active' => true,
                ]));
        }

        return [$estado, $cidade, $bairros];
    }

    /**
     * Catálogo comum a todos os planos (cafeteria/restaurante): categoria
     * "Bebidas" (3 produtos) + "Lanches" (3 produtos). Só no plano Diamante
     * cria também as 2 estações (Cozinha/Bar) e roteia cada categoria pra
     * sua estação via `product_categories.station_id` — coluna sem DTO/rota
     * própria ainda (ver ComandaService), setada aqui via DB::table()
     * direto, mesmo padrão que o DemoTenantSeeder já usa para ajustar
     * timestamp de simulação.
     *
     * @return array{products: array<int, array{model: Product, price: float}>, stations: array{kitchen: ?Station, bar: ?Station}}
     */
    private function setupCatalog(Tenant $tenant, string $tier): array
    {
        $stations = ['kitchen' => null, 'bar' => null];

        if ($tier === 'diamante') {
            $stations['kitchen'] = app(StationService::class)->create(CreateStationDTO::fromArray([
                'name' => 'Cozinha',
                'type' => 'kitchen',
                'is_active' => true,
            ], $tenant->id));

            $stations['bar'] = app(StationService::class)->create(CreateStationDTO::fromArray([
                'name' => 'Bar',
                'type' => 'bar',
                'is_active' => true,
            ], $tenant->id));
        }

        $categoriesSpec = [
            'Bebidas' => [
                'station' => $stations['bar'],
                'type' => 'Bebida',
                'products' => [
                    ['name' => 'Refrigerante Lata 350ml', 'price' => 6.00, 'sku' => 'BEB-REF-350'],
                    ['name' => 'Suco Natural 300ml', 'price' => 8.00, 'sku' => 'BEB-SUC-300'],
                    ['name' => 'Água Mineral 500ml', 'price' => 4.00, 'sku' => 'BEB-AGU-500'],
                ],
            ],
            'Lanches' => [
                'station' => $stations['kitchen'],
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

            if ($spec['station'] !== null) {
                DB::table('product_categories')->where('id', $category->id)->update(['station_id' => $spec['station']->id]);
            }

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

        return ['products' => $products, 'stations' => $stations];
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
     * Ouro/Diamante.
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

    /** @return array<int, Client> */
    private function setupClients(Tenant $tenant, Estado $estado, Cidade $cidade, array $bairros): array
    {
        $spec = [
            ['name' => 'Mercadinho Bom Preço', 'bairro' => 'Centro', 'logradouro' => 'Rua São Bento', 'numero' => '452', 'phone' => '(16) 99101-2233'],
            ['name' => 'Bar do Zé', 'bairro' => 'Vila Xavier', 'logradouro' => 'Rua Voluntários da Pátria', 'numero' => '118', 'phone' => '(16) 99202-3344'],
            ['name' => 'Padaria Pão Dourado', 'bairro' => 'Jardim América', 'logradouro' => 'Rua Padre Duarte', 'numero' => '305', 'phone' => '(16) 99404-5566'],
        ];

        $created = [];
        foreach ($spec as $c) {
            $bairro = $bairros[$c['bairro']];

            $created[] = app(ClientService::class)->create(CreateClientDTO::fromArray([
                'name' => $c['name'],
                'phone_primary' => $c['phone'],
                'is_trusted' => true,
                'is_active' => true,
                'estado_uuid' => $estado->uuid,
                'cidade_uuid' => $cidade->uuid,
                'bairro_uuid' => $bairro->uuid,
                'logradouro' => $c['logradouro'],
                'numero' => $c['numero'],
            ], $tenant->id));
        }

        return $created;
    }

    private function setupStorefront(Tenant $tenant, array $bairros): void
    {
        $days = [];
        foreach (range(0, 6) as $day) {
            $days[] = ['day_of_week' => $day, 'opens_at' => '00:00:00', 'closes_at' => '23:59:59', 'is_closed' => false];
        }
        app(StoreBusinessHoursService::class)->replaceForTenant($tenant->id, $days);

        foreach ($bairros as $bairro) {
            app(StoreDeliveryFeeService::class)->upsert($tenant->id, $bairro->uuid, 5.00);
        }

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
     * 2 vindos "da loja online" parados na fila de aprovação, 1 confirmado
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
                'client_uuid' => $client->uuid,
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
                'client_uuid' => $client->uuid,
                'stock_location_uuid' => $location->uuid,
                'items' => $items,
                'origin' => 'storefront',
                'status' => 'pending_approval',
            ], $tenant->id));
        }

        $client = $clients[array_rand($clients)];
        $orderService->create(CreateOrderDTO::fromArray([
            'client_uuid' => $client->uuid,
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
        $permissionsByTier = [
            'prata' => [
                'orders:read', 'orders:create',
                'clients:read', 'clients:create',
                'products:read',
                'dashboard:read',
                'storefront-orders:read', 'storefront-orders:update',
            ],
            'ouro' => ['stock:read', 'pdv:sell'],
            'diamante' => ['balcao:add_item', 'balcao:prep'],
        ];

        $pairs = $permissionsByTier['prata'];
        if (in_array($cfg['tier'], ['ouro', 'diamante'], true)) {
            $pairs = array_merge($pairs, $permissionsByTier['ouro']);
        }
        if ($cfg['tier'] === 'diamante') {
            $pairs = array_merge($pairs, $permissionsByTier['diamante']);
        }

        $role = app(TenantRoleService::class)->create(CreateTenantRoleDTO::fromArray([
            'name' => 'Funcionário',
            'slug' => 'funcionario',
            'is_active' => true,
        ], $tenant->id));

        $permissions = array_map(function (string $pair) {
            [$functionality, $action] = explode(':', $pair);
            return ['functionality' => $functionality, 'action' => $action];
        }, $pairs);

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

    /**
     * Ativa cashback no tenant e credita pelo menos 1 pedido: exige um
     * pedido `origin=storefront` de um cliente com FinalCustomerTenantLink
     * CONFIRMADO (CreditCashbackOnOrderPaid::handle → CashbackService::
     * creditEarning) — criado direto via Model (sem checkout HTTP), já que
     * o objetivo aqui é só popular o extrato pra demonstração.
     */
    private function setupCashbackDemo(Tenant $tenant, Client $client): void
    {
        app(TenantSettingsService::class)->update($tenant->id, UpdateTenantSettingsDTO::fromArray([
            'send_tracking_link_whatsapp' => false,
            'block_order_without_stock' => false,
            'minimum_order_value' => 0,
            'estimated_preparation_minutes' => 20,
            'cashback_enabled' => true,
            'cashback_percentage' => 5.0,
            'cashback_max_per_order' => 20.0,
            'cashback_hold_days' => 0,
            'cashback_expiration_days' => 90,
            'cashback_name' => 'Cashback',
        ]));

        $finalCustomer = FinalCustomer::firstOrCreate(
            ['email' => 'cliente.final.' . $tenant->slug . '@maskats.com'],
            ['uuid' => (string) Str::uuid(), 'name' => 'Cliente Final Demo']
        );

        FinalCustomerTenantLink::firstOrCreate(
            ['final_customer_id' => $finalCustomer->id, 'tenant_id' => $tenant->id, 'client_id' => $client->id],
            ['uuid' => (string) Str::uuid(), 'confirmed_at' => now()]
        );

        $location = $this->resolveDefaultStockLocation($tenant);
        $product = Product::where('tenant_id', $tenant->id)->firstOrFail();

        app(OrderService::class)->create(CreateOrderDTO::fromArray([
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'items' => [['product_uuid' => $product->uuid, 'quantity' => 2.0, 'unit_price' => (float) $product->price]],
            'origin' => 'storefront',
            'status' => 'confirmed',
            'mark_as_delivered' => true,
            'mark_as_paid' => true,
        ], $tenant->id));
    }

    /**
     * Abre um caixa e registra 2 vendas de PDV com métodos de pagamento
     * variados. Cada venda usa itens com preço/quantidade inteiros
     * conhecidos de antemão para que a soma dos pagamentos bata EXATAMENTE
     * com o total calculado no backend (senão PaymentAmountMismatchException).
     *
     * @param array<int, array{model: Product, price: float}> $products
     */
    private function setupPdvDemo(Tenant $tenant, array $products): void
    {
        app(CashSessionService::class)->open($tenant->id, OpenCashSessionDTO::fromArray(['opening_amount' => 100.00]));

        $p1 = $products[0];
        app(PdvSaleService::class)->create($tenant->id, CreatePdvSaleDTO::fromArray([
            'items' => [['product_uuid' => $p1['model']->uuid, 'quantity' => 2, 'unit_price' => $p1['price']]],
            'payments' => [['method' => 'cash', 'amount' => round($p1['price'] * 2, 2)]],
        ]));

        $p2 = $products[3];
        $p3 = $products[4];
        $total = round($p2['price'] + $p3['price'], 2);
        app(PdvSaleService::class)->create($tenant->id, CreatePdvSaleDTO::fromArray([
            'items' => [
                ['product_uuid' => $p2['model']->uuid, 'quantity' => 1, 'unit_price' => $p2['price']],
                ['product_uuid' => $p3['model']->uuid, 'quantity' => 1, 'unit_price' => $p3['price']],
            ],
            'payments' => [
                ['method' => 'pix', 'amount' => round($total / 2, 2)],
                ['method' => 'credit', 'amount' => $total - round($total / 2, 2)],
            ],
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

    /**
     * Balcão: mesa/comanda com itens em estágios variados de preparo
     * (queued, sent_to_station->preparing->ready->delivered_to_table) e
     * uma segunda mesa fechada completa, pra mostrar o ciclo inteiro lado a
     * lado (mesa aberta + mesa fechada).
     *
     * @param array{products: array<int, array{model: Product, price: float}>, stations: array{kitchen: ?Station, bar: ?Station}} $catalog
     */
    private function setupBalcao(Tenant $tenant, array $catalog): void
    {
        $tableService = app(TableService::class);
        $comandaService = app(ComandaService::class);
        $itemService = app(ComandaItemService::class);

        $tables = [];
        foreach (['Mesa 1', 'Mesa 2', 'Mesa 3', 'Mesa 4'] as $label) {
            $tables[] = $tableService->create(CreateTableDTO::fromArray([
                'label' => $label,
                'seats' => 4,
                'status' => 'free',
            ], $tenant->id));
        }

        $products = $catalog['products'];

        // Mesa 1: comanda aberta com 3 itens em estágios variados de preparo.
        $comandaOpen = $comandaService->open($tenant->id, OpenComandaDTO::fromArray(['table_uuid' => $tables[0]->uuid]));

        $item1 = $comandaService->addItem($tenant->id, $comandaOpen->uuid, AddComandaItemDTO::fromArray([
            'product_uuid' => $products[3]['model']->uuid, // Sanduíche
            'qty' => 1,
        ]));
        $item2 = $comandaService->addItem($tenant->id, $comandaOpen->uuid, AddComandaItemDTO::fromArray([
            'product_uuid' => $products[4]['model']->uuid, // Coxinha
            'qty' => 1,
        ]));
        $item3 = $comandaService->addItem($tenant->id, $comandaOpen->uuid, AddComandaItemDTO::fromArray([
            'product_uuid' => $products[0]['model']->uuid, // Refrigerante
            'qty' => 1,
        ]));

        // item1: enviado + em preparo.
        $itemService->sendToStation($tenant->id, $comandaOpen->uuid, $item1->uuid);
        $itemService->updatePrepStatus($tenant->id, $comandaOpen->uuid, $item1->uuid, UpdatePrepStatusDTO::fromArray(['prep_status' => 'preparing']));

        // item2: enviado, em preparo e pronto.
        $itemService->sendToStation($tenant->id, $comandaOpen->uuid, $item2->uuid);
        $itemService->updatePrepStatus($tenant->id, $comandaOpen->uuid, $item2->uuid, UpdatePrepStatusDTO::fromArray(['prep_status' => 'preparing']));
        $itemService->updatePrepStatus($tenant->id, $comandaOpen->uuid, $item2->uuid, UpdatePrepStatusDTO::fromArray(['prep_status' => 'ready']));

        // item3: ciclo completo até entregue na mesa (comanda continua aberta).
        $itemService->sendToStation($tenant->id, $comandaOpen->uuid, $item3->uuid);
        $itemService->updatePrepStatus($tenant->id, $comandaOpen->uuid, $item3->uuid, UpdatePrepStatusDTO::fromArray(['prep_status' => 'preparing']));
        $itemService->updatePrepStatus($tenant->id, $comandaOpen->uuid, $item3->uuid, UpdatePrepStatusDTO::fromArray(['prep_status' => 'ready']));
        $itemService->updatePrepStatus($tenant->id, $comandaOpen->uuid, $item3->uuid, UpdatePrepStatusDTO::fromArray(['prep_status' => 'delivered_to_table']));

        // Mesa 2: comanda aberta e fechada por completo (ciclo inteiro).
        $comandaClosed = $comandaService->open($tenant->id, OpenComandaDTO::fromArray(['table_uuid' => $tables[1]->uuid]));

        $itemA = $comandaService->addItem($tenant->id, $comandaClosed->uuid, AddComandaItemDTO::fromArray([
            'product_uuid' => $products[1]['model']->uuid, // Suco Natural
            'qty' => 2,
        ]));
        $itemB = $comandaService->addItem($tenant->id, $comandaClosed->uuid, AddComandaItemDTO::fromArray([
            'product_uuid' => $products[5]['model']->uuid, // Bolo Fatia
            'qty' => 1,
        ]));

        $itemService->sendToStation($tenant->id, $comandaClosed->uuid, $itemA->uuid);
        $itemService->updatePrepStatus($tenant->id, $comandaClosed->uuid, $itemA->uuid, UpdatePrepStatusDTO::fromArray(['prep_status' => 'preparing']));
        $itemService->updatePrepStatus($tenant->id, $comandaClosed->uuid, $itemA->uuid, UpdatePrepStatusDTO::fromArray(['prep_status' => 'ready']));
        $itemService->updatePrepStatus($tenant->id, $comandaClosed->uuid, $itemA->uuid, UpdatePrepStatusDTO::fromArray(['prep_status' => 'delivered_to_table']));

        $itemService->sendToStation($tenant->id, $comandaClosed->uuid, $itemB->uuid);
        $itemService->updatePrepStatus($tenant->id, $comandaClosed->uuid, $itemB->uuid, UpdatePrepStatusDTO::fromArray(['prep_status' => 'preparing']));
        $itemService->updatePrepStatus($tenant->id, $comandaClosed->uuid, $itemB->uuid, UpdatePrepStatusDTO::fromArray(['prep_status' => 'ready']));
        $itemService->updatePrepStatus($tenant->id, $comandaClosed->uuid, $itemB->uuid, UpdatePrepStatusDTO::fromArray(['prep_status' => 'delivered_to_table']));

        $total = round(($products[1]['price'] * 2) + $products[5]['price'], 2);

        $comandaService->close($tenant->id, $comandaClosed->uuid, CloseComandaDTO::fromArray([
            'payments' => [['method' => 'credit', 'amount' => $total]],
            'apply_service_fee' => true,
        ]));
    }
}
