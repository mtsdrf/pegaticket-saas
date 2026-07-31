<?php

namespace App\Console\Commands;

use App\Console\Commands\Support\LegacyDumpParser;
use App\Models\Order\Order;
use App\Models\Tenant\Tenant;
use App\Models\User\User;
use App\Services\Tenant\TenantProvisioningService;
use App\Services\Tenant\TenantRolePermissionService;
use App\Services\Tenant\TenantRoleService;
use App\DTOs\Tenant\CreateTenantRoleDTO;
use App\DTOs\Tenant\SyncTenantRolePermissionsDTO;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Migração de dados REAIS de produção: estabelecimento_id=4 ("Js Queijos e
 * Doces") do legado (`backup_prod_pegaticket.sql`) para um Tenant NOVO no
 * schema atual. Baseado em
 * `.claude/memory/database-analysis/09-estab4-migration-data-audit.md` e nas
 * decisões de negócio já fechadas com o usuário (ver prompt original —
 * não repetido aqui).
 *
 * Sem `--commit`: SEMPRE modo relatório, zero escrita — parseia o dump,
 * conta cada entidade, soma valores, roda validações de LEITURA (SELECT)
 * contra o banco de `.env`. Compara contra os números já validados
 * independentemente pelo usuário; qualquer divergência é reportada em
 * destaque (não aborta o comando, mas o operador não deve seguir para
 * `--commit` sem investigar).
 *
 * Com `--commit`: 1 única `DB::transaction()` para TUDO — se a
 * reconciliação final (`SUM(orders.total_amount)` do tenant recém-criado
 * contra o total esperado) não bater, `throw` reverte a transação inteira,
 * nada fica gravado pela metade. Guard de idempotência: aborta de cara se já
 * existir um Tenant (mesmo soft-deletado) com o slug gerado.
 *
 * CORREÇÃO DE INTERPRETAÇÃO (2026-07-22, confirmada por parsing direto do
 * dump contra os 38.052 pedidos do estab4, 0 divergência): a hipótese inicial
 * de que `pedido_produto.valor_momento_venda` era o preço UNITÁRIO (usada em
 * `09-estab4-migration-data-audit.md` seção 3) estava ERRADA — o campo já é
 * o TOTAL da linha (quantidade-inclusive). Consequência: `order_items
 * .line_total = valor_momento_venda` (direto, SEM multiplicar por
 * quantidade) e `order_items.unit_price = valor_momento_venda /
 * quantidade_produto` (dividido, arredondado 2 casas). O "achado grave" do
 * pedido #21131 (900 unidades) relatado antes NÃO é erro de digitação —
 * R$32.400 total ÷ 900 = R$36/unidade, pedido válido. Não havia ~742 casos
 * "graves": era artefato da interpretação errada da coluna, não divergência
 * real de dado. `orders.total_amount` continua = `pedido.valor_total`
 * verbatim, sem mudança (isso já estava certo).
 */
class MigrateLegacyEstablishmentCommand extends Command
{
    protected $signature = 'legacy:migrate-estabelecimento {--dump=} {--commit} {--database= : Nome da conexão (config/database.php) a usar em vez do default de .env — obrigatório junto com --commit fora de ensaio, cofre contra escrita acidental em produção}';

    protected $description = 'Migra o estabelecimento_id=4 ("Js Queijos e Doces") do dump legado para um Tenant novo. Sem --commit: só relatório, zero escrita.';

    private const LEGACY_ESTABELECIMENTO_ID = 4;
    private const TENANT_NAME = 'Js Queijos e Doces';
    private const OWNER_EMAIL = 'je_silveira14@hotmail.com';
    private const EMPLOYEE_EMAIL = 'jenifer@gmail.com';
    private const EMPLOYEE_NAME = 'Jenifer';
    private const PLAN_SLUG = 'diamante';
    private const CHUNK_SIZE = 500;

    /** Números já validados de forma independente pelo usuário (ver prompt original). */
    private const EXPECTED_COUNTS = [
        'estabelecimento' => 1,
        'usuario' => 2,
        'estado' => 1,
        'cidade' => 6,
        'bairro' => 48,
        'endereco' => 628,
        'dia_ideal' => 31,
        'cliente' => 1913,
        'produto' => 176,
        'pedido' => 38052,
        'pedido_produto' => 92246,
    ];

    private const EXPECTED_VALOR_TOTAL = 4217946.33;
    private const EXPECTED_VALOR_PAGO = 3226599.06;
    private const EXPECTED_PAGO_COUNT = 35857;

    /** Legado só guarda nome por extenso do estado — `estados.uf` é obrigatória/unique no schema novo. */
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

    /** Employee (Jenifer): permissões pedidas explicitamente pelo usuário. */
    private const EMPLOYEE_PERMISSIONS = [
        'orders:read', 'orders:create', 'orders:deliver', 'orders:pay',
        'clients:read', 'clients:create', 'clients:update',
        'products:read',
        'dashboard:read',
        'reports:read',
    ];

    private ?int $actorId = null;

    public function handle(): int
    {
        // Dump de 23MB gera dezenas de milhares de arrays associativos em
        // memória (pedido/pedido_produto) — CLI local tem memory_limit=128M
        // por padrão, insuficiente. ETL de uso único, elevar aqui é seguro.
        // INCIDENTE (2026-07-22): ini_set() sozinho aqui NÃO é garantia
        // suficiente em toda execução real — uma tentativa de --commit
        // contra produção OOMou em 128M mesmo com esta linha presente
        // (execução de longa duração, movida para background pelo
        // orquestrador do agente depois de ~120s; causa raiz exata de por
        // que o ini_set não "pegou" nessa execução específica não foi
        // isolada com certeza — não reproduzido em teste isolado de
        // ini_set()/alocação de memória no mesmo host). Correção robusta
        // aplicada: SEMPRE invocar este comando também com
        // `php -d memory_limit=2G artisan legacy:migrate-estabelecimento ...`
        // na CLI (define o limite ANTES do bootstrap do Laravel, não
        // depende de nenhum código deste arquivo rodar primeiro) — o
        // ini_set abaixo fica como segunda camada de defesa, não a única.
        ini_set('memory_limit', '2048M');

        if ($database = $this->option('database')) {
            if (!array_key_exists($database, config('database.connections'))) {
                $this->error("Conexão \"{$database}\" não existe em config/database.php.");

                return self::FAILURE;
            }

            config(['database.default' => $database]);
            DB::purge($database);
            $this->warn("Conexão alvo forçada via --database: \"{$database}\" (config('database.default') agora aponta pra cá, não pro default de .env).");
        }

        $this->line('Conexão efetiva desta execução: "' . config('database.default') . '" -> ' . $this->describeConnectionTarget(config('database.default')));

        $dumpPath = $this->option('dump') ?: base_path('../backup_prod_pegaticket.sql');

        if (!is_file($dumpPath)) {
            $this->error("Dump não encontrado: {$dumpPath}");

            return self::FAILURE;
        }

        $commit = (bool) $this->option('commit');

        $this->info('Lendo/parseando dump: ' . $dumpPath);
        $parser = new LegacyDumpParser($dumpPath);

        $extracted = $this->extractAll($parser);

        if (!$commit) {
            return $this->runDryRun($extracted);
        }

        return $this->runCommit($extracted);
    }

    /**
     * Extrai TODAS as tabelas de interesse do dump, já escopadas ao
     * estabelecimento 4 (direto ou indireto via pedido_id). Não toca banco
     * — é feito uma única vez e reaproveitado pelo modo relatório e pelo
     * modo commit (mesmo dado, mesmas contagens).
     *
     * @return array<string, mixed>
     */
    private function extractAll(LegacyDumpParser $parser): array
    {
        $estabelecimento = null;
        foreach ($parser->rows('estabelecimento') as $row) {
            if ((int) $row['id'] === self::LEGACY_ESTABELECIMENTO_ID) {
                $estabelecimento = $row;
                break;
            }
        }

        $filterByEstab = static fn (iterable $rows): array => collect($rows)
            ->filter(fn ($r) => (int) $r['estabelecimento_id'] === self::LEGACY_ESTABELECIMENTO_ID)
            ->values()
            ->all();

        $usuarios = $filterByEstab($parser->rows('usuario'));
        $estados = $filterByEstab($parser->rows('estado'));
        $cidades = $filterByEstab($parser->rows('cidade'));
        $bairros = $filterByEstab($parser->rows('bairro'));
        $enderecos = $filterByEstab($parser->rows('endereco'));
        $diaIdeais = $filterByEstab($parser->rows('dia_ideal'));
        $clientes = $filterByEstab($parser->rows('cliente'));
        $categoriaProdutos = $filterByEstab($parser->rows('categoria_produto'));
        $tipoProdutos = $filterByEstab($parser->rows('tipo_produto'));
        $produtos = $filterByEstab($parser->rows('produto'));

        $pedidos = [];
        $pedidoIds = [];
        $sumValorTotal = 0.0;
        $sumValorPago = 0.0;
        $pagoCount = 0;

        foreach ($parser->rows('pedido') as $row) {
            if ((int) $row['estabelecimento_id'] !== self::LEGACY_ESTABELECIMENTO_ID) {
                continue;
            }

            $pedidos[] = $row;
            $pedidoIds[(int) $row['id']] = true;
            $sumValorTotal += (float) $row['valor_total'];
            $sumValorPago += (float) $row['valor_pago'];

            if ((int) $row['pago'] === 1) {
                $pagoCount++;
            }
        }

        $pedidoProdutos = [];
        foreach ($parser->rows('pedido_produto') as $row) {
            if (isset($pedidoIds[(int) $row['pedido_id']])) {
                $pedidoProdutos[] = $row;
            }
        }

        return [
            'estabelecimento' => $estabelecimento,
            'usuarios' => $usuarios,
            'estados' => $estados,
            'cidades' => $cidades,
            'bairros' => $bairros,
            'enderecos' => $enderecos,
            'dia_ideais' => $diaIdeais,
            'clientes' => $clientes,
            'categoria_produtos' => $categoriaProdutos,
            'tipo_produtos' => $tipoProdutos,
            'produtos' => $produtos,
            'pedidos' => $pedidos,
            'pedido_produtos' => $pedidoProdutos,
            'sum_valor_total' => $sumValorTotal,
            'sum_valor_pago' => $sumValorPago,
            'pago_count' => $pagoCount,
        ];
    }

    // ======================================================================
    // DRY-RUN (único modo executado nesta rodada)
    // ======================================================================

    private function runDryRun(array $d): int
    {
        $this->newLine();
        $this->info('=== DRY-RUN — nenhuma escrita foi feita, nem será ===');
        $this->newLine();

        if (!$d['estabelecimento']) {
            $this->error('estabelecimento id=4 NÃO encontrado no dump. Abortando relatório.');

            return self::FAILURE;
        }

        $anyMismatch = false;

        $rows = [];
        $countsFound = [
            'estabelecimento' => $d['estabelecimento'] ? 1 : 0,
            'usuario' => count($d['usuarios']),
            'estado' => count($d['estados']),
            'cidade' => count($d['cidades']),
            'bairro' => count($d['bairros']),
            'endereco' => count($d['enderecos']),
            'dia_ideal' => count($d['dia_ideais']),
            'cliente' => count($d['clientes']),
            'produto' => count($d['produtos']),
            'pedido' => count($d['pedidos']),
            'pedido_produto' => count($d['pedido_produtos']),
        ];

        foreach ($countsFound as $entity => $found) {
            $expected = self::EXPECTED_COUNTS[$entity];
            $match = $found === $expected;
            $anyMismatch = $anyMismatch || !$match;
            $rows[] = [$entity, $expected, $found, $match ? 'OK' : 'DIVERGE'];
        }

        $this->table(['Entidade', 'Esperado', 'Encontrado no dump', 'Status'], $rows);

        // categoria_produto/tipo_produto não têm número pré-validado no
        // prompt original — só reportados (informativo, sem comparação).
        $this->line('categoria_produto (estab4, sem número pré-validado): ' . count($d['categoria_produtos']));
        $this->line('tipo_produto (estab4, sem número pré-validado): ' . count($d['tipo_produtos']));

        $this->newLine();

        $moneyRows = [
            ['SUM(pedido.valor_total)', number_format(self::EXPECTED_VALOR_TOTAL, 2, ',', '.'), number_format($d['sum_valor_total'], 2, ',', '.'), abs($d['sum_valor_total'] - self::EXPECTED_VALOR_TOTAL) < 0.01 ? 'OK' : 'DIVERGE'],
            ['SUM(pedido.valor_pago)', number_format(self::EXPECTED_VALOR_PAGO, 2, ',', '.'), number_format($d['sum_valor_pago'], 2, ',', '.'), abs($d['sum_valor_pago'] - self::EXPECTED_VALOR_PAGO) < 0.01 ? 'OK' : 'DIVERGE'],
            ['COUNT(pedido.pago=1)', (string) self::EXPECTED_PAGO_COUNT, (string) $d['pago_count'], $d['pago_count'] === self::EXPECTED_PAGO_COUNT ? 'OK' : 'DIVERGE'],
        ];

        foreach ($moneyRows as $r) {
            if ($r[3] === 'DIVERGE') {
                $anyMismatch = true;
            }
        }

        $this->table(['Métrica', 'Esperado', 'Encontrado', 'Status'], $moneyRows);

        // Soma de pedido_produto.valor_momento_venda por pedido (já é o
        // TOTAL da linha, não o preço unitário — ver docblock da classe,
        // correção de interpretação de 2026-07-22). SEM multiplicar por
        // quantidade: line_total = valor_momento_venda direto.
        $sumItensPorPedido = [];
        foreach ($d['pedido_produtos'] as $item) {
            $pedidoId = (int) $item['pedido_id'];
            $sumItensPorPedido[$pedidoId] = ($sumItensPorPedido[$pedidoId] ?? 0.0) + (float) $item['valor_momento_venda'];
        }

        $pedidosVazios = [];
        $reconciliados = 0;
        $divergentes = [];

        foreach ($d['pedidos'] as $pedido) {
            $pedidoId = (int) $pedido['id'];

            if (!isset($sumItensPorPedido[$pedidoId])) {
                $pedidosVazios[] = $pedidoId;
                continue;
            }

            $diff = abs($sumItensPorPedido[$pedidoId] - (float) $pedido['valor_total']);

            if ($diff < 0.01) {
                $reconciliados++;
            } else {
                $divergentes[] = $pedidoId;
            }
        }

        $this->line('Pedidos sem nenhum item (esperado: id 36195, 36492, 36533): [' . implode(', ', $pedidosVazios) . ']');
        $this->newLine();
        $this->info('=== Reconciliação item-a-item: SUM(pedido_produto.valor_momento_venda) por pedido vs. pedido.valor_total ===');
        $this->line('Pedidos com item, reconciliados exatos (diff < R$0,01): ' . $reconciliados . ' de ' . (count($d['pedidos']) - count($pedidosVazios)));
        $this->line('Pedidos com item e divergência real: ' . count($divergentes) . (count($divergentes) > 0 ? ' -> ids: [' . implode(', ', array_slice($divergentes, 0, 20)) . (count($divergentes) > 20 ? ', ...' : '') . ']' : ''));

        if ($divergentes !== []) {
            $anyMismatch = true;
        }

        // Risco de truncamento clients.name varchar(90) — não decidido, só reportado.
        $nomesLongos = collect($d['clientes'])->filter(fn ($c) => mb_strlen((string) $c['nome']) > 90)->count();
        $this->line("Clientes com nome > 90 caracteres (risco de truncamento em clients.name): {$nomesLongos}");

        $this->newLine();
        $this->info('=== Validações de LEITURA contra o banco de .env (só SELECT) ===');

        $existingOwner = User::withTrashed()->where('email', self::OWNER_EMAIL)->first();
        $existingEmployee = User::withTrashed()->where('email', self::EMPLOYEE_EMAIL)->first();

        $slug = Str::slug(self::TENANT_NAME);
        $existingTenant = Tenant::withTrashed()->where('slug', $slug)->first();

        $this->table(['Checagem', 'Resultado'], [
            ['User existente com e-mail ' . self::OWNER_EMAIL, $existingOwner ? "SIM (id={$existingOwner->id}, uuid={$existingOwner->uuid})" : 'não'],
            ['User existente com e-mail ' . self::EMPLOYEE_EMAIL, $existingEmployee ? "SIM (id={$existingEmployee->id}, uuid={$existingEmployee->uuid})" : 'não'],
            ['Slug gerado para o tenant', $slug],
            ['Tenant existente com esse slug', $existingTenant ? "SIM (id={$existingTenant->id}, uuid={$existingTenant->uuid}, trashed=" . ($existingTenant->trashed() ? 'sim' : 'não') . ')' : 'não'],
            ['Plan "' . self::PLAN_SLUG . '" existe', DB::table('plans')->where('slug', self::PLAN_SLUG)->exists() ? 'sim' : 'NÃO — abortaria o commit'],
        ]);

        $this->newLine();

        if ($anyMismatch) {
            $this->error('DIVERGÊNCIA encontrada entre o dump e os números já validados — NÃO prosseguir para --commit sem investigar a causa (pode ser bug no parser).');

            return self::FAILURE;
        }

        $this->info('Todas as contagens/somas batem exatamente com os números já validados. Dump íntegro para os fins desta migração.');

        if ($existingTenant) {
            $this->warn('Guard de idempotência: já existe um Tenant com o slug "' . $slug . '" — --commit abortaria imediatamente (comando não é seguro rodar 2x).');
        }

        return self::SUCCESS;
    }

    // ======================================================================
    // COMMIT (implementado, NÃO executado nesta tarefa)
    // ======================================================================

    private function runCommit(array $d): int
    {
        $slug = Str::slug(self::TENANT_NAME);

        if (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            $this->error("Já existe um Tenant (possivelmente soft-deletado) com slug \"{$slug}\" — abortando. Este comando não é seguro rodar --commit duas vezes.");

            return self::FAILURE;
        }

        $planId = DB::table('plans')->where('slug', self::PLAN_SLUG)->value('id');

        if (!$planId) {
            $this->error('Plan "' . self::PLAN_SLUG . '" não encontrado.');

            return self::FAILURE;
        }

        // SUM(pedido.valor_total) já extraído/validado no dry-run — mesma
        // fonte usada aqui como alvo de reconciliação final.
        $expectedTotal = $d['sum_valor_total'];

        try {
            DB::transaction(function () use ($d, $slug, $planId, $expectedTotal) {
                // Users primeiro — actorId (owner) precisa estar resolvido
                // ANTES de qualquer outro insert, para created_by/updated_by.
                [$ownerUserId, $employeeUserId] = $this->migrateUsers($d['usuarios']);
                $this->actorId = $ownerUserId;

                // ETL roda fora do contexto HTTP — Auth::id() é null aqui
                // por padrão. TenantRoleService::create()/TenantProvisioningService
                // exigem um ator autenticado (evento de auditoria com
                // actorId:int não-nulo). Mesmo truque já usado em
                // DemoPlansPresentationSeeder::buildTenant() (Auth::setUser
                // antes de chamar Services de domínio fora de request HTTP).
                \Illuminate\Support\Facades\Auth::setUser(\App\Models\User\User::find($ownerUserId));

                $tenant = $this->createTenant($d['estabelecimento'], $slug, $planId);

                // Mesmo truque de DemoPlansPresentationSeeder::buildTenant()
                // — fora do middleware `tenant` (ResolveTenant), os bindings
                // 'tenant'/'tenant_id'/'tenant_uuid' não existem no
                // container; TenantRoleService/TenantRolePermissionService
                // dependem deles (assertBelongsToCurrentTenant).
                app()->instance('tenant', $tenant);
                app()->instance('tenant_id', $tenant->id);
                app()->instance('tenant_uuid', $tenant->uuid);

                $stockLocationId = $this->createStockLocation($tenant->id);

                [$estadoMap, $cidadeMap, $bairroMap] = $this->migrateLocations($d);
                $diaMap = $this->migrateDiaIdeal($tenant->id, $d['dia_ideais']);
                $enderecoMap = $this->migrateEnderecos($tenant->id, $d['clientes'], $d['enderecos'], $estadoMap, $cidadeMap, $bairroMap);
                $clientMap = $this->migrateClients($tenant->id, $d['clientes'], $enderecoMap, $diaMap);

                [$categoryMap, $typeMap] = $this->migrateProductCategoriesAndTypes($tenant->id, $d['categoria_produtos'], $d['tipo_produtos']);
                $productMap = $this->migrateProducts($tenant->id, $d['produtos'], $typeMap);

                $this->attachOwnerAndEmployee($tenant, $ownerUserId, $employeeUserId);

                $this->migrateOrdersAndItems($tenant->id, $stockLocationId, $d['pedidos'], $d['pedido_produtos'], $clientMap, $productMap);
                $this->migratePayments($tenant->id, $d['pedidos']);

                DB::table('tenant_settings')->where('tenant_id', $tenant->id)->update([
                    'block_order_without_stock' => false,
                    'updated_at' => now(),
                ]);

                Artisan::call('orders:backfill-codigo');

                $actualTotal = (float) DB::table('orders')->where('tenant_id', $tenant->id)->sum('total_amount');

                if (abs($actualTotal - $expectedTotal) >= 0.01) {
                    throw new RuntimeException(sprintf(
                        'Reconciliação falhou: SUM(orders.total_amount)=%.2f, esperado=%.2f — revertendo tudo.',
                        $actualTotal,
                        $expectedTotal
                    ));
                }

                $this->info("Reconciliação OK: SUM(orders.total_amount) = {$actualTotal}");
            });
        } catch (\Throwable $e) {
            $this->error('Migração revertida (transação inteira desfeita): ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info('Migração concluída com sucesso.');

        return self::SUCCESS;
    }

    private function createTenant(array $estabelecimento, string $slug, int $planId): Tenant
    {
        $cnpj = $estabelecimento['cnpj'] ? preg_replace('/\D/', '', (string) $estabelecimento['cnpj']) : null;

        $id = DB::table('tenants')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => self::TENANT_NAME,
            'razao_social' => $estabelecimento['razao_social'] ?? null,
            'slug' => $slug,
            'plan_id' => $planId,
            'is_active' => true,
            'trial_ends_at' => null,
            'next_order_code' => 999,
            'cnpj' => $cnpj,
            'email' => $estabelecimento['email'] ?? null,
            'phone' => $estabelecimento['telefone'] ?? null,
            'mobile_phone' => $estabelecimento['celular'] ?? null,
            'whatsapp' => $estabelecimento['whatsapp'] ?? null,
            'facebook' => $estabelecimento['facebook'] ?? null,
            'instagram' => $estabelecimento['instagram'] ?? null,
            'created_by' => $this->actorId,
            'updated_by' => $this->actorId,
            'created_at' => $estabelecimento['inclusao_data'] ?? now(),
            'updated_at' => $estabelecimento['alteracao_data'] ?? $estabelecimento['inclusao_data'] ?? now(),
        ]);

        // tenant_settings não nasce sozinho aqui porque o insert acima é
        // via DB::table() (não dispara TenantCreated) — cria explicitamente.
        DB::table('tenant_settings')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $id,
            'send_tracking_link_whatsapp' => false,
            'block_order_without_stock' => false,
            'created_by' => $this->actorId,
            'updated_by' => $this->actorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Tenant::findOrFail($id);
    }

    private function createStockLocation(int $tenantId): int
    {
        return DB::table('stock_locations')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'name' => 'Loja',
            'type' => null,
            'address' => null,
            'is_default' => true,
            'is_active' => true,
            'created_by' => $this->actorId,
            'updated_by' => $this->actorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array{0: array<int,int>, 1: array<int,int>, 2: array<int,int>} */
    private function migrateLocations(array $d): array
    {
        $estadoMap = [];
        foreach ($d['estados'] as $row) {
            $existing = DB::table('estados')->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($row['nome']))])->first();

            if ($existing) {
                $estadoMap[(int) $row['id']] = $existing->id;
                continue;
            }

            $uf = self::STATE_UF_BY_NAME[$row['nome']] ?? $this->fallbackUf($row['nome']);

            $estadoMap[(int) $row['id']] = DB::table('estados')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => $row['nome'],
                'uf' => $uf,
                'is_active' => true,
                'created_by' => $this->actorId,
                'updated_by' => $this->actorId,
                'created_at' => $row['inclusao_data'] ?? now(),
                'updated_at' => $row['alteracao_data'] ?? $row['inclusao_data'] ?? now(),
            ]);
        }

        $cidadeMap = [];
        foreach ($d['cidades'] as $row) {
            $novoEstadoId = $estadoMap[(int) $row['estado_id']] ?? null;
            if (!$novoEstadoId) {
                continue;
            }

            $existing = DB::table('cidades')->where('estado_id', $novoEstadoId)
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($row['nome']))])->first();

            if ($existing) {
                $cidadeMap[(int) $row['id']] = $existing->id;
                continue;
            }

            $cidadeMap[(int) $row['id']] = DB::table('cidades')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'estado_id' => $novoEstadoId,
                'name' => $row['nome'],
                'is_active' => true,
                'created_by' => $this->actorId,
                'updated_by' => $this->actorId,
                'created_at' => $row['inclusao_data'] ?? now(),
                'updated_at' => $row['alteracao_data'] ?? $row['inclusao_data'] ?? now(),
            ]);
        }

        $bairroMap = [];
        foreach ($d['bairros'] as $row) {
            $novaCidadeId = $cidadeMap[(int) $row['cidade_id']] ?? null;
            if (!$novaCidadeId) {
                continue;
            }

            $existing = DB::table('bairros')->where('cidade_id', $novaCidadeId)
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($row['nome']))])->first();

            if ($existing) {
                $bairroMap[(int) $row['id']] = $existing->id;
                continue;
            }

            $bairroMap[(int) $row['id']] = DB::table('bairros')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'cidade_id' => $novaCidadeId,
                'name' => $row['nome'],
                'is_active' => true,
                'created_by' => $this->actorId,
                'updated_by' => $this->actorId,
                'created_at' => $row['inclusao_data'] ?? now(),
                'updated_at' => $row['alteracao_data'] ?? $row['inclusao_data'] ?? now(),
            ]);
        }

        return [$estadoMap, $cidadeMap, $bairroMap];
    }

    /** @return array<int,int> */
    private function migrateDiaIdeal(int $tenantId, array $diaIdeais): array
    {
        $map = [];
        foreach ($diaIdeais as $row) {
            $map[(int) $row['id']] = DB::table('dia_ideais')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'name' => $row['nome'],
                'is_active' => (bool) $row['ativo'],
                'created_by' => $this->actorId,
                'updated_by' => $this->actorId,
                'created_at' => $row['inclusao_data'] ?? now(),
                'updated_at' => $row['alteracao_data'] ?? $row['inclusao_data'] ?? now(),
            ]);
        }

        return $map;
    }

    /**
     * 1 linha em `enderecos` por combinação única
     * (endereco_id legado + numero + complemento) — `numero`/`complemento`
     * vêm de `cliente`, não de `endereco`, no legado.
     *
     * @return array<string,int> chave "{enderecoIdLegado}|{numero}|{complemento}" => novo id
     */
    private function migrateEnderecos(int $tenantId, array $clientes, array $enderecosLegado, array $estadoMap, array $cidadeMap, array $bairroMap): array
    {
        $legacyById = collect($enderecosLegado)->keyBy(fn ($r) => (int) $r['id']);
        $map = [];

        foreach ($clientes as $cliente) {
            $legacyEnderecoId = (int) $cliente['endereco_id'];
            $numero = (string) ($cliente['numero'] ?? '');
            $complemento = (string) ($cliente['complemento'] ?? '');
            $key = "{$legacyEnderecoId}|{$numero}|{$complemento}";

            if (isset($map[$key])) {
                continue;
            }

            $legacyEndereco = $legacyById->get($legacyEnderecoId);
            if (!$legacyEndereco) {
                continue;
            }

            $novoEstadoId = $estadoMap[(int) $legacyEndereco['estado_id']] ?? null;
            $novaCidadeId = $cidadeMap[(int) $legacyEndereco['cidade_id']] ?? null;
            $novoBairroId = $bairroMap[(int) $legacyEndereco['bairro_id']] ?? null;

            if (!$novoEstadoId || !$novaCidadeId || !$novoBairroId) {
                continue;
            }

            $map[$key] = DB::table('enderecos')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'estado_id' => $novoEstadoId,
                'cidade_id' => $novaCidadeId,
                'bairro_id' => $novoBairroId,
                'logradouro' => $legacyEndereco['logradouro'],
                'numero' => $numero !== '' ? $numero : null,
                'complemento' => $complemento !== '' ? $complemento : null,
                'cep' => $legacyEndereco['cep'] ?? null,
                'is_active' => true,
                'created_by' => $this->actorId,
                'updated_by' => $this->actorId,
                'created_at' => $cliente['inclusao_data'] ?? now(),
                'updated_at' => $cliente['alteracao_data'] ?? $cliente['inclusao_data'] ?? now(),
            ]);
        }

        return $map;
    }

    /** @return array<int,int> id do cliente legado => id do client novo */
    private function migrateClients(int $tenantId, array $clientes, array $enderecoMap, array $diaMap): array
    {
        $map = [];

        foreach (array_chunk($clientes, self::CHUNK_SIZE) as $chunk) {
            $rows = [];
            $legacyIdByUuid = [];

            foreach ($chunk as $row) {
                $numero = (string) ($row['numero'] ?? '');
                $complemento = (string) ($row['complemento'] ?? '');
                $key = "{$row['endereco_id']}|{$numero}|{$complemento}";
                $enderecoId = $enderecoMap[$key] ?? null;

                if (!$enderecoId) {
                    continue;
                }

                $uuid = (string) Str::uuid();
                $createdAt = $row['inclusao_data'] ?? now();
                $updatedAt = $row['alteracao_data'] ?? $createdAt;

                $rows[] = [
                    'uuid' => $uuid,
                    'tenant_id' => $tenantId,
                    'name' => mb_substr((string) $row['nome'], 0, 90),
                    'endereco_id' => $enderecoId,
                    'dia_ideal_id' => $diaMap[(int) ($row['dia_ideal_id'] ?? 0)] ?? null,
                    'periodo_ideal_id' => null,
                    'phone_primary' => $row['telefone_principal'] ?? null,
                    'phone_secondary' => $row['telefone_secundario'] ?? null,
                    'notes' => $row['observacao'] ?? null,
                    'is_trusted' => (bool) ($row['confianca'] ?? true),
                    'is_active' => (bool) $row['ativo'],
                    'created_by' => $this->actorId,
                    'updated_by' => $this->actorId,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ];

                $legacyIdByUuid[$uuid] = (int) $row['id'];
            }

            if ($rows === []) {
                continue;
            }

            DB::table('clients')->insert($rows);

            // insertGetId não funciona para insert em lote — recupera os
            // IDs recém-inseridos por uuid (único e gerado localmente acima).
            $insertedIdByUuid = DB::table('clients')->whereIn('uuid', array_keys($legacyIdByUuid))->pluck('id', 'uuid');

            foreach ($legacyIdByUuid as $uuid => $legacyId) {
                $map[$legacyId] = (int) $insertedIdByUuid[$uuid];
            }
        }

        return $map;
    }

    /** @return array{0: array<int,int>, 1: array<int,int>} */
    private function migrateProductCategoriesAndTypes(int $tenantId, array $categorias, array $tipos): array
    {
        $categoryMap = [];
        foreach ($categorias as $row) {
            $categoryMap[(int) $row['id']] = DB::table('product_categories')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'name' => $row['nome'],
                'priority' => $row['prioridade'] ?? null,
                'is_active' => (bool) $row['ativo'],
                'created_by' => $this->actorId,
                'updated_by' => $this->actorId,
                'created_at' => $row['inclusao_data'] ?? now(),
                'updated_at' => $row['alteracao_data'] ?? $row['inclusao_data'] ?? now(),
            ]);
        }

        $typeMap = [];
        foreach ($tipos as $row) {
            $novaCategoriaId = $categoryMap[(int) $row['categoria_produto_id']] ?? null;
            if (!$novaCategoriaId) {
                continue;
            }

            $typeMap[(int) $row['id']] = DB::table('product_types')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'product_category_id' => $novaCategoriaId,
                'name' => $row['nome'],
                'priority' => $row['prioridade'] ?? null,
                'is_active' => (bool) $row['ativo'],
                'created_by' => $this->actorId,
                'updated_by' => $this->actorId,
                'created_at' => $row['inclusao_data'] ?? now(),
                'updated_at' => $row['alteracao_data'] ?? $row['inclusao_data'] ?? now(),
            ]);
        }

        return [$categoryMap, $typeMap];
    }

    /** @return array<int,int> */
    private function migrateProducts(int $tenantId, array $produtos, array $typeMap): array
    {
        $map = [];
        foreach ($produtos as $row) {
            $novoTipoId = $typeMap[(int) $row['tipo_produto_id']] ?? null;
            if (!$novoTipoId) {
                continue;
            }

            $map[(int) $row['id']] = DB::table('products')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'product_type_id' => $novoTipoId,
                'name' => $row['nome'],
                'price' => number_format((float) $row['valor'], 2, '.', ''),
                'description' => $row['descricao'] ?? null,
                'image_path' => null,
                'is_available' => (bool) $row['disponivel'],
                'stock_quantity' => 0,
                'surcharge_rate' => $row['taxa_acrescimo'] !== null ? number_format((float) $row['taxa_acrescimo'], 2, '.', '') : null,
                'created_at' => $row['inclusao_data'] ?? now(),
                'updated_at' => $row['alteracao_data'] ?? $row['inclusao_data'] ?? now(),
            ]);
        }

        return $map;
    }

    /** @return array{0:int,1:int} [ownerUserId, employeeUserId] */
    private function migrateUsers(array $usuarios): array
    {
        $byEmail = collect($usuarios)->keyBy('email');

        $owner = $byEmail->get(self::OWNER_EMAIL);
        $employee = $byEmail->get(self::EMPLOYEE_EMAIL);

        if (!$owner || !$employee) {
            throw new RuntimeException('usuario legado não encontrado para owner/employee esperados.');
        }

        $ownerUser = User::firstOrCreate(
            ['email' => self::OWNER_EMAIL],
            [
                'uuid' => (string) Str::uuid(),
                'name' => $owner['nome'],
                'password' => $owner['password'], // hash bcrypt preservado literalmente
                'is_active' => (bool) $owner['ativo'],
                'created_by' => $this->actorId,
                'updated_by' => $this->actorId,
            ]
        );

        $employeeUser = User::firstOrCreate(
            ['email' => self::EMPLOYEE_EMAIL],
            [
                'uuid' => (string) Str::uuid(),
                'name' => $employee['nome'],
                'password' => $employee['password'],
                'is_active' => (bool) $employee['ativo'],
                'created_by' => $this->actorId,
                'updated_by' => $this->actorId,
            ]
        );

        return [$ownerUser->id, $employeeUser->id];
    }

    private function attachOwnerAndEmployee(Tenant $tenant, int $ownerUserId, int $employeeUserId): void
    {
        $provisioning = app(TenantProvisioningService::class);

        $ownerRole = $provisioning->createOwnerRole($tenant);
        $provisioning->attachOwnerUser($tenant, $ownerUserId, $ownerRole);
        $provisioning->syncOwnerRolePermissions($tenant, $ownerRole);

        $employeeRole = app(TenantRoleService::class)->create(CreateTenantRoleDTO::fromArray([
            'name' => self::EMPLOYEE_NAME,
            'slug' => 'funcionario',
            'is_active' => true,
            'created_by' => $this->actorId,
            'updated_by' => $this->actorId,
        ], $tenant->id));

        $permissions = array_map(function (string $pair) {
            [$functionality, $action] = explode(':', $pair);

            return ['functionality' => $functionality, 'action' => $action];
        }, self::EMPLOYEE_PERMISSIONS);

        app(TenantRolePermissionService::class)->syncPermissions(
            $employeeRole,
            SyncTenantRolePermissionsDTO::fromArray(['permissions' => $permissions])
        );

        DB::table('tenant_users')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'user_id' => $employeeUserId,
            'tenant_role_id' => $employeeRole->id,
            'is_active' => true,
            'created_by' => $this->actorId,
            'updated_by' => $this->actorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * `total_amount` = `pedido.valor_total` verbatim (decisão 1 do usuário,
     * nunca recalculado). `line_total` de cada item = `valor_momento_venda`
     * direto (já é o total da linha, quantidade-inclusive — ver docblock da
     * classe); `unit_price` = `valor_momento_venda / quantidade_produto`,
     * arredondado 2 casas.
     */
    private function migrateOrdersAndItems(int $tenantId, int $stockLocationId, array $pedidos, array $pedidoProdutos, array $clientMap, array $productMap): void
    {
        $itemsByPedido = [];
        foreach ($pedidoProdutos as $item) {
            $itemsByPedido[(int) $item['pedido_id']][] = $item;
        }

        foreach (array_chunk($pedidos, self::CHUNK_SIZE) as $chunk) {
            foreach ($chunk as $pedido) {
                $novoClienteId = $clientMap[(int) $pedido['cliente_id']] ?? null;
                if (!$novoClienteId) {
                    continue;
                }

                $createdAt = $pedido['inclusao_data'] ?? now();
                $updatedAt = $pedido['alteracao_data'] ?? $createdAt;

                $paidAt = ((int) $pedido['pago'] === 1)
                    ? $this->sanitizeDate($pedido['data_pagamento'] ?? null)
                    : null;

                $deliveredAt = ((int) $pedido['entregue'] === 1)
                    ? $this->sanitizeDate($pedido['data_entrega'] ?? null)
                    : null;

                $orderId = DB::table('orders')->insertGetId([
                    'uuid' => $pedido['uuid'],
                    'tenant_id' => $tenantId,
                    'client_id' => $novoClienteId,
                    'stock_location_id' => $stockLocationId,
                    'is_installment' => (bool) $pedido['parcelado'],
                    'total_amount' => number_format((float) $pedido['valor_total'], 2, '.', ''),
                    'paid_amount' => $pedido['valor_pago'] !== null ? number_format((float) $pedido['valor_pago'], 2, '.', '') : null,
                    'is_paid' => (int) $pedido['pago'] === 1,
                    'paid_at' => $paidAt,
                    'is_delivered' => (int) $pedido['entregue'] === 1,
                    'delivered_at' => $deliveredAt,
                    'due_date' => null,
                    'expected_delivery_date' => null,
                    'cancelled_at' => null,
                    'cancellation_reason' => null,
                    'notes' => $pedido['observacao'] ?? null,
                    'status' => 'confirmed',
                    'origin' => 'staff',
                    'stock_reserved' => false,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ]);

                $items = $itemsByPedido[(int) $pedido['id']] ?? [];
                $itemRows = [];

                foreach ($items as $item) {
                    $novoProdutoId = $productMap[(int) $item['produto_id']] ?? null;
                    if (!$novoProdutoId) {
                        continue;
                    }

                    $quantity = (float) $item['quantidade_produto'];
                    $lineTotalCents = (int) round(((float) $item['valor_momento_venda']) * 100);
                    // valor_momento_venda já é o TOTAL da linha (confirmado
                    // por reconciliação 100% contra os 38.052 pedidos do
                    // estab4, 2026-07-22) — unit_price é derivado dividindo
                    // pela quantidade, nunca o contrário. quantidade_produto
                    // nunca é 0 nos dados reais, mas guard defensivo mesmo
                    // assim (evita DivisionByZeroError num ETL de produção).
                    $unitPriceCents = $quantity > 0.0 ? (int) round($lineTotalCents / $quantity) : $lineTotalCents;

                    $itemRows[] = [
                        'uuid' => $item['uuid'] ?? (string) Str::uuid(),
                        'tenant_id' => $tenantId,
                        'order_id' => $orderId,
                        'product_id' => $novoProdutoId,
                        'quantity' => $quantity,
                        'unit_price' => number_format($unitPriceCents / 100, 2, '.', ''),
                        'line_total' => number_format($lineTotalCents / 100, 2, '.', ''),
                        'created_at' => $item['inclusao_data'] ?? $createdAt,
                        'updated_at' => $item['alteracao_data'] ?? $item['inclusao_data'] ?? $updatedAt,
                    ];
                }

                if ($itemRows !== []) {
                    DB::table('order_items')->insert($itemRows);
                }
            }
        }
    }

    /**
     * 1 payment sintético por pedido pago (decisão 3 do usuário).
     */
    private function migratePayments(int $tenantId, array $pedidos): void
    {
        $orderIdsByUuid = DB::table('orders')->where('tenant_id', $tenantId)->pluck('id', 'uuid');

        foreach (array_chunk($pedidos, self::CHUNK_SIZE) as $chunk) {
            $rows = [];

            foreach ($chunk as $pedido) {
                if ((int) $pedido['pago'] !== 1) {
                    continue;
                }

                $orderId = $orderIdsByUuid[$pedido['uuid']] ?? null;
                if (!$orderId) {
                    continue;
                }

                $paidAt = $this->sanitizeDate($pedido['data_pagamento'] ?? null) ?? ($pedido['inclusao_data'] ?? now());

                $rows[] = [
                    'uuid' => (string) Str::uuid(),
                    'payable_type' => Order::class,
                    'payable_id' => $orderId,
                    'provider' => 'manual',
                    'provider_charge_id' => null,
                    'method' => null,
                    'amount' => number_format((float) $pedido['valor_pago'], 2, '.', ''),
                    'status' => 'paid',
                    'paid_at' => $paidAt,
                    'idempotency_key' => null,
                    'created_at' => $paidAt,
                    'updated_at' => $paidAt,
                ];
            }

            if ($rows !== []) {
                DB::table('payments')->insert($rows);
            }
        }
    }

    /**
     * Achado documentado em `ImportLegacyJsQueijosCommand` (mesma fonte):
     * alguns `data_pagamento`/`data_entrega` legados têm ano corrompido
     * (ex. "0223-02-20", "3023-05-10"). `orders.paid_at`/`delivered_at` são
     * TIMESTAMP (range 1970–2038) — data implausível vira null, a flag
     * booleana continua sendo a fonte de verdade de negócio.
     */
    private function sanitizeDate(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $year = (int) substr($value, 0, 4);

        if ($year < 2000 || $year > (int) now()->format('Y') + 1) {
            return null;
        }

        return $value;
    }

    private function fallbackUf(string $name): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $name) ?: $name;
        $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', $ascii));

        return substr($letters, 0, 3) ?: 'XXX';
    }

    /**
     * Só para exibição no início da execução — nunca decide nada sozinho,
     * é o operador conferindo visualmente antes de confirmar `--commit`.
     */
    private function describeConnectionTarget(string $connectionName): string
    {
        $cfg = config("database.connections.{$connectionName}", []);

        return match ($cfg['driver'] ?? '?') {
            'sqlite' => 'sqlite file=' . ($cfg['database'] ?? '?'),
            'mysql', 'mariadb' => 'mysql host=' . ($cfg['host'] ?? '?') . ' db=' . ($cfg['database'] ?? '?'),
            default => ($cfg['driver'] ?? 'driver desconhecido'),
        };
    }
}
