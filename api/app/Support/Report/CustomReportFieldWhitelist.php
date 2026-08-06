<?php

namespace App\Support\Report;

/**
 * Fonte de verdade ÚNICA das colunas que o construtor de relatórios
 * personalizados (roadmap 5.6) pode ler. Requisito de segurança obrigatório
 * do roadmap: "fonte de dados, dimensões e métricas são sempre selecionadas
 * de uma WHITELIST fixa no código". O usuário nunca envia nome de
 * tabela/coluna — só a CHAVE (ex. `ticket_type`), que é traduzida aqui para
 * uma expressão SQL fixa escrita por nós. Nenhum valor deste array chega a
 * ser montado a partir de input do usuário.
 *
 * Cada data_source define:
 *  - table: tabela base (sempre tenant-scoped por `tenant_id`)
 *  - joins: lista fixa de joins necessários pras dimensões dessa fonte
 *  - date_column: coluna usada pelo filtro especial date_from/date_to
 *  - dimensions: chave => ['expr' => <coluna/expr SQL fixa>, 'label' => ...]
 *  - metrics: chave => ['expr' => <expressão agregada SQL fixa>, 'label' => ...]
 *
 * Reaproveita os mesmos campos já usados por App\Services\Report\AnalyticsService
 * e App\Services\Report\ReportService (mesma fonte real de dados, só que
 * aqui exposta via chave fixa em vez de branch de código por relatório).
 */
class CustomReportFieldWhitelist
{
    public const SALES = 'sales';

    public const PAYMENTS = 'payments';

    public const CHECKINS = 'checkins';

    public const FINANCE = 'finance';

    public const CRM = 'crm';

    private static ?array $config = null;

    public static function dataSources(): array
    {
        return array_keys(self::config());
    }

    public static function isValidDataSource(string $dataSource): bool
    {
        return array_key_exists($dataSource, self::config());
    }

    public static function baseTable(string $dataSource): string
    {
        return self::sourceConfig($dataSource)['table'];
    }

    public static function joins(string $dataSource): array
    {
        return self::sourceConfig($dataSource)['joins'] ?? [];
    }

    public static function dateColumn(string $dataSource): string
    {
        return self::sourceConfig($dataSource)['date_column'];
    }

    public static function tenantColumn(string $dataSource): string
    {
        return self::sourceConfig($dataSource)['tenant_column'];
    }

    /**
     * Tabelas (base + joins) que têm soft delete e precisam de
     * `whereNull(<table>.deleted_at)` sempre aplicado.
     */
    public static function softDeleteTables(string $dataSource): array
    {
        return self::sourceConfig($dataSource)['soft_delete_tables'] ?? [];
    }

    /**
     * `sales`/`payments`: venda cancelada nunca conta em nenhum indicador
     * (decisão de produto já registrada em ReportService/AnalyticsService)
     * — replicada aqui via `sales.cancelled_at IS NULL` fixo.
     */
    public static function excludeCancelledSales(string $dataSource): bool
    {
        return in_array($dataSource, [self::SALES, self::PAYMENTS], true);
    }

    public static function dimensions(string $dataSource): array
    {
        return self::sourceConfig($dataSource)['dimensions'];
    }

    public static function metrics(string $dataSource): array
    {
        return self::sourceConfig($dataSource)['metrics'];
    }

    public static function isValidDimension(string $dataSource, string $dimension): bool
    {
        return array_key_exists($dimension, self::dimensions($dataSource));
    }

    public static function isValidMetric(string $dataSource, string $metric): bool
    {
        return array_key_exists($metric, self::metrics($dataSource));
    }

    public static function dimensionExpr(string $dataSource, string $dimension): string
    {
        return self::dimensions($dataSource)[$dimension]['expr'];
    }

    public static function metricExpr(string $dataSource, string $metric): string
    {
        return self::metrics($dataSource)[$metric]['expr'];
    }

    private static function sourceConfig(string $dataSource): array
    {
        $config = self::config();

        if (! array_key_exists($dataSource, $config)) {
            throw new \InvalidArgumentException("Fonte de dados desconhecida: {$dataSource}");
        }

        return $config[$dataSource];
    }

    private static function config(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        return self::$config = [
            self::SALES => [
                'table' => 'sales',
                'tenant_column' => 'sales.tenant_id',
                'date_column' => 'sales.created_at',
                'joins' => [],
                'soft_delete_tables' => ['sales'],
                'dimensions' => [
                    'status' => ['expr' => 'sales.status', 'label' => 'Status da venda'],
                    'origin' => ['expr' => 'sales.origin', 'label' => 'Origem da venda'],
                    'channel' => ['expr' => 'sales.channel', 'label' => 'Canal de venda'],
                    'payment_method' => ['expr' => 'sales.payment_method', 'label' => 'Meio de pagamento'],
                    'month' => ['expr' => "DATE_FORMAT(sales.created_at, '%Y-%m')", 'label' => 'Mês da venda'],
                    'day' => ['expr' => 'DATE(sales.created_at)', 'label' => 'Dia da venda'],
                ],
                'metrics' => [
                    'sale_count' => ['expr' => 'COUNT(DISTINCT sales.id)', 'label' => 'Quantidade de vendas'],
                    'total_revenue' => ['expr' => 'SUM(sales.total_amount)', 'label' => 'Receita total'],
                    'average_ticket' => ['expr' => 'AVG(sales.total_amount)', 'label' => 'Ticket médio'],
                    'discount_total' => ['expr' => 'SUM(sales.discount_amount)', 'label' => 'Desconto total'],
                    'paid_amount_total' => ['expr' => 'SUM(sales.paid_amount)', 'label' => 'Total pago'],
                ],
            ],
            self::PAYMENTS => [
                'table' => 'sales',
                'tenant_column' => 'sales.tenant_id',
                'date_column' => 'sales.created_at',
                'joins' => [],
                'soft_delete_tables' => ['sales'],
                'dimensions' => [
                    'status' => ['expr' => 'sales.status', 'label' => 'Status do pagamento'],
                    'payment_method' => ['expr' => 'sales.payment_method', 'label' => 'Meio de pagamento'],
                    'month' => ['expr' => "DATE_FORMAT(sales.created_at, '%Y-%m')", 'label' => 'Mês'],
                ],
                'metrics' => [
                    'sale_count' => ['expr' => 'COUNT(DISTINCT sales.id)', 'label' => 'Quantidade de vendas'],
                    'total_revenue' => ['expr' => 'SUM(sales.total_amount)', 'label' => 'Valor total'],
                    'paid_amount_total' => ['expr' => 'SUM(sales.paid_amount)', 'label' => 'Total pago'],
                ],
            ],
            self::CHECKINS => [
                'table' => 'ticket_checkins',
                'tenant_column' => 'ticket_checkins.tenant_id',
                'date_column' => 'ticket_checkins.checked_in_at',
                'joins' => [
                    ['table' => 'tickets', 'first' => 'tickets.id', 'operator' => '=', 'second' => 'ticket_checkins.ticket_id'],
                    ['table' => 'ticket_types', 'first' => 'ticket_types.id', 'operator' => '=', 'second' => 'tickets.ticket_type_id'],
                ],
                'soft_delete_tables' => ['ticket_checkins', 'tickets', 'ticket_types'],
                'dimensions' => [
                    'result' => ['expr' => 'ticket_checkins.result', 'label' => 'Resultado do check-in'],
                    'gate_name' => ['expr' => 'ticket_checkins.gate_name', 'label' => 'Portaria'],
                    'ticket_type' => ['expr' => 'ticket_types.name', 'label' => 'Tipo de ingresso'],
                    'month' => ['expr' => "DATE_FORMAT(ticket_checkins.checked_in_at, '%Y-%m')", 'label' => 'Mês'],
                ],
                'metrics' => [
                    'checkin_count' => ['expr' => 'COUNT(DISTINCT ticket_checkins.id)', 'label' => 'Quantidade de leituras'],
                    'unique_ticket_count' => ['expr' => 'COUNT(DISTINCT ticket_checkins.ticket_id)', 'label' => 'Ingressos únicos lidos'],
                ],
            ],
            self::FINANCE => [
                'table' => 'receivables',
                'tenant_column' => 'receivables.tenant_id',
                'date_column' => 'receivables.created_at',
                'joins' => [],
                'soft_delete_tables' => ['receivables'],
                'dimensions' => [
                    'status' => ['expr' => 'receivables.status', 'label' => 'Status do recebível'],
                    'provider' => ['expr' => 'receivables.provider', 'label' => 'Provedor'],
                    'month' => ['expr' => "DATE_FORMAT(receivables.created_at, '%Y-%m')", 'label' => 'Mês'],
                ],
                'metrics' => [
                    'receivable_count' => ['expr' => 'COUNT(DISTINCT receivables.id)', 'label' => 'Quantidade de recebíveis'],
                    'gross_total' => ['expr' => 'SUM(receivables.gross_amount)', 'label' => 'Bruto total'],
                    'net_total' => ['expr' => 'SUM(receivables.net_amount)', 'label' => 'Líquido total'],
                    'platform_fee_total' => ['expr' => 'SUM(receivables.platform_fee_amount)', 'label' => 'Taxa da plataforma total'],
                    'processor_fee_total' => ['expr' => 'SUM(receivables.processor_fee_amount)', 'label' => 'Taxa do processador total'],
                ],
            ],
            self::CRM => [
                'table' => 'final_customer_tenant_links',
                'tenant_column' => 'final_customer_tenant_links.tenant_id',
                'date_column' => 'final_customer_tenant_links.created_at',
                'joins' => [],
                'dimensions' => [
                    'is_trusted' => ['expr' => 'final_customer_tenant_links.is_trusted', 'label' => 'Cliente confiável'],
                    'is_active' => ['expr' => 'final_customer_tenant_links.is_active', 'label' => 'Cliente ativo'],
                    'month' => ['expr' => "DATE_FORMAT(final_customer_tenant_links.created_at, '%Y-%m')", 'label' => 'Mês de cadastro'],
                ],
                'metrics' => [
                    'client_count' => ['expr' => 'COUNT(DISTINCT final_customer_tenant_links.id)', 'label' => 'Quantidade de clientes'],
                ],
            ],
        ];
    }
}
