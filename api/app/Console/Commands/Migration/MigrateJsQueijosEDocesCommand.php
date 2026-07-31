<?php

namespace App\Console\Commands\Migration;

use App\Models\Location\Bairro;
use App\Models\Location\Cidade;
use App\Models\Location\Estado;
use App\Models\Product\ProductCategory;
use App\Models\Product\ProductType;
use App\Models\Stock\StockLocation;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantRole;
use App\Models\Tenant\TenantUser;
use App\Models\User\User;
use App\Services\Stock\StockLocationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ETL de uso único (Fase 8): migra os dados reais de UM negócio do banco
 * legado (`estabelecimento_id` fixo abaixo, base `legacy` = staging com o
 * dump de produção importado) para o schema novo (`tenant` criado por este
 * comando). Não reaproveita Services de aplicação viva (ClientService,
 * ProductService, OrderService, TenantService) — eles simulam fluxo de
 * negócio ao vivo (reserva de estoque, Auth::id(), eventos de auditoria
 * por ação do usuário) inadequado para carga histórica em massa. Faz
 * inserts diretos, em chunks para as tabelas de maior volume.
 *
 * Decisões de negócio já confirmadas com o usuário (ver
 * .claude/memory/architecture-decisions.md):
 * - Estoque novo começa zerado — `produto.quantidade` do legado é ignorado,
 *   nenhum StockMovement/StockBalance é criado por este comando.
 * - `Order.total_amount` = `pedido.valor_total` do legado tal como está
 *   (fonte de verdade financeira), NUNCA recalculado a partir da soma dos
 *   itens — ~37% dos pedidos reais divergem (bug de casa decimal em parte
 *   dos casos, desconto não registrado por item em outros).
 * - `paid_at`/`delivered_at` ficam null quando a flag é true mas a data
 *   está ausente no legado (5 e 4 casos respectivamente, <0,03% do total).
 * - Pedidos sem nenhum item (`pedido_produto`) são excluídos da migração.
 *
 * Achado de modelagem (não é decisão de negócio, é técnico): no legado,
 * `endereco` é compartilhado entre múltiplos `cliente` (vários clientes na
 * mesma rua apontam pro mesmo `endereco.id`), e `numero`/`complemento`
 * vivem no `cliente`, não no `endereco`. No sistema novo, `Endereco` é 1:1
 * com `Client` (todo fluxo de criação de Client sempre cria seu próprio
 * Endereco). Por isso este comando cria um Endereco NOVO e exclusivo para
 * cada Client migrado, mesmo duplicando logradouro/cep/estado/cidade/bairro
 * entre clientes vizinhos — é o mesmo padrão que a tela normal já gera.
 */
class MigrateJsQueijosEDocesCommand extends Command
{
    protected $signature = 'migration:js-queijos-e-doces {--fresh : remove o tenant migrado anteriormente (slug fixo) e refaz do zero}';

    protected $description = 'ETL único: migra os dados reais do estabelecimento "Js Queijos e Doces" (legado) para um Tenant novo.';

    private const LEGACY_ESTABELECIMENTO_ID = 4;
    private const TENANT_SLUG = 'js-queijos-e-doces';
    private const TENANT_NAME = 'Js Queijos e Doces';

    /**
     * O legado só guarda o nome por extenso do estado (`estado.nome`,
     * ex.: "São Paulo"), sem sigla — o schema novo exige `uf` (unique).
     */
    private const BRAZILIAN_STATE_UF_BY_NAME = [
        'Acre' => 'AC',
        'Alagoas' => 'AL',
        'Amapá' => 'AP',
        'Amazonas' => 'AM',
        'Bahia' => 'BA',
        'Ceará' => 'CE',
        'Distrito Federal' => 'DF',
        'Espírito Santo' => 'ES',
        'Goiás' => 'GO',
        'Maranhão' => 'MA',
        'Mato Grosso' => 'MT',
        'Mato Grosso do Sul' => 'MS',
        'Minas Gerais' => 'MG',
        'Pará' => 'PA',
        'Paraíba' => 'PB',
        'Paraná' => 'PR',
        'Pernambuco' => 'PE',
        'Piauí' => 'PI',
        'Rio de Janeiro' => 'RJ',
        'Rio Grande do Norte' => 'RN',
        'Rio Grande do Sul' => 'RS',
        'Rondônia' => 'RO',
        'Roraima' => 'RR',
        'Santa Catarina' => 'SC',
        'São Paulo' => 'SP',
        'Sergipe' => 'SE',
        'Tocantins' => 'TO',
    ];

    public function handle(): int
    {
        $legacy = DB::connection('legacy');

        if ($this->option('fresh')) {
            $this->deleteExistingTenant();
        } elseif (Tenant::where('slug', self::TENANT_SLUG)->exists()) {
            $this->error('Tenant "' . self::TENANT_SLUG . '" já existe. Use --fresh para recriar do zero.');

            return self::FAILURE;
        }

        $stats = [];

        DB::transaction(function () use ($legacy, &$stats) {
            $this->info('1/8 — Criando tenant, role Owner e local de estoque padrão...');
            [$tenant, $ownerRole, $defaultLocation] = $this->migrateTenantAndOwner();
            $stats['tenant_uuid'] = $tenant->uuid;

            $this->info('2/8 — Migrando usuários...');
            $stats['users'] = $this->migrateUsers($legacy, $tenant, $ownerRole);

            $this->info('3/8 — Migrando localização global (Estado/Cidade/Bairro)...');
            [$estadoMap, $cidadeMap, $bairroMap, $locStats] = $this->migrateLocations($legacy);
            $stats = array_merge($stats, $locStats);

            $stats = array_merge($stats, [
                'dia_ideal_migrados' => 0,
                'periodo_ideal_migrados' => 0,
            ]);

            $this->info('5/8 — Migrando categorias e tipos de produto...');
            [$categoryMap, $typeMap, $catStats] = $this->migrateProductCategoriesAndTypes($legacy, $tenant);
            $stats = array_merge($stats, $catStats);

            $this->info('6/8 — Migrando produtos (com imagens)...');
            [$productMap, $productStats] = $this->migrateProducts($legacy, $tenant, $typeMap);
            $stats = array_merge($stats, $productStats);

            $this->info('7/8 — Migrando clientes + endereços...');
            [$clientMap, $clientStats] = $this->migrateClientsAndEnderecos(
                $legacy,
                $tenant,
                $estadoMap,
                $cidadeMap,
                $bairroMap
            );
            $stats = array_merge($stats, $clientStats);

            $this->info('8/8 — Migrando pedidos + itens...');
            $orderStats = $this->migrateOrders($legacy, $tenant, $defaultLocation, $clientMap, $productMap);
            $stats = array_merge($stats, $orderStats);
        });

        $this->printSummary($stats);

        return self::SUCCESS;
    }

    private function deleteExistingTenant(): void
    {
        $tenant = Tenant::where('slug', self::TENANT_SLUG)->first();

        if (!$tenant) {
            return;
        }

        $this->warn('Removendo tenant existente "' . self::TENANT_SLUG . '" (uuid ' . $tenant->uuid . ') antes de recriar...');

        DB::transaction(function () use ($tenant) {
            // FKs tenant-scoped já têm cascadeOnDelete no schema, exceto
            // clients/enderecos onde o delete físico do tenant não passa
            // por SoftDeletes — forceDelete explícito garante limpeza real
            // em staging entre execuções repetidas do --fresh.
            DB::table('order_items')->where('tenant_id', $tenant->id)->delete();
            DB::table('orders')->where('tenant_id', $tenant->id)->delete();
            DB::table('clients')->where('tenant_id', $tenant->id)->delete();
            DB::table('enderecos')->where('tenant_id', $tenant->id)->delete();
            DB::table('products')->where('tenant_id', $tenant->id)->delete();
            DB::table('product_types')->where('tenant_id', $tenant->id)->delete();
            DB::table('product_categories')->where('tenant_id', $tenant->id)->delete();
            DB::table('stock_locations')->where('tenant_id', $tenant->id)->delete();
            DB::table('tenant_role_permissions')
                ->whereIn('tenant_role_id', TenantRole::where('tenant_id', $tenant->id)->pluck('id'))
                ->delete();
            DB::table('tenant_users')->where('tenant_id', $tenant->id)->delete();
            DB::table('tenant_roles')->where('tenant_id', $tenant->id)->delete();
            DB::table('tenants')->where('id', $tenant->id)->delete();
        });
    }

    /**
     * @return array{0: Tenant, 1: TenantRole, 2: StockLocation}
     */
    private function migrateTenantAndOwner(): array
    {
        $tenant = Tenant::create([
            'name' => self::TENANT_NAME,
            'slug' => self::TENANT_SLUG,
            'is_active' => true,
        ]);

        $ownerRole = TenantRole::create([
            'tenant_id' => $tenant->id,
            'name' => 'Proprietário',
            'slug' => 'owner',
            'is_active' => true,
        ]);

        // Reaproveita o comando idempotente já existente em vez de
        // duplicar o loop de concessão de Functionality×Action.
        $this->call('tenants:sync-permissions', ['--tenant' => $tenant->uuid]);

        // Efeito equivalente ao listener CreateDefaultStockLocation, sem
        // disparar o evento TenantCreated (o construtor exige actorId
        // int não-nulo — não há usuário autenticado neste contexto de
        // Artisan command).
        $defaultLocation = app(StockLocationService::class)->ensureDefaultLocation($tenant->id);

        return [$tenant, $ownerRole, $defaultLocation];
    }

    private function migrateUsers($legacy, Tenant $tenant, TenantRole $ownerRole): int
    {
        $rows = $legacy->table('usuario')
            ->where('estabelecimento_id', self::LEGACY_ESTABELECIMENTO_ID)
            ->get();

        $created = 0;

        foreach ($rows as $row) {
            // Reaproveita o User global existente (ex.: execução anterior
            // com --fresh já criou este e-mail e só apagou o vínculo do
            // tenant, não o usuário) em vez de pular a linha inteira —
            // senão o vínculo TenantUser nunca seria recriado num re-run.
            $user = User::where('email', $row->email)->first();

            if (!$user) {
                $user = User::create([
                    'uuid' => (string) Str::uuid(),
                    'name' => $row->nome,
                    'email' => $row->email,
                    'password' => $row->password,
                    'is_active' => (bool) $row->ativo,
                ]);
            }

            if (!TenantUser::where('tenant_id', $tenant->id)->where('user_id', $user->id)->exists()) {
                TenantUser::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'tenant_role_id' => $ownerRole->id,
                    'is_active' => true,
                ]);
            }

            $created++;
        }

        return $created;
    }

    /**
     * @return array{0: array<int,int>, 1: array<int,int>, 2: array<int,int>, 3: array<string,int>}
     */
    private function migrateLocations($legacy): array
    {
        $enderecos = $legacy->table('endereco as e')
            ->join('cliente as c', 'c.endereco_id', '=', 'e.id')
            ->where('c.estabelecimento_id', self::LEGACY_ESTABELECIMENTO_ID)
            ->select('e.id', 'e.estado_id', 'e.cidade_id', 'e.bairro_id')
            ->distinct()
            ->get();

        $estadoIds = $enderecos->pluck('estado_id')->unique();
        $cidadeIds = $enderecos->pluck('cidade_id')->unique();
        $bairroIds = $enderecos->pluck('bairro_id')->unique();

        $legacyEstados = $legacy->table('estado')->whereIn('id', $estadoIds)->get()->keyBy('id');
        $legacyCidades = $legacy->table('cidade')->whereIn('id', $cidadeIds)->get()->keyBy('id');
        $legacyBairros = $legacy->table('bairro')->whereIn('id', $bairroIds)->get()->keyBy('id');

        $estadoMap = [];
        $estadosCriados = 0;
        foreach ($legacyEstados as $legacyId => $row) {
            $uf = self::BRAZILIAN_STATE_UF_BY_NAME[$row->nome] ?? null;

            if (!$uf) {
                $this->error("Estado legado id={$legacyId} nome=\"{$row->nome}\": UF não reconhecida no mapa de estados brasileiros. Pulado — endereços que dependem dele serão pulados também.");

                continue;
            }

            // Global (sem tenant_id) — firstOrCreate por uf (unique real)
            // pra não colidir se o comando rodar de novo ou outro tenant
            // já tiver cadastrado o mesmo estado.
            $estado = Estado::firstOrCreate(
                ['uf' => $uf],
                ['name' => $row->nome, 'is_active' => true]
            );

            if ($estado->wasRecentlyCreated) {
                $estadosCriados++;
            }

            $estadoMap[$legacyId] = $estado->id;
        }

        $cidadeMap = [];
        $cidadesCriadas = 0;
        foreach ($legacyCidades as $legacyId => $row) {
            $novoEstadoId = $estadoMap[$row->estado_id] ?? null;

            if (!$novoEstadoId) {
                $this->warn("Cidade legada id={$legacyId}: estado_id={$row->estado_id} não mapeado, pulada.");

                continue;
            }

            $cidade = Cidade::firstOrCreate(
                ['estado_id' => $novoEstadoId, 'name' => $row->nome],
                ['is_active' => true]
            );

            if ($cidade->wasRecentlyCreated) {
                $cidadesCriadas++;
            }

            $cidadeMap[$legacyId] = $cidade->id;
        }

        $bairroMap = [];
        $bairrosCriados = 0;
        foreach ($legacyBairros as $legacyId => $row) {
            $novaCidadeId = $cidadeMap[$row->cidade_id] ?? null;

            if (!$novaCidadeId) {
                $this->warn("Bairro legado id={$legacyId}: cidade_id={$row->cidade_id} não mapeada, pulado.");

                continue;
            }

            $bairro = Bairro::firstOrCreate(
                ['cidade_id' => $novaCidadeId, 'name' => $row->nome],
                ['is_active' => true]
            );

            if ($bairro->wasRecentlyCreated) {
                $bairrosCriados++;
            }

            $bairroMap[$legacyId] = $bairro->id;
        }

        return [$estadoMap, $cidadeMap, $bairroMap, [
            'estados_criados' => $estadosCriados,
            'cidades_criadas' => $cidadesCriadas,
            'bairros_criados' => $bairrosCriados,
        ]];
    }

    /**
     * @return array{0: array<int,int>, 1: array<int,int>, 2: array<string,int>}
     */
    private function migrateProductCategoriesAndTypes($legacy, Tenant $tenant): array
    {
        $categoryMap = [];
        foreach ($legacy->table('categoria_produto')->where('estabelecimento_id', self::LEGACY_ESTABELECIMENTO_ID)->get() as $row) {
            $category = ProductCategory::create([
                'tenant_id' => $tenant->id,
                'name' => $row->nome,
                'priority' => $row->prioridade,
                'is_active' => (bool) $row->ativo,
            ]);
            $categoryMap[$row->id] = $category->id;
        }

        $typeMap = [];
        foreach ($legacy->table('tipo_produto')->where('estabelecimento_id', self::LEGACY_ESTABELECIMENTO_ID)->get() as $row) {
            $novaCategoriaId = $categoryMap[$row->categoria_produto_id] ?? null;

            if (!$novaCategoriaId) {
                $this->warn("Tipo de produto legado id={$row->id}: categoria_produto_id={$row->categoria_produto_id} não mapeada, pulado.");

                continue;
            }

            $type = ProductType::create([
                'tenant_id' => $tenant->id,
                'product_category_id' => $novaCategoriaId,
                'name' => $row->nome,
                'priority' => $row->prioridade,
                'is_active' => (bool) $row->ativo,
            ]);
            $typeMap[$row->id] = $type->id;
        }

        return [$categoryMap, $typeMap, [
            'product_categories_migradas' => count($categoryMap),
            'product_types_migrados' => count($typeMap),
        ]];
    }

    /**
     * @return array{0: array<int,int>, 1: array<string,int>}
     */
    private function migrateProducts($legacy, Tenant $tenant, array $typeMap): array
    {
        $productMap = [];
        $criados = 0;
        $inativos = 0;
        $imagensSalvas = 0;
        $imagensInvalidas = 0;

        $rows = $legacy->table('produto')
            ->where('estabelecimento_id', self::LEGACY_ESTABELECIMENTO_ID)
            ->get();

        foreach ($rows as $row) {
            $novoTipoId = $typeMap[$row->tipo_produto_id] ?? null;

            if (!$novoTipoId) {
                $this->warn("Produto legado id={$row->id}: tipo_produto_id={$row->tipo_produto_id} não mapeado, pulado.");

                continue;
            }

            $uuid = (string) Str::uuid();
            $imageData = null;
            $imageMime = null;

            if (!empty($row->imagem)) {
                $imageMime = $this->detectImageMime($row->imagem);

                if ($imageMime) {
                    $imageData = $row->imagem;
                    $imagensSalvas++;
                } else {
                    $imagensInvalidas++;
                }
            }

            $productId = DB::table('products')->insertGetId([
                'uuid' => $uuid,
                'tenant_id' => $tenant->id,
                'product_type_id' => $novoTipoId,
                'name' => $row->nome,
                'sku' => null,
                'barcode' => null,
                'brand' => null,
                'unit' => 'un',
                'price' => $row->valor,
                'description' => $row->descricao,
                'image_data' => $imageData,
                'image_mime' => $imageMime,
                'is_available' => (bool) $row->disponivel,
                'stock_quantity' => 0,
                'surcharge_rate' => $row->taxa_acrescimo,
                'is_lot_controlled' => false,
                'is_expiry_controlled' => false,
                'is_serial_controlled' => false,
                'min_stock' => null,
                'max_stock' => null,
                'reorder_point' => null,
                'reorder_qty' => null,
                'last_purchase_cost' => null,
                'created_at' => $row->inclusao_data ?? now(),
                'updated_at' => $row->alteracao_data ?? $row->inclusao_data ?? now(),
                'deleted_at' => !$row->ativo ? ($row->alteracao_data ?? now()) : null,
            ]);

            $productMap[$row->id] = $productId;
            $criados++;

            if (!$row->ativo) {
                $inativos++;
            }
        }

        return [$productMap, [
            'produtos_migrados' => $criados,
            'produtos_inativos' => $inativos,
            'produtos_imagens_salvas' => $imagensSalvas,
            'produtos_imagens_invalidas' => $imagensInvalidas,
        ]];
    }

    /**
     * O legado tem alguns registros com data corrompida (achado real: 6
     * pedidos com data_pagamento tipo "0223-02-20" — falta um dígito no
     * ano, vira ano 223, fora do range válido do DATETIME do MySQL, o que
     * quebraria o insert inteiro). Mesmo princípio já validado para data
     * ausente (flag booleana é a fonte de verdade de negócio, a data é
     * só um detalhe — fica null quando não é confiável), estendido pra
     * data presente mas implausível.
     */
    private function sanitizeDate(?string $value, int $legacyPedidoId, string $field): ?string
    {
        if (!$value) {
            return null;
        }

        $year = (int) substr($value, 0, 4);

        if ($year < 2000 || $year > (int) now()->format('Y') + 1) {
            $this->warn("Pedido legado id={$legacyPedidoId}: {$field}=\"{$value}\" fora de um range plausível, tratado como ausente.");

            return null;
        }

        return $value;
    }

    private function detectImageMime(string $blob): ?string
    {
        $info = @getimagesizefromstring($blob);

        if ($info === false) {
            return null;
        }

        return match ($info['mime'] ?? null) {
            'image/jpeg', 'image/png', 'image/gif', 'image/webp' => $info['mime'],
            default => null,
        };
    }

    /**
     * @return array{0: array<int,int>, 1: array<string,int>}
     */
    private function migrateClientsAndEnderecos(
        $legacy,
        Tenant $tenant,
        array $estadoMap,
        array $cidadeMap,
        array $bairroMap
    ): array {
        $clientMap = [];
        $criados = 0;
        $inativos = 0;
        $semEnderecoMapeado = 0;

        $legacyEnderecos = $legacy->table('endereco')->get()->keyBy('id');

        $legacy->table('cliente')
            ->where('estabelecimento_id', self::LEGACY_ESTABELECIMENTO_ID)
            ->orderBy('id')
            ->chunk(500, function ($clientes) use (
                &$clientMap,
                &$criados,
                &$inativos,
                &$semEnderecoMapeado,
                $tenant,
                $estadoMap,
                $cidadeMap,
                $bairroMap,
                $legacyEnderecos
            ) {
                foreach ($clientes as $row) {
                    $legacyEndereco = $legacyEnderecos->get($row->endereco_id);

                    if (!$legacyEndereco) {
                        $this->warn("Cliente legado id={$row->id}: endereco_id={$row->endereco_id} não encontrado, pulado.");
                        $semEnderecoMapeado++;

                        continue;
                    }

                    $novoEstadoId = $estadoMap[$legacyEndereco->estado_id] ?? null;
                    $novaCidadeId = $cidadeMap[$legacyEndereco->cidade_id] ?? null;
                    $novoBairroId = $bairroMap[$legacyEndereco->bairro_id] ?? null;

                    if (!$novoEstadoId || !$novaCidadeId || !$novoBairroId) {
                        $this->warn("Cliente legado id={$row->id}: localização do endereço não mapeada, pulado.");
                        $semEnderecoMapeado++;

                        continue;
                    }

                    $deletedAt = !$row->ativo ? ($row->alteracao_data ?? now()) : null;
                    $createdAt = $row->inclusao_data ?? now();
                    $updatedAt = $row->alteracao_data ?? $createdAt;

                    $enderecoId = DB::table('enderecos')->insertGetId([
                        'uuid' => (string) Str::uuid(),
                        'tenant_id' => $tenant->id,
                        'estado_id' => $novoEstadoId,
                        'cidade_id' => $novaCidadeId,
                        'bairro_id' => $novoBairroId,
                        'logradouro' => $legacyEndereco->logradouro,
                        'numero' => (string) $row->numero,
                        'complemento' => $row->complemento,
                        'cep' => $legacyEndereco->cep,
                        'is_active' => (bool) $row->ativo,
                        'created_at' => $createdAt,
                        'updated_at' => $updatedAt,
                        'deleted_at' => $deletedAt,
                    ]);

                    $clientId = DB::table('clients')->insertGetId([
                        'uuid' => (string) Str::uuid(),
                        'tenant_id' => $tenant->id,
                        'name' => $row->nome,
                        'endereco_id' => $enderecoId,
                        'phone_primary' => $row->telefone_principal,
                        'phone_secondary' => $row->telefone_secundario,
                        'notes' => $row->observacao,
                        'is_trusted' => (bool) $row->confianca,
                        'is_active' => (bool) $row->ativo,
                        'created_at' => $createdAt,
                        'updated_at' => $updatedAt,
                        'deleted_at' => $deletedAt,
                    ]);

                    $clientMap[$row->id] = $clientId;
                    $criados++;

                    if (!$row->ativo) {
                        $inativos++;
                    }
                }
            });

        return [$clientMap, [
            'clientes_migrados' => $criados,
            'clientes_inativos' => $inativos,
            'clientes_pulados_sem_endereco' => $semEnderecoMapeado,
        ]];
    }

    /**
     * @return array<string,int>
     */
    private function migrateOrders(
        $legacy,
        Tenant $tenant,
        StockLocation $defaultLocation,
        array $clientMap,
        array $productMap
    ): array {
        $itemsByPedido = [];

        foreach (
            $legacy->table('pedido_produto as pp')
                ->join('pedido as p', 'p.id', '=', 'pp.pedido_id')
                ->where('p.estabelecimento_id', self::LEGACY_ESTABELECIMENTO_ID)
                ->select('pp.*')
                ->orderBy('pp.pedido_id')
                ->cursor() as $item
        ) {
            $itemsByPedido[$item->pedido_id][] = $item;
        }

        $ordersMigrated = 0;
        $ordersSkippedNoItems = 0;
        $ordersSkippedNoClient = 0;
        $ordersWithInstallmentFound = 0;
        $itemsMigrated = 0;
        $itemsSkippedNoProduct = 0;
        $excludedPedidoIds = [];

        $legacy->table('pedido')
            ->where('estabelecimento_id', self::LEGACY_ESTABELECIMENTO_ID)
            ->orderBy('id')
            ->chunk(500, function ($pedidos) use (
                &$ordersMigrated,
                &$ordersSkippedNoItems,
                &$ordersSkippedNoClient,
                &$ordersWithInstallmentFound,
                &$itemsMigrated,
                &$itemsSkippedNoProduct,
                &$excludedPedidoIds,
                $itemsByPedido,
                $tenant,
                $defaultLocation,
                $clientMap,
                $productMap
            ) {
                foreach ($pedidos as $pedido) {
                    if ((bool) $pedido->parcelado) {
                        $ordersWithInstallmentFound++;

                        continue;
                    }

                    $items = $itemsByPedido[$pedido->id] ?? [];

                    if (empty($items)) {
                        $ordersSkippedNoItems++;
                        $excludedPedidoIds[] = $pedido->id;

                        continue;
                    }

                    $novoClienteId = $clientMap[$pedido->cliente_id] ?? null;

                    if (!$novoClienteId) {
                        $ordersSkippedNoClient++;

                        continue;
                    }

                    $validItems = [];
                    foreach ($items as $item) {
                        $novoProdutoId = $productMap[$item->produto_id] ?? null;

                        if (!$novoProdutoId) {
                            $itemsSkippedNoProduct++;

                            continue;
                        }

                        $validItems[] = [$item, $novoProdutoId];
                    }

                    if (empty($validItems)) {
                        $ordersSkippedNoItems++;
                        $excludedPedidoIds[] = $pedido->id;

                        continue;
                    }

                    $createdAt = $pedido->inclusao_data ?? now();
                    $updatedAt = $pedido->alteracao_data ?? $createdAt;
                    $deletedAt = !$pedido->ativo ? ($pedido->alteracao_data ?? now()) : null;

                    $orderId = DB::table('orders')->insertGetId([
                        'uuid' => (string) Str::uuid(),
                        'tenant_id' => $tenant->id,
                        'client_id' => $novoClienteId,
                        'stock_location_id' => $defaultLocation->id,
                        'is_installment' => false,
                        'total_amount' => $pedido->valor_total,
                        'is_paid' => (bool) $pedido->pago,
                        'paid_at' => ($pedido->pago && $pedido->data_pagamento) ? $this->sanitizeDate($pedido->data_pagamento, $pedido->id, 'data_pagamento') : null,
                        'is_delivered' => (bool) $pedido->entregue,
                        'delivered_at' => ($pedido->entregue && $pedido->data_entrega) ? $this->sanitizeDate($pedido->data_entrega, $pedido->id, 'data_entrega') : null,
                        'due_date' => null,
                        'cancelled_at' => null,
                        'cancellation_reason' => null,
                        'notes' => $pedido->observacao,
                        'created_at' => $createdAt,
                        'updated_at' => $updatedAt,
                        'deleted_at' => $deletedAt,
                    ]);

                    $itemRows = [];
                    foreach ($validItems as [$item, $novoProdutoId]) {
                        $unitPrice = (float) $item->valor_momento_venda;
                        $quantity = (float) $item->quantidade_produto;

                        $itemRows[] = [
                            'uuid' => (string) Str::uuid(),
                            'tenant_id' => $tenant->id,
                            'order_id' => $orderId,
                            'product_id' => $novoProdutoId,
                            'quantity' => $quantity,
                            'unit_price' => $unitPrice,
                            'line_total' => round($unitPrice * $quantity, 2),
                            'created_at' => $item->inclusao_data ?? $createdAt,
                            'updated_at' => $item->alteracao_data ?? $item->inclusao_data ?? $updatedAt,
                            'deleted_at' => null,
                        ];
                    }

                    DB::table('order_items')->insert($itemRows);

                    $itemsMigrated += count($itemRows);
                    $ordersMigrated++;
                }
            });

        if ($ordersWithInstallmentFound > 0) {
            $this->error("ATENÇÃO: {$ordersWithInstallmentFound} pedido(s) com parcelado=1 encontrados — premissa de 0 pedidos parcelados quebrada. Migração de OrderInstallment NÃO foi implementada. Esses pedidos foram pulados, revisar antes de prosseguir.");
        }

        if (!empty($excludedPedidoIds)) {
            $this->warn('Pedidos legados excluídos por falta de item válido: ' . implode(', ', $excludedPedidoIds));
        }

        return [
            'pedidos_migrados' => $ordersMigrated,
            'pedidos_pulados_sem_item' => $ordersSkippedNoItems,
            'pedidos_pulados_sem_cliente' => $ordersSkippedNoClient,
            'pedidos_parcelados_encontrados' => $ordersWithInstallmentFound,
            'itens_migrados' => $itemsMigrated,
            'itens_pulados_sem_produto' => $itemsSkippedNoProduct,
        ];
    }

    private function printSummary(array $stats): void
    {
        $this->newLine();
        $this->info('=== Resumo da migração ===');

        foreach ($stats as $key => $value) {
            $this->line(str_pad($key, 40, '.') . ' ' . $value);
        }
    }
}
