<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\AccountingReportPeriodRequest;
use App\Services\Accounting\AccountingReportService;
use App\Services\APIResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Relatórios do contador (roadmap 2C). `accounting.jwt` + `ResolveAccountingTenant`
 * (o tenant vem do path param {tenant_uuid}, validado contra o vínculo
 * aprovado). Cada leitura é auditada dentro do AccountingReportService.
 * `?format=csv` transmite CSV via StreamedResponse (não há helper de export no
 * projeto — construído inline aqui, minimal e sem dependência nova).
 */
class AccountingReportController extends Controller
{
    public function __construct(
        private AccountingReportService $service
    ) {
    }

    public function sales(AccountingReportPeriodRequest $request)
    {
        $v = $request->validated();
        $data = $this->service->sales(app('tenant_id'), $v['from'] ?? null, $v['to'] ?? null);

        if (($v['format'] ?? 'json') === 'csv') {
            return $this->csv('vendas', ['order_uuid', 'client_name', 'created_at', 'total_amount', 'is_paid', 'is_delivered'], $data['items']);
        }

        return APIResponse::success($data, __('messages.accounting_report.sales'));
    }

    public function cashFlow(AccountingReportPeriodRequest $request)
    {
        $v = $request->validated();
        $data = $this->service->cashFlow(app('tenant_id'), $v['from'] ?? null, $v['to'] ?? null);

        if (($v['format'] ?? 'json') === 'csv') {
            return $this->csv('livro-caixa', ['date', 'order_uuid', 'client_name', 'amount'], $data['entries']);
        }

        return APIResponse::success($data, __('messages.accounting_report.cash_flow'));
    }

    public function dre(AccountingReportPeriodRequest $request)
    {
        $v = $request->validated();
        $data = $this->service->dre(app('tenant_id'), $v['from'] ?? null, $v['to'] ?? null);

        if (($v['format'] ?? 'json') === 'csv') {
            return $this->csv('dre', ['from', 'to', 'revenue', 'product_cost', 'gross_profit'], [$data]);
        }

        return APIResponse::success($data, __('messages.accounting_report.dre'));
    }

    /**
     * @param list<string>              $columns
     * @param array<int, array<string, mixed>> $rows
     */
    private function csv(string $name, array $columns, array $rows): StreamedResponse
    {
        $filename = $name . '-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($columns, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            foreach ($rows as $row) {
                fputcsv($handle, array_map(
                    fn ($col) => $this->scalar($row[$col] ?? null),
                    $columns
                ));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function scalar(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) ($value ?? '');
    }
}
