<?php

namespace Database\Seeders;

use App\DTOs\Client\CreateClientDTO;
use App\DTOs\Location\CreateBairroDTO;
use App\DTOs\Location\CreateCidadeDTO;
use App\DTOs\Order\CreateOrderDTO;
use App\DTOs\Product\CreateProductCategoryDTO;
use App\DTOs\Product\CreateProductDTO;
use App\DTOs\Product\CreateProductTypeDTO;
use App\DTOs\Tenant\CreateTenantDTO;
use App\DTOs\TenantSettings\UpdateTenantSettingsDTO;
use App\Models\Client\Client;
use App\Models\Location\Bairro;
use App\Models\Location\Cidade;
use App\Models\Location\Estado;
use App\Models\Plan\Plan;
use App\Models\Product\Product;
use App\Models\Stock\StockLocation;
use App\Models\Tenant\Tenant;
use App\Models\User\User;
use App\Services\Client\ClientService;
use App\Services\Location\BairroService;
use App\Services\Location\CidadeService;
use App\Services\Order\OrderService;
use App\Services\Product\ProductCategoryService;
use App\Services\Product\ProductService;
use App\Services\Product\ProductTypeService;
use App\Services\Stock\StockService;
use App\Services\Storefront\StoreBusinessHoursService;
use App\Services\Storefront\StoreDeliveryFeeService;
use App\Services\Tenant\TenantService;
use App\Services\Tenant\TenantSettingsService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Carga de DEMONSTRAÇÃO COMERCIAL — separada da carga inicial
 * (DatabaseSeeder). Roda sozinha, sob demanda:
 *   php artisan db:seed --class=DemoTenantSeeder
 *
 * Cria uma empresa fictícia completa (distribuidora de bebidas) com
 * clientes, produtos com foto real, estoque, histórico de pedidos e loja
 * online configurada, pronta pra apresentar a prospects. NÃO é idempotente
 * de propósito além do guard inicial — rodar duas vezes aborta cedo (ver
 * `run()`) em vez de duplicar dados, porque diferente da carga inicial
 * (grupos/permissões/planos, que fazem sentido existir uma vez só e
 * sempre iguais), aqui cada re-execução deveria ser uma decisão explícita
 * ("quero outra empresa demo" = mudar o slug/e-mail abaixo).
 *
 * Reaproveita os Services de domínio (não INSERT cru) para que eventos,
 * auditoria e saldo de estoque fiquem idênticos a uma operação real —
 * único truque necessário é "fingir" o contexto de tenant/usuário que
 * normalmente vem do middleware HTTP (ResolveTenant), replicando
 * exatamente o padrão já usado em produção por
 * StorefrontCheckoutService::checkout() para o mesmo problema.
 */
class DemoTenantSeeder extends Seeder
{
    private const TENANT_NAME = 'Distribuidora Show de Bebidas';
    private const TENANT_SLUG = 'distribuidora-show';
    private const OWNER_NAME = 'Distribuidora Show — Conta Demonstração';
    private const OWNER_EMAIL = 'teste@teste.com';
    private const OWNER_PASSWORD = 'PegaTicket@2026';

    /** Caminho dos assets fornecidos pelo usuário, na raiz do repo (fora de `api/`). */
    private const ASSETS_ROOT = __DIR__ . '/../../../';

    private array $products = [
        ['name' => 'Coca-Cola Lata 350 ml', 'image' => 'cocacolalata350ml.png', 'price' => 6.00, 'type' => 'refrigerante', 'sku' => 'COCA-LT-350', 'brand' => 'Coca-Cola', 'stock' => 600, 'order_qty' => [6, 24]],
        ['name' => 'Coca-Cola Garrafa 2 L', 'image' => 'cocacola2l.png', 'price' => 12.00, 'type' => 'refrigerante', 'sku' => 'COCA-GF-2L', 'brand' => 'Coca-Cola', 'stock' => 300, 'order_qty' => [2, 10]],
        ['name' => 'Guaraná Antarctica Lata 350 ml', 'image' => 'guaranaantarticalata350ml.png', 'price' => 5.50, 'type' => 'refrigerante', 'sku' => 'GUAR-LT-350', 'brand' => 'Ambev', 'stock' => 500, 'order_qty' => [6, 24]],
        ['name' => 'Guaraná Antarctica Garrafa 2 L', 'image' => 'guaranaantarica2l.png', 'price' => 11.00, 'type' => 'refrigerante', 'sku' => 'GUAR-GF-2L', 'brand' => 'Ambev', 'stock' => 250, 'order_qty' => [2, 10]],
        ['name' => 'Fanta Laranja Lata 350 ml', 'image' => 'faltalata350ml.png', 'price' => 5.50, 'type' => 'refrigerante', 'sku' => 'FANT-LT-350', 'brand' => 'Coca-Cola', 'stock' => 400, 'order_qty' => [6, 20]],
        ['name' => 'Fanta Laranja Garrafa 2 L', 'image' => 'fanta2l.png', 'price' => 11.00, 'type' => 'refrigerante', 'sku' => 'FANT-GF-2L', 'brand' => 'Coca-Cola', 'stock' => 200, 'order_qty' => [2, 8]],
        ['name' => 'Sprite Lata 350 ml', 'image' => 'spritelata350ml.png', 'price' => 5.50, 'type' => 'refrigerante', 'sku' => 'SPRI-LT-350', 'brand' => 'Coca-Cola', 'stock' => 350, 'order_qty' => [6, 18]],
        ['name' => 'Sprite Garrafa 2 L', 'image' => 'sprite2l.png', 'price' => 11.00, 'type' => 'refrigerante', 'sku' => 'SPRI-GF-2L', 'brand' => 'Coca-Cola', 'stock' => 180, 'order_qty' => [2, 8]],
        ['name' => 'Pepsi Garrafa 500 ml', 'image' => 'pepsi500ml.png', 'price' => 6.50, 'type' => 'refrigerante', 'sku' => 'PEPS-GF-500', 'brand' => 'PepsiCo', 'stock' => 220, 'order_qty' => [3, 12]],
        ['name' => 'Água Mineral sem Gás 500 ml', 'image' => 'agua500ml.png', 'price' => 3.50, 'type' => 'agua', 'sku' => 'AGUA-500', 'brand' => 'Fonte Pura', 'stock' => 450, 'order_qty' => [6, 30]],
    ];

    /** Bairros reais de Araraquara/SP (confirmados via busca), coordenadas aproximadas do centro de cada região. */
    private array $bairros = [
        ['name' => 'Centro', 'cep' => '14801-320', 'lat' => -21.7919, 'lng' => -48.1811],
        ['name' => 'Vila Xavier', 'cep' => '14810-020', 'lat' => -21.7705, 'lng' => -48.2015],
        ['name' => 'São Geraldo', 'cep' => '14805-100', 'lat' => -21.8015, 'lng' => -48.1720],
        ['name' => 'Jardim América', 'cep' => '14802-060', 'lat' => -21.7850, 'lng' => -48.1650],
        ['name' => 'Vila Melhado', 'cep' => '14808-045', 'lat' => -21.7770, 'lng' => -48.1890],
    ];

    /** 1 cliente por bairro, endereço/telefone fake com DDD real de Araraquara (16). */
    private array $clients = [
        ['name' => 'Mercadinho Bom Preço', 'bairro' => 'Centro', 'logradouro' => 'Rua São Bento', 'numero' => '452', 'phone' => '(16) 99101-2233'],
        ['name' => 'Bar do Zé', 'bairro' => 'Vila Xavier', 'logradouro' => 'Rua Voluntários da Pátria', 'numero' => '118', 'phone' => '(16) 99202-3344'],
        ['name' => 'Padaria Pão Dourado', 'bairro' => 'São Geraldo', 'logradouro' => 'Rua Marechal Deodoro', 'numero' => '876', 'phone' => '(16) 99303-4455'],
        ['name' => 'Restaurante Sabor Caseiro', 'bairro' => 'Jardim América', 'logradouro' => 'Rua Padre Duarte', 'numero' => '305', 'phone' => '(16) 99404-5566'],
        ['name' => 'Lanchonete Estrela', 'bairro' => 'Vila Melhado', 'logradouro' => 'Rua Gonçalves Dias', 'numero' => '640', 'phone' => '(16) 99505-6677'],
    ];

    public function run(): void
    {
        if (Tenant::withTrashed()->where('slug', self::TENANT_SLUG)->exists()) {
            $this->command?->warn('DemoTenantSeeder: tenant "' . self::TENANT_SLUG . '" já existe — abortando sem alterar nada (não é idempotente de propósito, ver comentário da classe).');
            return;
        }

        $this->assertAssetsExist();

        DB::transaction(function () {
            $owner = $this->createOwnerUser();
            Auth::setUser($owner);

            $tenant = $this->createTenant($owner);
            app()->instance('tenant', $tenant);
            app()->instance('tenant_id', $tenant->id);

            [$cidade, $bairros] = $this->setupGeography();
            $estadoSp = Estado::where('uf', 'SP')->firstOrFail();

            $products = $this->setupCatalog($tenant);
            $location = $this->resolveDefaultStockLocation($tenant);
            $this->stockUp($products, $location);

            $clients = $this->setupClients($tenant, $estadoSp, $cidade, $bairros);
            $this->setupStorefront($tenant, $bairros);
            $this->createOrders($tenant, $clients, $products, $location);
        });

        $this->command?->info('DemoTenantSeeder: OK — tenant "' . self::TENANT_NAME . '" (slug: ' . self::TENANT_SLUG . ') pronto. Login: ' . self::OWNER_EMAIL . ' / ' . self::OWNER_PASSWORD);
    }

    private function assertAssetsExist(): void
    {
        $missing = [];

        foreach ($this->products as $p) {
            if (!is_file(self::ASSETS_ROOT . 'produtos/' . $p['image'])) {
                $missing[] = 'produtos/' . $p['image'];
            }
        }

        if (!is_file(self::ASSETS_ROOT . 'logo_empresa_teste.png')) {
            $missing[] = 'logo_empresa_teste.png';
        }

        if ($missing) {
            throw new RuntimeException(
                'DemoTenantSeeder: assets ausentes na raiz do repo: ' . implode(', ', $missing) .
                ' — precisam existir no checkout de onde este seeder é executado (o BLOB fica salvo no banco, só precisa existir uma vez, na hora de rodar).'
            );
        }
    }

    private function fakeUploadedFile(string $relativePath): UploadedFile
    {
        $path = self::ASSETS_ROOT . $relativePath;

        return new UploadedFile(
            path: $path,
            originalName: basename($path),
            mimeType: 'image/png',
            error: null,
            test: true,
        );
    }

    private function createOwnerUser(): User
    {
        return User::updateOrCreate(
            ['email' => self::OWNER_EMAIL],
            ['name' => self::OWNER_NAME, 'password' => Hash::make(self::OWNER_PASSWORD), 'is_active' => true]
        );
    }

    private function createTenant(User $owner): Tenant
    {
        $plan = Plan::where('slug', 'diamante')->firstOrFail();

        $dto = CreateTenantDTO::fromArray([
            'name' => self::TENANT_NAME,
            'slug' => self::TENANT_SLUG,
            'plan_uuid' => $plan->uuid,
            'is_active' => true,
            'logo' => $this->fakeUploadedFile('logo_empresa_teste.png'),
        ]);

        return app(TenantService::class)->create($dto, $owner->id, $owner->id);
    }

    /** @return array{0: Cidade, 1: array<string, Bairro>} */
    private function setupGeography(): array
    {
        $estadoSp = Estado::where('uf', 'SP')->firstOrFail();

        $cidade = Cidade::where('estado_id', $estadoSp->id)->where('name', 'Araraquara')->first()
            ?? app(CidadeService::class)->create(CreateCidadeDTO::fromArray([
                'name' => 'Araraquara',
                'estado_uuid' => $estadoSp->uuid,
                'is_active' => true,
            ]));

        $bairros = [];
        foreach ($this->bairros as $b) {
            $bairros[$b['name']] = Bairro::where('cidade_id', $cidade->id)->where('name', $b['name'])->first()
                ?? app(BairroService::class)->create(CreateBairroDTO::fromArray([
                    'name' => $b['name'],
                    'cidade_uuid' => $cidade->uuid,
                    'is_active' => true,
                ]));
        }

        return [$cidade, $bairros];
    }

    /** @return array<int, Product> */
    private function setupCatalog(Tenant $tenant): array
    {
        $category = app(ProductCategoryService::class)->create(CreateProductCategoryDTO::fromArray([
            'name' => 'Bebidas sem Álcool',
            'priority' => 1,
            'is_active' => true,
        ], $tenant->id));

        $types = [];
        foreach (['refrigerante' => 'Refrigerante', 'agua' => 'Água'] as $key => $label) {
            $types[$key] = app(ProductTypeService::class)->create(CreateProductTypeDTO::fromArray([
                'name' => $label,
                'product_category_uuid' => $category->uuid,
                'priority' => 1,
                'is_active' => true,
            ], $tenant->id));
        }

        $created = [];
        foreach ($this->products as $p) {
            $dto = CreateProductDTO::fromArray([
                'name' => $p['name'],
                'product_type_uuid' => $types[$p['type']]->uuid,
                'price' => $p['price'],
                'description' => $p['name'] . ' — ' . $p['brand'],
                'is_available' => true,
                'image' => $this->fakeUploadedFile('produtos/' . $p['image']),
                'sku' => $p['sku'],
                'brand' => $p['brand'],
                'unit' => 'un',
                'min_stock' => 30,
                'last_purchase_cost' => round($p['price'] * 0.6, 2),
            ], $tenant->id);

            $product = app(ProductService::class)->create($dto);
            $created[] = ['model' => $product, 'meta' => $p];
        }

        return $created;
    }

    private function resolveDefaultStockLocation(Tenant $tenant): StockLocation
    {
        // "Depósito Principal" já nasce sozinho via listener CreateDefaultStockLocation
        // (evento TenantCreated) — não criar um segundo local aqui.
        return StockLocation::where('tenant_id', $tenant->id)
            ->where('is_default', true)
            ->firstOrFail();
    }

    private function stockUp(array $products, StockLocation $location): void
    {
        $entryDate = Carbon::now()->subDays(60);

        foreach ($products as $p) {
            $movement = app(StockService::class)->entry(
                $p['model'],
                $location,
                (float) $p['meta']['stock'],
                'Carga inicial de estoque — abertura da operação',
            );

            DB::table('stock_movements')->where('id', $movement->id)->update([
                'created_at' => $entryDate,
                'updated_at' => $entryDate,
            ]);
        }
    }

    /** @return array<int, Client> */
    private function setupClients(Tenant $tenant, Estado $estado, Cidade $cidade, array $bairros): array
    {
        $created = [];

        foreach ($this->clients as $c) {
            $bairro = $bairros[$c['bairro']];
            $bairroMeta = collect($this->bairros)->firstWhere('name', $c['bairro']);

            $dto = CreateClientDTO::fromArray([
                'name' => $c['name'],
                'phone_primary' => $c['phone'],
                'is_trusted' => true,
                'is_active' => true,
                'estado_uuid' => $estado->uuid,
                'cidade_uuid' => $cidade->uuid,
                'bairro_uuid' => $bairro->uuid,
                'logradouro' => $c['logradouro'],
                'numero' => $c['numero'],
                'cep' => $bairroMeta['cep'],
                'lat' => $bairroMeta['lat'],
                'lng' => $bairroMeta['lng'],
            ], $tenant->id);

            $created[] = app(ClientService::class)->create($dto);
        }

        return $created;
    }

    private function setupStorefront(Tenant $tenant, array $bairros): void
    {
        // Dia inteiro, todos os 7 dias, de propósito: garante que a loja
        // apareça "aberta" na demonstração não importa quando o prospect for
        // olhar nem o timezone de comparação (app.timezone=UTC, então um
        // horário "comercial" pensado em horário do Brasil poderia cair fora
        // da janela em UTC) — realismo cede pra confiabilidade da demo aqui.
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
            'estimated_preparation_minutes' => 30,
        ]));
    }

    private function createOrders(Tenant $tenant, array $clients, array $products, StockLocation $location): void
    {
        $orderService = app(OrderService::class);

        // 24 pedidos históricos (últimos ~55 dias), já entregues e pagos —
        // é o que alimenta os gráficos de faturamento/indicadores.
        for ($i = 0; $i < 24; $i++) {
            $client = $clients[array_rand($clients)];
            $items = $this->randomItems($products);

            $order = $orderService->create(CreateOrderDTO::fromArray([
                'client_uuid' => $client->uuid,
                'stock_location_uuid' => $location->uuid,
                'items' => $items,
                'origin' => 'staff',
                'status' => 'confirmed',
                'mark_as_delivered' => true,
                'mark_as_paid' => true,
            ], $tenant->id));

            $createdAt = Carbon::now()->subDays(random_int(3, 55))->subHours(random_int(0, 10));
            $deliveredAt = (clone $createdAt)->addDays(random_int(0, 2))->addHours(random_int(1, 10));
            $paidAt = (clone $deliveredAt)->addDays(random_int(0, 4))->addHours(random_int(0, 8));

            DB::table('orders')->where('id', $order->id)->update([
                'created_at' => $createdAt,
                'updated_at' => $paidAt,
                'delivered_at' => $deliveredAt,
                'paid_at' => $paidAt,
            ]);
            DB::table('order_items')->where('order_id', $order->id)->update([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        // 3 pedidos vindos "da loja online", parados na fila de aprovação —
        // mostra a tela de aprovação de pedidos com dado de verdade.
        for ($i = 0; $i < 3; $i++) {
            $client = $clients[array_rand($clients)];
            $items = $this->randomItems($products);

            $order = $orderService->create(CreateOrderDTO::fromArray([
                'client_uuid' => $client->uuid,
                'stock_location_uuid' => $location->uuid,
                'items' => $items,
                'origin' => 'storefront',
                'status' => 'pending_approval',
            ], $tenant->id));

            $createdAt = Carbon::now()->subHours(random_int(2, 60));
            DB::table('orders')->where('id', $order->id)->update(['created_at' => $createdAt, 'updated_at' => $createdAt]);
            DB::table('order_items')->where('order_id', $order->id)->update(['created_at' => $createdAt, 'updated_at' => $createdAt]);
        }

        // 3 pedidos confirmados, ainda não entregues, com entrega esperada
        // hoje/amanhã — candidatos prontos pra tela de Rotas.
        foreach ([0, 0, 1] as $offset) {
            $client = $clients[array_rand($clients)];
            $items = $this->randomItems($products);

            $orderService->create(CreateOrderDTO::fromArray([
                'client_uuid' => $client->uuid,
                'stock_location_uuid' => $location->uuid,
                'items' => $items,
                'origin' => 'staff',
                'status' => 'confirmed',
                'expected_delivery_date' => Carbon::now()->addDays($offset)->toDateString(),
            ], $tenant->id));
        }
    }

    /** @return array<int, array{product_uuid: string, quantity: float}> */
    private function randomItems(array $products): array
    {
        $picked = collect($products)->random(random_int(2, 4));

        return $picked->map(function (array $p) {
            [$min, $max] = $p['meta']['order_qty'];

            return [
                'product_uuid' => $p['model']->uuid,
                'quantity' => (float) random_int($min, $max),
            ];
        })->values()->all();
    }
}
