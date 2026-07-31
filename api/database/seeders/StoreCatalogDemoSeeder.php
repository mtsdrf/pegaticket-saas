<?php

namespace Database\Seeders;

use App\DTOs\Product\CreateProductDTO;
use App\DTOs\Storefront\CreateCouponDTO;
use App\DTOs\TenantSettings\UpdateTenantSettingsDTO;
use App\Models\Product\Product;
use App\Models\Storefront\Coupon;
use App\Models\Tenant\Tenant;
use App\Models\User\User;
use App\Services\Product\ProductService;
use App\Services\Storefront\CouponService;
use App\Services\Storefront\ProductPromotionService;
use App\Services\Storefront\StoreAddressService;
use App\Services\Storefront\StoreDeliveryFeeService;
use App\Services\Tenant\TenantSettingsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Carga de DEMONSTRAÇÃO de catálogo/loja para o tenant JÁ EXISTENTE
 * "10k Atacadista" (slug 10k-atacadista). Roda sob demanda, uma vez:
 *   php artisan db:seed --class=StoreCatalogDemoSeeder
 *
 * NÃO cria tenant novo — só popula/atualiza dados do tenant existente para
 * exercitar as funcionalidades de catálogo/loja que o sistema suporta:
 * formas de pagamento, pedido mínimo, atacado por produto, cupons (3
 * tipos), promoções, taxa de entrega por bairro e endereço da loja.
 *
 * Reaproveita os Services de domínio (não INSERT cru) para que eventos e
 * auditoria fiquem idênticos a uma operação real — replicando o mesmo truque
 * de "fingir" o contexto de tenant/usuário do DemoTenantSeeder
 * (app()->instance('tenant', ...) + Auth::setUser($owner)).
 *
 * DESVIO DELIBERADO (justificado): `wholesale_min_quantity`/`wholesale_price`
 * são colunas `fillable` de Product lidas pelo checkout público
 * (StorefrontCheckoutService) e pelo StorefrontProductResource, mas NÃO estão
 * mapeadas em CreateProductDTO/UpdateProductDTO nem em ProductService — não há
 * caminho de Service/DTO que as escreva. Como esta é carga de dados (não
 * feature nova) e a tarefa exige demonstrar atacado por produto, elas são
 * gravadas via Eloquent direto (`Product::update`, respeitando fillable +
 * updated_by do BaseModel) sob o contexto de auth já simulado. Isso não
 * dispara o evento ProductUpdated de auditoria — aceitável para carga.
 *
 * Idempotente onde razoável: promoções/taxa/endereço são upsert por
 * natureza; produtos novos e cupons checam existência antes de criar.
 */
class StoreCatalogDemoSeeder extends Seeder
{
    private const TENANT_SLUG = '10k-atacadista';

    /** Atacado por produto: nome => [qtd mínima, preço atacado] (preço < varejo já cadastrado). */
    private array $wholesale = [
        'Coca-Cola Lata 350 ml' => [24, 5.00],
        'Coca-Cola Garrafa 2 L' => [12, 10.50],
        'Guaraná Antarctica Lata 350 ml' => [24, 4.50],
        'Fanta Laranja Lata 350 ml' => [24, 4.50],
        'Água Mineral sem Gás 500 ml' => [50, 2.80],
    ];

    /** Produtos novos SÓ varejo (sem atacado) — demonstra os dois cenários lado a lado. */
    private array $retailOnly = [
        ['name' => 'Red Bull Energético Lata 250 ml', 'type' => 'Refrigerante', 'price' => 9.90, 'sku' => 'REDB-LT-250', 'brand' => 'Red Bull'],
        ['name' => 'Água Tônica Antarctica Lata 350 ml', 'type' => 'Refrigerante', 'price' => 4.50, 'sku' => 'TONI-LT-350', 'brand' => 'Antarctica'],
        ['name' => 'Água com Gás 500 ml', 'type' => 'Água', 'price' => 4.00, 'sku' => 'AGUAG-500', 'brand' => 'Fonte Pura'],
    ];

    /** Promoção "de/por": nome do produto => promo_price (< price). */
    private array $promotions = [
        'Coca-Cola Garrafa 2 L' => 9.90,
        'Guaraná Antarctica Garrafa 2 L' => 8.90,
        'Sprite Lata 350 ml' => 4.49,
    ];

    public function run(): void
    {
        $tenant = Tenant::where('slug', self::TENANT_SLUG)->first();

        if (!$tenant) {
            throw new RuntimeException(
                'StoreCatalogDemoSeeder: tenant "' . self::TENANT_SLUG . '" não encontrado — nada foi alterado.'
            );
        }

        $owner = User::find($tenant->created_by)
            ?? User::whereIn('id', DB::table('tenant_users')->where('tenant_id', $tenant->id)->pluck('user_id'))->first();

        if (!$owner) {
            throw new RuntimeException(
                'StoreCatalogDemoSeeder: dono do tenant ' . $tenant->id . ' não encontrado — nada foi alterado.'
            );
        }

        Auth::setUser($owner);
        app()->instance('tenant', $tenant);
        app()->instance('tenant_id', $tenant->id);

        DB::transaction(function () use ($tenant) {
            $this->configureSettings($tenant);
            $this->applyWholesale($tenant);
            $this->createRetailOnlyProducts($tenant);
            $this->createCoupons($tenant);
            $this->applyPromotions($tenant);
            $this->ensureDeliveryFees($tenant);
            $this->ensureStoreAddress($tenant);
        });

        $this->command?->info('StoreCatalogDemoSeeder: OK — catálogo/loja do "' . $tenant->name . '" populado.');
    }

    private function configureSettings(Tenant $tenant): void
    {
        $current = app(TenantSettingsService::class)->getForTenant($tenant->id);

        app(TenantSettingsService::class)->update($tenant->id, UpdateTenantSettingsDTO::fromArray([
            // Preserva os flags operacionais já configurados.
            'send_tracking_link_whatsapp' => (bool) $current->send_tracking_link_whatsapp,
            'block_order_without_stock' => (bool) $current->block_order_without_stock,
            'minimum_order_value' => 100.00,
            'estimated_preparation_minutes' => 30,
            'accepted_payment_methods' => ['cash', 'pix', 'credit_card', 'debit_card'],
        ]));

        $this->command?->info('  settings: 4 formas de pagamento + pedido mínimo R$100,00.');
    }

    private function applyWholesale(Tenant $tenant): void
    {
        foreach ($this->wholesale as $name => [$minQty, $price]) {
            $product = $this->findProduct($tenant, $name);
            if (!$product) {
                continue;
            }

            // Ver nota da classe: colunas de atacado não têm caminho de
            // Service/DTO — gravadas via Eloquent (fillable + updated_by).
            $product->update([
                'wholesale_min_quantity' => $minQty,
                'wholesale_price' => $price,
            ]);
        }

        $this->command?->info('  atacado: ' . count($this->wholesale) . ' produtos com preço/qtd de atacado.');
    }

    private function createRetailOnlyProducts(Tenant $tenant): void
    {
        $service = app(ProductService::class);
        $created = 0;

        foreach ($this->retailOnly as $p) {
            if ($this->findProduct($tenant, $p['name'])) {
                continue;
            }

            $type = \App\Models\Product\ProductType::where('tenant_id', $tenant->id)
                ->where('name', $p['type'])
                ->whereNull('deleted_at')
                ->firstOrFail();

            $service->create(CreateProductDTO::fromArray([
                'name' => $p['name'],
                'product_type_uuid' => $type->uuid,
                'price' => $p['price'],
                'description' => $p['name'] . ' — ' . $p['brand'],
                'is_available' => true,
                'sku' => $p['sku'],
                'brand' => $p['brand'],
                'unit' => 'un',
                'min_stock' => 10,
            ], $tenant->id));

            $created++;
        }

        $this->command?->info('  varejo: ' . $created . ' produtos novos só-varejo (sem atacado).');
    }

    private function createCoupons(Tenant $tenant): void
    {
        $service = app(CouponService::class);

        $coupons = [
            [
                'code' => 'BEMVINDO10',
                'type' => 'percentage',
                'value' => 10,
                'minimum_order_value' => 80.00,
                'max_uses_per_customer' => 1,
                'expires_at' => Carbon::now()->addDays(60)->toDateTimeString(),
                'is_active' => true,
            ],
            [
                'code' => 'DESCONTO20',
                'type' => 'fixed',
                'value' => 20.00,
                'minimum_order_value' => 150.00,
                'expires_at' => Carbon::now()->addDays(90)->toDateTimeString(),
                'is_active' => true,
            ],
            [
                'code' => 'FRETEGRATIS',
                'type' => 'free_shipping',
                'minimum_order_value' => 120.00,
                'expires_at' => Carbon::now()->addDays(45)->toDateTimeString(),
                'is_active' => true,
            ],
        ];

        $created = 0;

        foreach ($coupons as $c) {
            $exists = Coupon::where('tenant_id', $tenant->id)
                ->where('code', $c['code'])
                ->whereNull('deleted_at')
                ->exists();

            if ($exists) {
                continue;
            }

            $service->create($tenant->id, CreateCouponDTO::fromArray($c));
            $created++;
        }

        $this->command?->info('  cupons: ' . $created . ' criados (percentage/fixed/free_shipping).');
    }

    private function applyPromotions(Tenant $tenant): void
    {
        $service = app(ProductPromotionService::class);

        foreach ($this->promotions as $name => $promoPrice) {
            $product = $this->findProduct($tenant, $name);
            if (!$product) {
                continue;
            }

            $service->upsert(
                $tenant->id,
                $product->uuid,
                $promoPrice,
                Carbon::now()->toDateTimeString(),
                Carbon::now()->addDays(20)->toDateTimeString(),
            );
        }

        $this->command?->info('  promoções: ' . count($this->promotions) . ' produtos com preço promocional.');
    }

    private function ensureDeliveryFees(Tenant $tenant): void
    {
        $existing = app(StoreDeliveryFeeService::class)->list($tenant->id);

        if ($existing->isNotEmpty()) {
            $this->command?->info('  taxa de entrega: já configurada (' . $existing->count() . ' bairros) — mantida.');

            return;
        }

        $bairros = DB::table('final_customer_tenant_links')
            ->join('enderecos', 'enderecos.id', '=', 'final_customer_tenant_links.endereco_id')
            ->join('bairros', 'bairros.id', '=', 'enderecos.bairro_id')
            ->where('final_customer_tenant_links.tenant_id', $tenant->id)
            ->distinct()
            ->pluck('bairros.uuid');

        foreach ($bairros as $bairroUuid) {
            app(StoreDeliveryFeeService::class)->upsert($tenant->id, $bairroUuid, 8.00);
        }

        $this->command?->info('  taxa de entrega: ' . $bairros->count() . ' bairros a R$8,00.');
    }

    private function ensureStoreAddress(Tenant $tenant): void
    {
        if ($tenant->endereco_id) {
            $this->command?->info('  endereço da loja: já configurado — mantido.');

            return;
        }

        $bairro = DB::table('bairros')
            ->join('cidades', 'cidades.id', '=', 'bairros.cidade_id')
            ->join('estados', 'estados.id', '=', 'cidades.estado_id')
            ->where('cidades.name', 'Araraquara')
            ->where('bairros.name', 'Centro')
            ->select('bairros.uuid as bairro_uuid', 'cidades.uuid as cidade_uuid', 'estados.uuid as estado_uuid')
            ->first();

        if (!$bairro) {
            $this->command?->warn('  endereço da loja: bairro Centro/Araraquara não encontrado — pulado.');

            return;
        }

        app(StoreAddressService::class)->updateForTenant($tenant->id, [
            'logradouro' => 'Avenida Portugal',
            'numero' => '1500',
            'complemento' => 'Galpão A',
            'cep' => '14801-320',
            'is_active' => true,
            'estado_uuid' => $bairro->estado_uuid,
            'cidade_uuid' => $bairro->cidade_uuid,
            'bairro_uuid' => $bairro->bairro_uuid,
        ]);

        $this->command?->info('  endereço da loja: Avenida Portugal, 1500 — Centro, Araraquara/SP (geocoding disparado).');
    }

    private function findProduct(Tenant $tenant, string $name): ?Product
    {
        return Product::where('tenant_id', $tenant->id)
            ->where('name', $name)
            ->whereNull('deleted_at')
            ->first();
    }
}
