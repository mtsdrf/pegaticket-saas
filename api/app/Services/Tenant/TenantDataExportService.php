<?php

namespace App\Services\Tenant;

use App\Events\Tenant\TenantDataExported;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use ZipArchive;

/**
 * Exportação self-service dos dados do tenant (roadmap A1.2) — generaliza
 * os exports por tela já existentes (PDF de pedidos/clientes em
 * ReportService) num pacote único, 1 CSV por entidade principal. Não
 * reaproveita ReportService (que é focado em relatório filtrado/paginado
 * pra tela); aqui é sempre o dado bruto e completo do tenant, sem filtro.
 * ZipArchive precisa de um arquivo real em disco (não escreve direto em
 * memória) — usa um temp file descartado ao final, nunca fica em
 * storage/app (não é um artefato persistente, é gerado e servido na hora).
 */
class TenantDataExportService
{
    /**
     * @return array{content: string, filename: string}
     */
    public function export(int $tenantId): array
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'tenant-export-');

        $zip = new ZipArchive();
        $zip->open($tempPath, ZipArchive::OVERWRITE);

        $zip->addFromString('clients.csv', $this->clientsCsv($tenantId));
        $zip->addFromString('products.csv', $this->productsCsv($tenantId));
        $zip->addFromString('orders.csv', $this->ordersCsv($tenantId));

        $zip->close();

        $content = file_get_contents($tempPath);
        unlink($tempPath);

        event(new TenantDataExported(tenantId: $tenantId, actorId: (int) Auth::id()));

        return [
            'content' => $content,
            'filename' => 'meus-dados-' . now()->format('Ymd_His') . '.zip',
        ];
    }

    private function clientsCsv(int $tenantId): string
    {
        $rows = DB::table('clients')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->select(['uuid', 'name', 'phone_primary', 'phone_secondary', 'is_trusted', 'is_active', 'created_at'])
            ->get();

        return $this->toCsv(
            ['uuid', 'name', 'phone_primary', 'phone_secondary', 'is_trusted', 'is_active', 'created_at'],
            $rows
        );
    }

    private function productsCsv(int $tenantId): string
    {
        $rows = DB::table('products')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->select(['uuid', 'name', 'price', 'is_available', 'stock_quantity', 'created_at'])
            ->get();

        return $this->toCsv(
            ['uuid', 'name', 'price', 'is_available', 'stock_quantity', 'created_at'],
            $rows
        );
    }

    private function ordersCsv(int $tenantId): string
    {
        $rows = DB::table('orders')
            ->join('clients', 'clients.id', '=', 'orders.client_id')
            ->where('orders.tenant_id', $tenantId)
            ->whereNull('orders.deleted_at')
            ->orderByDesc('orders.id')
            ->select([
                'orders.uuid as uuid',
                'clients.name as client_name',
                'orders.total_amount as total_amount',
                'orders.origin as origin',
                'orders.is_paid as is_paid',
                'orders.is_delivered as is_delivered',
                'orders.cancelled_at as cancelled_at',
                'orders.created_at as created_at',
            ])
            ->get();

        return $this->toCsv(
            ['uuid', 'client_name', 'total_amount', 'origin', 'is_paid', 'is_delivered', 'cancelled_at', 'created_at'],
            $rows
        );
    }

    /**
     * @param list<string> $header
     * @param \Illuminate\Support\Collection<int, object> $rows
     */
    private function toCsv(array $header, $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, $header);

        foreach ($rows as $row) {
            fputcsv($handle, array_map(
                fn (string $column) => $row->$column,
                $header
            ));
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
