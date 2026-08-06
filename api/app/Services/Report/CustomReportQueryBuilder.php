<?php

namespace App\Services\Report;

use App\Exceptions\InvalidReportDefinitionException;
use App\Exceptions\ReportExecutionException;
use App\Support\Report\CustomReportFieldWhitelist;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Monta e executa a query de um relatório personalizado (roadmap 5.6) só a
 * partir de CHAVES já validadas contra App\Support\Report\CustomReportFieldWhitelist
 * — nunca a partir de string SQL vinda do payload do usuário. Requisitos de
 * segurança obrigatórios cobertos aqui:
 *
 *  1. Toda coluna/tabela vem de CustomReportFieldWhitelist::dimensionExpr()/
 *     metricExpr(), nunca de `$request` diretamente.
 *  2. Métricas calculadas são resolvidas por CustomReportFormulaValidator
 *     (ExpressionLanguage sandboxado) sobre a LINHA JÁ AGREGADA pelo SQL,
 *     nunca linha-a-linha não agregada.
 *  3. `tenant_id` é aplicado aqui, no nível mais baixo da query, a partir
 *     de `$tenantId` (sempre `app('tenant_id')` no caller — nunca aceito
 *     do payload do usuário) — nenhuma combinação de dimensão/filtro
 *     escolhida pelo usuário consegue sobrescrever essa cláusula porque ela
 *     nunca é construída a partir de input.
 *  4. Limites de complexidade (MAX_DIMENSIONS/MAX_METRICS/
 *     MAX_CALCULATED_METRICS/MAX_FILTERS) + MAX_ROWS (hard cap antes de
 *     paginar) + paginação sempre aplicada (nunca retorna dataset
 *     completo).
 *  5. Qualquer erro de execução (\Throwable do driver) é capturado e
 *     relançado como ReportExecutionException genérica — a mensagem/stack
 *     trace original só vai pro log.
 */
class CustomReportQueryBuilder
{
    public const MAX_DIMENSIONS = 3;

    public const MAX_METRICS = 10;

    public const MAX_CALCULATED_METRICS = 5;

    public const MAX_FILTERS = 10;

    public const MAX_ROWS = 1000;

    public const DEFAULT_PER_PAGE = 20;

    public const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly CustomReportFormulaValidator $formulaValidator
    ) {}

    /**
     * Valida uma definição de relatório (data_source + dimensions + metrics
     * + calculated_metrics + filters) contra a whitelist e os limites de
     * complexidade. Usado tanto ao salvar quanto ao pré-visualizar/executar
     * ad-hoc — uma definição salva antes de a whitelist mudar é
     * revalidada aqui de novo na hora de executar, nunca confiada
     * cegamente.
     *
     * @throws InvalidReportDefinitionException
     */
    public function validateDefinition(
        string $dataSource,
        array $dimensions,
        array $metrics,
        array $calculatedMetrics,
        array $filters
    ): void {
        if (! CustomReportFieldWhitelist::isValidDataSource($dataSource)) {
            throw new InvalidReportDefinitionException("Fonte de dados inválida: {$dataSource}", 'INVALID_DATA_SOURCE');
        }

        if (count($dimensions) > self::MAX_DIMENSIONS) {
            throw new InvalidReportDefinitionException('Número máximo de dimensões excedido.', 'TOO_MANY_DIMENSIONS');
        }

        if (count($metrics) === 0) {
            throw new InvalidReportDefinitionException('Selecione ao menos uma métrica.', 'NO_METRICS');
        }

        if (count($metrics) > self::MAX_METRICS) {
            throw new InvalidReportDefinitionException('Número máximo de métricas excedido.', 'TOO_MANY_METRICS');
        }

        if (count($calculatedMetrics) > self::MAX_CALCULATED_METRICS) {
            throw new InvalidReportDefinitionException('Número máximo de métricas calculadas excedido.', 'TOO_MANY_CALCULATED_METRICS');
        }

        if (count($filters) > self::MAX_FILTERS) {
            throw new InvalidReportDefinitionException('Número máximo de filtros excedido.', 'TOO_MANY_FILTERS');
        }

        foreach ($dimensions as $dimension) {
            if (! is_string($dimension) || ! CustomReportFieldWhitelist::isValidDimension($dataSource, $dimension)) {
                throw new InvalidReportDefinitionException('Dimensão inválida: '.(is_string($dimension) ? $dimension : '?'), 'INVALID_DIMENSION');
            }
        }

        foreach ($metrics as $metric) {
            if (! is_string($metric) || ! CustomReportFieldWhitelist::isValidMetric($dataSource, $metric)) {
                throw new InvalidReportDefinitionException('Métrica inválida: '.(is_string($metric) ? $metric : '?'), 'INVALID_METRIC');
            }
        }

        $allowedVariableNames = array_values(array_filter($metrics, 'is_string'));
        $usedCalculatedNames = [];

        foreach ($calculatedMetrics as $calculated) {
            if (! is_array($calculated) || ! isset($calculated['name'], $calculated['formula'])
                || ! is_string($calculated['name']) || ! is_string($calculated['formula'])) {
                throw new InvalidReportDefinitionException('Métrica calculada mal formada.', 'INVALID_CALCULATED_METRIC');
            }

            $name = trim($calculated['name']);

            if ($name === '' || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,49}$/', $name)) {
                throw new InvalidReportDefinitionException('Nome de métrica calculada inválido.', 'INVALID_CALCULATED_METRIC_NAME');
            }

            if (in_array($name, $allowedVariableNames, true) || in_array($name, $usedCalculatedNames, true)) {
                throw new InvalidReportDefinitionException("Nome de métrica calculada duplicado: {$name}", 'DUPLICATE_CALCULATED_METRIC_NAME');
            }

            $usedCalculatedNames[] = $name;

            // A fórmula só pode referenciar métricas-base já selecionadas —
            // nunca outra métrica calculada (evita ciclo/ordem ambígua).
            $this->formulaValidator->validate($calculated['formula'], $allowedVariableNames);
        }

        foreach (array_keys($filters) as $filterKey) {
            if (! is_string($filterKey)) {
                throw new InvalidReportDefinitionException('Filtro inválido.', 'INVALID_FILTER');
            }

            if (in_array($filterKey, ['date_from', 'date_to'], true)) {
                continue;
            }

            if (! CustomReportFieldWhitelist::isValidDimension($dataSource, $filterKey)) {
                throw new InvalidReportDefinitionException("Filtro inválido: {$filterKey}", 'INVALID_FILTER');
            }
        }
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     *
     * @throws ReportExecutionException
     */
    public function execute(
        int $tenantId,
        string $dataSource,
        array $dimensions,
        array $metrics,
        array $calculatedMetrics,
        array $filters,
        int $page = 1,
        int $perPage = self::DEFAULT_PER_PAGE
    ): LengthAwarePaginator {
        $this->validateDefinition($dataSource, $dimensions, $metrics, $calculatedMetrics, $filters);

        $perPage = max(1, min($perPage, self::MAX_PER_PAGE));
        $page = max(1, $page);

        try {
            $rows = $this->fetchAggregatedRows($tenantId, $dataSource, $dimensions, $metrics, $filters);
        } catch (\Throwable $e) {
            Log::error('custom_report.execution_failed', [
                'tenant_id' => $tenantId,
                'data_source' => $dataSource,
                'exception' => $e->getMessage(),
            ]);

            throw new ReportExecutionException('Falha ao executar o relatório.', 0, $e);
        }

        $rows = $this->applyCalculatedMetrics($rows, $calculatedMetrics, $metrics);

        $total = $rows->count();
        $items = $rows->forPage($page, $perPage)->values();

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
        ]);
    }

    private function fetchAggregatedRows(
        int $tenantId,
        string $dataSource,
        array $dimensions,
        array $metrics,
        array $filters
    ): Collection {
        $table = CustomReportFieldWhitelist::baseTable($dataSource);
        $query = DB::table($table);

        foreach (CustomReportFieldWhitelist::joins($dataSource) as $join) {
            $query->join($join['table'], $join['first'], $join['operator'], $join['second']);
        }

        // tenant_id NUNCA vem do payload do usuário — só do contexto de
        // autenticação (`app('tenant_id')`) resolvido pelo caller. Aplicado
        // aqui, no nível mais baixo da query, antes de qualquer filtro
        // escolhido pelo usuário ser processado.
        $query->where(CustomReportFieldWhitelist::tenantColumn($dataSource), '=', $tenantId);

        foreach (CustomReportFieldWhitelist::softDeleteTables($dataSource) as $softDeleteTable) {
            $query->whereNull("{$softDeleteTable}.deleted_at");
        }

        if (CustomReportFieldWhitelist::excludeCancelledSales($dataSource)) {
            $query->whereNull('sales.cancelled_at');
        }

        $dateColumn = CustomReportFieldWhitelist::dateColumn($dataSource);

        if (isset($filters['date_from']) && is_string($filters['date_from']) && $filters['date_from'] !== '') {
            $query->whereDate($dateColumn, '>=', $filters['date_from']);
        }

        if (isset($filters['date_to']) && is_string($filters['date_to']) && $filters['date_to'] !== '') {
            $query->whereDate($dateColumn, '<=', $filters['date_to']);
        }

        foreach ($filters as $filterKey => $filterValue) {
            if (in_array($filterKey, ['date_from', 'date_to'], true)) {
                continue;
            }

            if ($filterValue === null || $filterValue === '') {
                continue;
            }

            if (! is_scalar($filterValue)) {
                continue;
            }

            $expr = CustomReportFieldWhitelist::dimensionExpr($dataSource, $filterKey);
            $query->whereRaw("{$expr} = ?", [$filterValue]);
        }

        $selects = [];
        $groupBys = [];

        foreach (array_values($dimensions) as $index => $dimension) {
            $expr = CustomReportFieldWhitelist::dimensionExpr($dataSource, $dimension);
            $alias = "dim_{$index}";
            $selects[] = DB::raw("{$expr} as `{$alias}`");
            $groupBys[] = DB::raw($expr);
        }

        foreach (array_values($metrics) as $metric) {
            $expr = CustomReportFieldWhitelist::metricExpr($dataSource, $metric);
            $selects[] = DB::raw("{$expr} as `{$metric}`");
        }

        $query->select($selects);

        if ($groupBys !== []) {
            $query->groupBy($groupBys);
        }

        if ($dimensions !== []) {
            $query->orderBy(DB::raw('dim_0'));
        }

        $this->applyBestEffortTimeout();

        $rows = $query->limit(self::MAX_ROWS)->get();

        return collect($rows)->map(function ($row) use ($dimensions, $metrics) {
            $arr = (array) $row;
            $mapped = [];

            foreach (array_values($dimensions) as $index => $dimension) {
                $mapped[$dimension] = $arr["dim_{$index}"] ?? null;
            }

            foreach (array_values($metrics) as $metric) {
                $mapped[$metric] = isset($arr[$metric]) ? (is_numeric($arr[$metric]) ? (float) $arr[$metric] : $arr[$metric]) : null;
            }

            return $mapped;
        });
    }

    private function applyCalculatedMetrics(Collection $rows, array $calculatedMetrics, array $metrics): Collection
    {
        if ($calculatedMetrics === []) {
            return $rows;
        }

        return $rows->map(function (array $row) use ($calculatedMetrics, $metrics) {
            $variables = [];

            foreach (array_values($metrics) as $metric) {
                $variables[$metric] = is_numeric($row[$metric] ?? null) ? (float) $row[$metric] : 0.0;
            }

            foreach ($calculatedMetrics as $calculated) {
                $row[$calculated['name']] = $this->formulaValidator->evaluate($calculated['formula'], $variables);
            }

            return $row;
        });
    }

    /**
     * Best-effort: limita o tempo de execução da query no servidor de
     * banco. MySQL >= 5.7.8 aceita `MAX_EXECUTION_TIME` (ms); MariaDB usa
     * `max_statement_time` (segundos, variável de sessão). Nenhuma das
     * duas é garantida em todo ambiente/driver — por isso o requisito de
     * segurança #4 é garantido de forma dura pelo MAX_ROWS acima; isto é
     * só uma camada extra, sem quebrar a execução se não suportado.
     */
    private function applyBestEffortTimeout(): void
    {
        try {
            DB::statement('SET SESSION MAX_EXECUTION_TIME=5000');
        } catch (\Throwable) {
            try {
                DB::statement('SET SESSION max_statement_time=5');
            } catch (\Throwable) {
                // Driver/versão não suporta nenhuma das duas — segue sem
                // timeout de sessão, MAX_ROWS continua sendo o limite
                // duro.
            }
        }
    }
}
