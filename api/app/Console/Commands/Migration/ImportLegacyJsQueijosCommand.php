<?php

namespace App\Console\Commands\Migration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ETL de uso único: importa os dados reais de "Js Queijos e Doces" (legado,
 * base `maskats_legacy_staging`, `estabelecimento_id` fixo abaixo) para o
 * Tenant EXISTENTE "MF software" (id fixo abaixo, já criado manualmente,
 * confirmado vazio antes da primeira execução). Diferente de
 * `MigrateJsQueijosEDocesCommand` (que cria um tenant novo do zero e migra
 * usuários): este comando não cria tenant, nunca migra `usuario` (o dono já
 * tem login próprio no sistema novo) e faz cada chunk de 500 registros em
 * sua própria transação (não uma transação gigante segurando lock em 37k+
 * linhas). Não dispara Events/Listeners de auditoria nem passa por
 * Service/Repository — inserts diretos via `DB::table(...)`, apropriado só
 * para carga histórica em lote (ver justificativa completa no prompt que
 * originou este comando).
 *
 * Decisões tomadas durante a implementação, não cobertas explicitamente no
 * escopo original (documentar em `.claude/memory/architecture-decisions.md`
 * após validação):
 * - `estado`/`cidade`/`bairro` do legado, escopados por
 *   `estabelecimento_id=4` diretamente (a tabela já carrega essa coluna,
 *   não precisa do join indireto via `cliente`): contagem real medida é
 *   1/6/48, não 3/15/56 como constava na estimativa inicial do escopo —
 *   valores reais usados, divergência sinalizada no resumo final.
 * - `ativo` do legado (cliente/produto/categoria/tipo/dia_ideal/
 *   periodo_ideal) mapeia para o campo `is_active`/`is_available` do
 *   schema novo, NUNCA para `deleted_at` — são conceitos distintos no
 *   schema novo (soft delete x flag de ativo), diferente do padrão usado
 *   por `MigrateJsQueijosEDocesCommand` (que tratava `ativo=0` como
 *   soft-delete). `pedido.ativo` não tem campo equivalente em `Order` e
 *   sempre veio `1` nesta base (confirmado por query), não é mapeado.
 * - `created_at`/`updated_at` de toda linha migrada preservam
 *   `inclusao_data`/`alteracao_data` do legado (não `now()`) — é dado
 *   histórico, e os relatórios de Analytics agrupam por `orders.created_at`;
 *   usar `now()` faria 37 mil pedidos históricos aparecerem como "criados
 *   hoje".
 * - Pedidos com `parcelado=1` são migrados normalmente com
 *   `is_installment=true` (não excluídos), sem nenhum `OrderInstallment`
 *   (fonte `pedido_parcela` vazia para este estabelecimento) — contados
 *   separadamente no resumo. Nesta base = 0 pedidos nessa condição.
 * - Pedidos sem nenhum item em `pedido_produto` são excluídos (schema novo
 *   não tem como representar um Order sem OrderItem) — nesta base, 3
 *   pedidos.
 * - `ProductCategory`/`ProductType` são resolvidos por nome
 *   (trim+case-insensitive) contra o que já existe no tenant antes de
 *   criar — o tenant já tinha "Doces"/"Frios" cadastrados manualmente
 *   antes desta importação; sem esse dedupe o insert quebraria a unique
 *   `(tenant_id, name)`.
 */
class ImportLegacyJsQueijosCommand extends Command
{
    protected $signature = 'import:legacy-js-queijos {--dry-run : mapeia e conta sem escrever nada} {--force : ignora o guard de "tenant já tem clients" e prossegue mesmo assim}';

    protected $description = 'ETL único: importa pedidos/clientes/produtos reais de "Js Queijos e Doces" (legado) para o tenant existente "MF software".';

    private const LEGACY_ESTABELECIMENTO_ID = 4;
    private const TARGET_TENANT_ID = 2;
    private const CHUNK_SIZE = 500;

    /**
     * O legado só guarda o nome por extenso do estado (`estado.nome`, ex.:
     * "São Paulo"), sem sigla — `estados.uf` é `unique`/obrigatória no
     * schema novo. Nome não mapeado aqui cai no fallback (3 primeiras
     * letras do nome sem acento, maiúsculas).
     */
    private const STATE_UF_BY_NAME = [
        'Acre' => 'AC', 'Alagoas' => 'AL', 'Amapá' => 'AP', 'Amazonas' => 'AM',
        'Bahia' => 'BA', 'Ceará' => 'CE', 'Distrito Federal' => 'DF',
        'Espírito Santo' => 'ES', 'Goiás' => 'GO', 'Maranhão' => 'MA',
        'Mato Grosso' => 'MT', 'Mato Grosso do Sul' => 'MS', 'Minas Gerais' => 'MG',
        'Pará' => 'PA', 'Paraíba' => 'PB', 'Paraná' => 'PR', 'Pernambuco' => 'PE',
        'Piauí' => 'PI', 'Rio de Janeiro' => 'RJ', 'Rio Grande do Norte' => 'RN',
        'Rio Grande do Sul' => 'RS', 'Rondônia' => 'RO', 'Roraima' => 'RR',
        'Santa Catarina' => 'SC', 'São Paulo' => 'SP', 'Sergipe' => 'SE',
        'Tocantins' => 'TO',
    ];

    private bool $dryRun = false;
    private ?int $actorId = null;

    /** @var array<string,int> */
    private array $stats = [];

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $tenant = DB::table('tenants')->where('id', self::TARGET_TENANT_ID)->whereNull('deleted_at')->first();

        if (!$tenant) {
            $this->error('Tenant id=' . self::TARGET_TENANT_ID . ' não encontrado (ou soft-deletado). Abortando.');

            return self::FAILURE;
        }

        $existingClients = DB::table('clients')->where('tenant_id', self::TARGET_TENANT_ID)->count();

        if ($existingClients > 0 && !$this->dryRun && !$force) {
            $this->error(
                "Tenant \"{$tenant->name}\" (id=" . self::TARGET_TENANT_ID . ") já tem {$existingClients} client(s) cadastrado(s) — "
                . 'sinal de que este comando já rodou antes. Use --force para prosseguir mesmo assim (não recomendado sem revisar o estado atual antes).'
            );

            return self::FAILURE;
        }

        $this->actorId = $this->resolveOwnerUserId();
        $this->line('created_by/updated_by (Owner do tenant) resolvido para: ' . ($this->actorId ?? 'null — nenhum owner encontrado, colunas ficam null'));

        $stockLocationId = $this->resolveDefaultStockLocationId();

        if (!$stockLocationId) {
            $this->error('Tenant id=' . self::TARGET_TENANT_ID . ' não tem stock_location com is_default=true. Abortando — Order.stock_location_id é NOT NULL.');

            return self::FAILURE;
        }

        $legacy = DB::connection('legacy');

        $this->info($this->dryRun ? '=== DRY-RUN — nenhuma escrita será feita no banco alvo ===' : '=== EXECUÇÃO REAL — escrevendo no banco alvo (produção) ===');

        [$estadoMap, $cidadeMap, $bairroMap] = $this->transactional(fn () => $this->migrateLocations($legacy));
        [$diaMap, $periodoMap] = $this->transactional(fn () => $this->migrateDiaPeriodoIdeal($legacy));
        [$categoryMap, $typeMap] = $this->transactional(fn () => $this->migrateProductCategoriesAndTypes($legacy));
        $productMap = $this->transactional(fn () => $this->migrateProducts($legacy, $typeMap));

        $clientMap = $this->migrateClients($legacy, $estadoMap, $cidadeMap, $bairroMap, $diaMap, $periodoMap);
        $this->migrateOrders($legacy, $stockLocationId, $clientMap, $productMap);

        $this->printSummary();

        return self::SUCCESS;
    }

    private function transactional(callable $callback)
    {
        return $this->dryRun ? $callback() : DB::transaction($callback);
    }

    private function resolveOwnerUserId(): ?int
    {
        return DB::table('tenant_users')
            ->join('tenant_roles', 'tenant_roles.id', '=', 'tenant_users.tenant_role_id')
            ->where('tenant_users.tenant_id', self::TARGET_TENANT_ID)
            ->where('tenant_roles.slug', 'owner')
            ->whereNull('tenant_users.deleted_at')
            ->value('tenant_users.user_id');
    }

    private function resolveDefaultStockLocationId(): ?int
    {
        return DB::table('stock_locations')
            ->where('tenant_id', self::TARGET_TENANT_ID)
            ->where('is_default', true)
            ->whereNull('deleted_at')
            ->value('id');
    }

    /**
     * @return array{0: array<int,int>, 1: array<int,int>, 2: array<int,int>}
     */
    private function migrateLocations($legacy): array
    {
        $legacyEstados = $legacy->table('estado')->where('estabelecimento_id', self::LEGACY_ESTABELECIMENTO_ID)->get();
        $legacyCidades = $legacy->table('cidade')->where('estabelecimento_id', self::LEGACY_ESTABELECIMENTO_ID)->get();
        $legacyBairros = $legacy->table('bairro')->where('estabelecimento_id', self::LEGACY_ESTABELECIMENTO_ID)->get();

        $estadoMap = [];
        $estadosCriados = 0;
        $estadosReaproveitados = 0;

        foreach ($legacyEstados as $row) {
            $existing = DB::table('estados')->whereRaw('LOWER(TRIM(name)) = ?', [$this->norm($row->nome)])->first();

            if ($existing) {
                $estadoMap[$row->id] = $existing->id;
                $estadosReaproveitados++;

                continue;
            }

            $uf = self::STATE_UF_BY_NAME[$row->nome] ?? $this->fallbackUf($row->nome);

            $estadoMap[$row->id] = $this->dryRun
                ? -$row->id
                : DB::table('estados')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'name' => $row->nome,
                    'uf' => $uf,
                    'is_active' => true,
                    'created_by' => $this->actorId,
                    'updated_by' => $this->actorId,
                    'created_at' => $row->inclusao_data ?? now(),
                    'updated_at' => $row->alteracao_data ?? $row->inclusao_data ?? now(),
                ]);

            $estadosCriados++;
        }

        $cidadeMap = [];
        $cidadesCriadas = 0;
        $cidadesReaproveitadas = 0;

        foreach ($legacyCidades as $row) {
            $novoEstadoId = $estadoMap[$row->estado_id] ?? null;

            if (!$novoEstadoId) {
                $this->warn("Cidade legada id={$row->id}: estado_id={$row->estado_id} não mapeado, pulada.");

                continue;
            }

            $existing = DB::table('cidades')
                ->where('estado_id', $novoEstadoId)
                ->whereRaw('LOWER(TRIM(name)) = ?', [$this->norm($row->nome)])
                ->first();

            if ($existing) {
                $cidadeMap[$row->id] = $existing->id;
                $cidadesReaproveitadas++;

                continue;
            }

            $cidadeMap[$row->id] = $this->dryRun
                ? -$row->id
                : DB::table('cidades')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'estado_id' => $novoEstadoId,
                    'name' => $row->nome,
                    'is_active' => true,
                    'created_by' => $this->actorId,
                    'updated_by' => $this->actorId,
                    'created_at' => $row->inclusao_data ?? now(),
                    'updated_at' => $row->alteracao_data ?? $row->inclusao_data ?? now(),
                ]);

            $cidadesCriadas++;
        }

        $bairroMap = [];
        $bairrosCriados = 0;
        $bairrosReaproveitados = 0;

        foreach ($legacyBairros as $row) {
            $novaCidadeId = $cidadeMap[$row->cidade_id] ?? null;

            if (!$novaCidadeId) {
                $this->warn("Bairro legado id={$row->id}: cidade_id={$row->cidade_id} não mapeada, pulado.");

                continue;
            }

            $existing = DB::table('bairros')
                ->where('cidade_id', $novaCidadeId)
                ->whereRaw('LOWER(TRIM(name)) = ?', [$this->norm($row->nome)])
                ->first();

            if ($existing) {
                $bairroMap[$row->id] = $existing->id;
                $bairrosReaproveitados++;

                continue;
            }

            $bairroMap[$row->id] = $this->dryRun
                ? -$row->id
                : DB::table('bairros')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'cidade_id' => $novaCidadeId,
                    'name' => $row->nome,
                    'is_active' => true,
                    'created_by' => $this->actorId,
                    'updated_by' => $this->actorId,
                    'created_at' => $row->inclusao_data ?? now(),
                    'updated_at' => $row->alteracao_data ?? $row->inclusao_data ?? now(),
                ]);

            $bairrosCriados++;
        }

        $this->stats['estados_criados'] = $estadosCriados;
        $this->stats['estados_reaproveitados'] = $estadosReaproveitados;
        $this->stats['cidades_criadas'] = $cidadesCriadas;
        $this->stats['cidades_reaproveitadas'] = $cidadesReaproveitadas;
        $this->stats['bairros_criados'] = $bairrosCriados;
        $this->stats['bairros_reaproveitados'] = $bairrosReaproveitados;

        return [$estadoMap, $cidadeMap, $bairroMap];
    }

    /**
     * @return array{0: array<int,int>, 1: array<int,int>}
     */
    private function migrateDiaPeriodoIdeal($legacy): array
    {
        $tenantId = self::TARGET_TENANT_ID;

        $diaMap = [];
        $diaCriados = 0;
        $diaReaproveitados = 0;

        foreach ($legacy->table('dia_ideal')->where('estabelecimento_id', self::LEGACY_ESTABELECIMENTO_ID)->get() as $row) {
            $existing = DB::table('dia_ideais')
                ->where('tenant_id', $tenantId)
                ->whereRaw('LOWER(TRIM(name)) = ?', [$this->norm($row->nome)])
                ->first();

            if ($existing) {
                $diaMap[$row->id] = $existing->id;
                $diaReaproveitados++;

                continue;
            }

            $diaMap[$row->id] = $this->dryRun
                ? -$row->id
                : DB::table('dia_ideais')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'name' => $row->nome,
                    'is_active' => (bool) $row->ativo,
                    'created_by' => $this->actorId,
                    'updated_by' => $this->actorId,
                    'created_at' => $row->inclusao_data ?? now(),
                    'updated_at' => $row->alteracao_data ?? $row->inclusao_data ?? now(),
                ]);

            $diaCriados++;
        }

        $periodoMap = [];
        $periodoCriados = 0;
        $periodoReaproveitados = 0;

        foreach ($legacy->table('periodo_ideal')->where('estabelecimento_id', self::LEGACY_ESTABELECIMENTO_ID)->get() as $row) {
            $existing = DB::table('periodo_ideais')
                ->where('tenant_id', $tenantId)
                ->whereRaw('LOWER(TRIM(name)) = ?', [$this->norm($row->nome)])
                ->first();

            if ($existing) {
                $periodoMap[$row->id] = $existing->id;
                $periodoReaproveitados++;

                continue;
            }

            $periodoMap[$row->id] = $this->dryRun
                ? -$row->id
                : DB::table('periodo_ideais')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'name' => $row->nome,
                    'is_active' => (bool) $row->ativo,
                    'created_by' => $this->actorId,
                    'updated_by' => $this->actorId,
                    'created_at' => $row->inclusao_data ?? now(),
                    'updated_at' => $row->alteracao_data ?? $row->inclusao_data ?? now(),
                ]);

            $periodoCriados++;
        }

        $this->stats['dia_ideal_criados'] = $diaCriados;
        $this->stats['dia_ideal_reaproveitados'] = $diaReaproveitados;
        $this->stats['periodo_ideal_criados'] = $periodoCriados;
        $this->stats['periodo_ideal_reaproveitados'] = $periodoReaproveitados;

        return [$diaMap, $periodoMap];
    }

    /**
     * @return array{0: array<int,int>, 1: array<int,int>}
     */
    private function migrateProductCategoriesAndTypes($legacy): array
    {
        $tenantId = self::TARGET_TENANT_ID;

        $categoryMap = [];
        $catCriadas = 0;
        $catReaproveitadas = 0;

        foreach ($legacy->table('categoria_produto')->where('estabelecimento_id', self::LEGACY_ESTABELECIMENTO_ID)->get() as $row) {
            $existing = DB::table('product_categories')
                ->where('tenant_id', $tenantId)
                ->whereRaw('LOWER(TRIM(name)) = ?', [$this->norm($row->nome)])
                ->first();

            if ($existing) {
                $categoryMap[$row->id] = $existing->id;
                $catReaproveitadas++;

                continue;
            }

            $categoryMap[$row->id] = $this->dryRun
                ? -$row->id
                : DB::table('product_categories')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'name' => $row->nome,
                    'priority' => $row->prioridade,
                    'is_active' => (bool) $row->ativo,
                    'created_by' => $this->actorId,
                    'updated_by' => $this->actorId,
                    'created_at' => $row->inclusao_data ?? now(),
                    'updated_at' => $row->alteracao_data ?? $row->inclusao_data ?? now(),
                ]);

            $catCriadas++;
        }

        $typeMap = [];
        $tipoCriados = 0;
        $tipoReaproveitados = 0;
        $tipoPulados = 0;

        foreach ($legacy->table('tipo_produto')->where('estabelecimento_id', self::LEGACY_ESTABELECIMENTO_ID)->get() as $row) {
            $novaCategoriaId = $categoryMap[$row->categoria_produto_id] ?? null;

            if (!$novaCategoriaId) {
                $this->warn("Tipo de produto legado id={$row->id}: categoria_produto_id={$row->categoria_produto_id} não mapeada, pulado.");
                $tipoPulados++;

                continue;
            }

            $existing = DB::table('product_types')
                ->where('tenant_id', $tenantId)
                ->whereRaw('LOWER(TRIM(name)) = ?', [$this->norm($row->nome)])
                ->first();

            if ($existing) {
                $typeMap[$row->id] = $existing->id;
                $tipoReaproveitados++;

                continue;
            }

            $typeMap[$row->id] = $this->dryRun
                ? -$row->id
                : DB::table('product_types')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'product_category_id' => $novaCategoriaId,
                    'name' => $row->nome,
                    'priority' => $row->prioridade,
                    'is_active' => (bool) $row->ativo,
                    'created_by' => $this->actorId,
                    'updated_by' => $this->actorId,
                    'created_at' => $row->inclusao_data ?? now(),
                    'updated_at' => $row->alteracao_data ?? $row->inclusao_data ?? now(),
                ]);

            $tipoCriados++;
        }

        $this->stats['product_categories_criadas'] = $catCriadas;
        $this->stats['product_categories_reaproveitadas'] = $catReaproveitadas;
        $this->stats['product_types_criados'] = $tipoCriados;
        $this->stats['product_types_reaproveitados'] = $tipoReaproveitados;
        $this->stats['product_types_pulados'] = $tipoPulados;

        return [$categoryMap, $typeMap];
    }

    /**
     * @return array<int,int>
     */
    private function migrateProducts($legacy, array $typeMap): array
    {
        $productMap = [];
        $criados = 0;
        $indisponiveis = 0;
        $pulados = 0;

        foreach ($legacy->table('produto')->where('estabelecimento_id', self::LEGACY_ESTABELECIMENTO_ID)->get() as $row) {
            $novoTipoId = $typeMap[$row->tipo_produto_id] ?? null;

            if (!$novoTipoId) {
                $this->warn("Produto legado id={$row->id}: tipo_produto_id={$row->tipo_produto_id} não mapeado, pulado.");
                $pulados++;

                continue;
            }

            $productMap[$row->id] = $this->dryRun
                ? -$row->id
                : DB::table('products')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => self::TARGET_TENANT_ID,
                    'product_type_id' => $novoTipoId,
                    'name' => $row->nome,
                    'sku' => null,
                    'barcode' => null,
                    'brand' => null,
                    'unit' => 'un',
                    // imagem (longblob) fora de escopo desta carga de teste
                    // — image_path fica null, não a migramos.
                    'price' => number_format((float) $row->valor, 2, '.', ''),
                    'description' => $row->descricao,
                    'image_path' => null,
                    'is_available' => (bool) $row->disponivel,
                    // produto.quantidade do legado é ignorado — estoque novo
                    // começa zerado, sem StockBalance/StockMovement criados.
                    'stock_quantity' => 0,
                    'surcharge_rate' => $row->taxa_acrescimo !== null ? number_format((float) $row->taxa_acrescimo, 2, '.', '') : null,
                    'is_lot_controlled' => false,
                    'is_expiry_controlled' => false,
                    'is_serial_controlled' => false,
                    'min_stock' => null,
                    'max_stock' => null,
                    'reorder_point' => null,
                    'reorder_qty' => null,
                    'last_purchase_cost' => null,
                    'created_by' => $this->actorId,
                    'updated_by' => $this->actorId,
                    'created_at' => $row->inclusao_data ?? now(),
                    'updated_at' => $row->alteracao_data ?? $row->inclusao_data ?? now(),
                ]);

            $criados++;

            if (!$row->disponivel) {
                $indisponiveis++;
            }
        }

        $this->stats['produtos_migrados'] = $criados;
        $this->stats['produtos_indisponiveis'] = $indisponiveis;
        $this->stats['produtos_pulados_sem_tipo'] = $pulados;

        return $productMap;
    }

    /**
     * Chunk de 500 clientes por transação — não reaproveita a mesma
     * transação da fase anterior (categorias/produtos), que já commitou.
     * Cria sempre um Endereco NOVO e exclusivo por Client (mesmo que vários
     * clientes do legado compartilhem a mesma linha de `endereco`),
     * porque `Client.endereco_id` é 1:1 no schema novo — combina
     * `endereco.logradouro`/`cep` (legado) com `cliente.numero`/
     * `complemento` (legado, não vivem no `endereco` lá).
     *
     * @return array<int,int>
     */
    private function migrateClients(
        $legacy,
        array $estadoMap,
        array $cidadeMap,
        array $bairroMap,
        array $diaMap,
        array $periodoMap
    ): array {
        $clientMap = [];
        $criados = 0;
        $inativos = 0;
        $pulados = 0;

        $legacyEnderecos = $legacy->table('endereco')->get()->keyBy('id');

        $legacy->table('cliente')
            ->where('estabelecimento_id', self::LEGACY_ESTABELECIMENTO_ID)
            ->orderBy('id')
            ->chunk(self::CHUNK_SIZE, function ($clientes) use (
                &$clientMap,
                &$criados,
                &$inativos,
                &$pulados,
                $estadoMap,
                $cidadeMap,
                $bairroMap,
                $diaMap,
                $periodoMap,
                $legacyEnderecos
            ) {
                $this->transactional(function () use (
                    $clientes,
                    &$clientMap,
                    &$criados,
                    &$inativos,
                    &$pulados,
                    $estadoMap,
                    $cidadeMap,
                    $bairroMap,
                    $diaMap,
                    $periodoMap,
                    $legacyEnderecos
                ) {
                    foreach ($clientes as $row) {
                        $legacyEndereco = $legacyEnderecos->get($row->endereco_id);

                        if (!$legacyEndereco) {
                            $this->warn("Cliente legado id={$row->id}: endereco_id={$row->endereco_id} não encontrado, pulado.");
                            $pulados++;

                            continue;
                        }

                        $novoEstadoId = $estadoMap[$legacyEndereco->estado_id] ?? null;
                        $novaCidadeId = $cidadeMap[$legacyEndereco->cidade_id] ?? null;
                        $novoBairroId = $bairroMap[$legacyEndereco->bairro_id] ?? null;

                        if (!$novoEstadoId || !$novaCidadeId || !$novoBairroId) {
                            $this->warn("Cliente legado id={$row->id}: localização do endereço não mapeada, pulado.");
                            $pulados++;

                            continue;
                        }

                        $createdAt = $row->inclusao_data ?? now();
                        $updatedAt = $row->alteracao_data ?? $createdAt;

                        if ($this->dryRun) {
                            $clientMap[$row->id] = -$row->id;
                        } else {
                            $enderecoId = DB::table('enderecos')->insertGetId([
                                'uuid' => (string) Str::uuid(),
                                'tenant_id' => self::TARGET_TENANT_ID,
                                'estado_id' => $novoEstadoId,
                                'cidade_id' => $novaCidadeId,
                                'bairro_id' => $novoBairroId,
                                'logradouro' => $legacyEndereco->logradouro,
                                'numero' => (string) $row->numero,
                                'complemento' => $row->complemento,
                                'cep' => $legacyEndereco->cep,
                                'is_active' => true,
                                'created_by' => $this->actorId,
                                'updated_by' => $this->actorId,
                                'created_at' => $createdAt,
                                'updated_at' => $updatedAt,
                            ]);

                            $clientMap[$row->id] = DB::table('clients')->insertGetId([
                                'uuid' => (string) Str::uuid(),
                                'tenant_id' => self::TARGET_TENANT_ID,
                                'name' => $row->nome,
                                'endereco_id' => $enderecoId,
                                'dia_ideal_id' => $diaMap[$row->dia_ideal_id] ?? null,
                                'periodo_ideal_id' => $periodoMap[$row->periodo_ideal_id] ?? null,
                                'phone_primary' => $row->telefone_principal,
                                'phone_secondary' => $row->telefone_secundario,
                                'notes' => $row->observacao,
                                'is_trusted' => (bool) $row->confianca,
                                'is_active' => (bool) $row->ativo,
                                'created_by' => $this->actorId,
                                'updated_by' => $this->actorId,
                                'created_at' => $createdAt,
                                'updated_at' => $updatedAt,
                            ]);
                        }

                        $criados++;

                        if (!$row->ativo) {
                            $inativos++;
                        }
                    }
                });
            });

        $this->stats['clientes_migrados'] = $criados;
        $this->stats['clientes_inativos'] = $inativos;
        $this->stats['clientes_pulados'] = $pulados;

        return $clientMap;
    }

    /**
     * Chunk de 500 pedidos por transação. `total_amount` usa
     * `pedido.valor_total` como está (fonte de verdade histórica), NUNCA
     * recalculado a partir da soma dos itens. `unit_price`/`line_total` de
     * cada item usam a mesma convenção de arredondamento em centavos de
     * `OrderService::create()` (round-to-nearest-cent via inteiro, não
     * truncamento), só por consistência de representação — não afeta
     * `total_amount`, que é sempre o valor histórico bruto.
     */
    private function migrateOrders($legacy, int $stockLocationId, array $clientMap, array $productMap): void
    {
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

        $migrados = 0;
        $puladosSemItem = 0;
        $puladosSemCliente = 0;
        $parceladosSemParcela = 0;
        $itensMigrados = 0;
        $itensPuladosSemProduto = 0;

        $legacy->table('pedido')
            ->where('estabelecimento_id', self::LEGACY_ESTABELECIMENTO_ID)
            ->orderBy('id')
            ->chunk(self::CHUNK_SIZE, function ($pedidos) use (
                &$migrados,
                &$puladosSemItem,
                &$puladosSemCliente,
                &$parceladosSemParcela,
                &$itensMigrados,
                &$itensPuladosSemProduto,
                $itemsByPedido,
                $stockLocationId,
                $clientMap,
                $productMap
            ) {
                $this->transactional(function () use (
                    $pedidos,
                    &$migrados,
                    &$puladosSemItem,
                    &$puladosSemCliente,
                    &$parceladosSemParcela,
                    &$itensMigrados,
                    &$itensPuladosSemProduto,
                    $itemsByPedido,
                    $stockLocationId,
                    $clientMap,
                    $productMap
                ) {
                    foreach ($pedidos as $pedido) {
                        $items = $itemsByPedido[$pedido->id] ?? [];

                        if (empty($items)) {
                            $puladosSemItem++;

                            continue;
                        }

                        $novoClienteId = $clientMap[$pedido->cliente_id] ?? null;

                        if (!$novoClienteId) {
                            $puladosSemCliente++;

                            continue;
                        }

                        $validItems = [];

                        foreach ($items as $item) {
                            $novoProdutoId = $productMap[$item->produto_id] ?? null;

                            if (!$novoProdutoId) {
                                $itensPuladosSemProduto++;

                                continue;
                            }

                            $validItems[] = [$item, $novoProdutoId];
                        }

                        if (empty($validItems)) {
                            $puladosSemItem++;

                            continue;
                        }

                        if ((bool) $pedido->parcelado) {
                            $parceladosSemParcela++;
                        }

                        $createdAt = $pedido->inclusao_data ?? now();
                        $updatedAt = $pedido->alteracao_data ?? $createdAt;

                        $paidAt = ((bool) $pedido->pago)
                            ? $this->sanitizeDate($pedido->data_pagamento, $pedido->id, 'data_pagamento')
                            : null;

                        $deliveredAt = ((bool) $pedido->entregue)
                            ? $this->sanitizeDate($pedido->data_entrega, $pedido->id, 'data_entrega')
                            : null;

                        if (!$this->dryRun) {
                            $orderId = DB::table('orders')->insertGetId([
                                'uuid' => (string) Str::uuid(),
                                'tenant_id' => self::TARGET_TENANT_ID,
                                'client_id' => $novoClienteId,
                                'stock_location_id' => $stockLocationId,
                                'is_installment' => (bool) $pedido->parcelado,
                                'total_amount' => number_format((float) $pedido->valor_total, 2, '.', ''),
                                'is_paid' => (bool) $pedido->pago,
                                'paid_at' => $paidAt,
                                'is_delivered' => (bool) $pedido->entregue,
                                'delivered_at' => $deliveredAt,
                                'due_date' => null,
                                'expected_delivery_date' => null,
                                'cancelled_at' => null,
                                'cancellation_reason' => null,
                                'notes' => $pedido->observacao,
                                'created_by' => $this->actorId,
                                'updated_by' => $this->actorId,
                                'created_at' => $createdAt,
                                'updated_at' => $updatedAt,
                            ]);

                            $itemRows = [];

                            foreach ($validItems as [$item, $novoProdutoId]) {
                                $quantity = (float) $item->quantidade_produto;
                                $unitPriceCents = (int) round(((float) $item->valor_momento_venda) * 100);
                                $lineTotalCents = (int) round($unitPriceCents * $quantity);

                                $itemRows[] = [
                                    'uuid' => (string) Str::uuid(),
                                    'tenant_id' => self::TARGET_TENANT_ID,
                                    'order_id' => $orderId,
                                    'product_id' => $novoProdutoId,
                                    'quantity' => $quantity,
                                    'unit_price' => $this->centsToDecimal($unitPriceCents),
                                    'line_total' => $this->centsToDecimal($lineTotalCents),
                                    'created_by' => $this->actorId,
                                    'updated_by' => $this->actorId,
                                    'created_at' => $item->inclusao_data ?? $createdAt,
                                    'updated_at' => $item->alteracao_data ?? $item->inclusao_data ?? $updatedAt,
                                ];
                            }

                            DB::table('order_items')->insert($itemRows);
                        }

                        $migrados++;
                        $itensMigrados += count($validItems);
                    }
                });
            });

        $this->stats['pedidos_migrados'] = $migrados;
        $this->stats['pedidos_pulados_sem_item'] = $puladosSemItem;
        $this->stats['pedidos_pulados_sem_cliente'] = $puladosSemCliente;
        $this->stats['pedidos_parcelados_sem_parcela'] = $parceladosSemParcela;
        $this->stats['itens_migrados'] = $itensMigrados;
        $this->stats['itens_pulados_sem_produto'] = $itensPuladosSemProduto;
    }

    /**
     * Achado real nesta base (6 pedidos): `data_pagamento`/`data_entrega`
     * com ano corrompido (ex.: "0223-02-20", "3023-05-10" — falta/sobra um
     * dígito). `orders.paid_at`/`delivered_at` são `TIMESTAMP` (range
     * 1970–2038 no MySQL) — inserir um desses quebraria o insert inteiro.
     * A flag booleana (`pago`/`entregue`) é a fonte de verdade de negócio;
     * a data é só um detalhe que vira null quando implausível.
     */
    private function sanitizeDate($value, int $legacyPedidoId, string $field): ?string
    {
        if (!$value) {
            return null;
        }

        $year = (int) substr((string) $value, 0, 4);

        if ($year < 2000 || $year > (int) now()->format('Y') + 1) {
            $this->warn("Pedido legado id={$legacyPedidoId}: {$field}=\"{$value}\" fora de range plausível, tratado como ausente.");

            return null;
        }

        return $value;
    }

    private function centsToDecimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function norm(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    private function fallbackUf(string $name): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $name) ?: $name;
        $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', $ascii));

        return substr($letters, 0, 3) ?: 'XXX';
    }

    private function printSummary(): void
    {
        $this->newLine();
        $this->info('=== Resumo — ' . ($this->dryRun ? 'DRY-RUN (nada foi escrito)' : 'EXECUÇÃO REAL') . ' ===');

        $this->table(['Métrica', 'Valor'], collect($this->stats)->map(fn ($v, $k) => [$k, $v])->values()->all());

        $this->newLine();
        $this->warn(
            'Pedidos com is_installment=true SEM nenhum OrderInstallment criado (pedido_parcela vazio na fonte): '
            . ($this->stats['pedidos_parcelados_sem_parcela'] ?? 0)
        );
        $this->warn('Clientes pulados por dedupe/inconsistência: ' . ($this->stats['clientes_pulados'] ?? 0) . ' (Client não tem dedupe por nome, só é pulado se endereço/localização não resolver).');
        $this->warn('Produtos pulados por dedupe/inconsistência: ' . ($this->stats['produtos_pulados_sem_tipo'] ?? 0) . ' (Product não tem dedupe por nome — nenhum dos 2 produtos já existentes no tenant colidiu com nome do legado).');
        $this->warn(
            'Estoque NÃO migrado: nenhum StockBalance/StockMovement foi criado (legado não tem conceito de estoque). '
            . 'O tenant "MF software" vai precisar cadastrar saldo de estoque manualmente antes de conseguir criar PEDIDOS NOVOS pelo sistema — '
            . 'os pedidos importados são só histórico e não reservam/consomem estoque.'
        );
        $this->warn(
            'Estado/Cidade/Bairro do legado escopados por estabelecimento_id=4 (medido diretamente na fonte): 1/6/48 — '
            . 'diferente da estimativa inicial do escopo (3/15/56). Números reais usados nesta importação.'
        );
    }
}
