<?php

namespace App\Services\Tenant;

use App\Events\Tenant\TenantDataExported;
use App\Models\Sale\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use ZipArchive;

/**
 * Exportação self-service dos dados do tenant (roadmap A1.2) — generaliza
 * os exports por tela já existentes (PDF de vendas/clientes em
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

        $zip->addFromString('customers.csv', $this->customersCsv($tenantId));
        $zip->addFromString('ticket_types.csv', $this->productsCsv($tenantId));
        $zip->addFromString('sales.csv', $this->salesCsv($tenantId));

        $zip->close();

        $content = file_get_contents($tempPath);
        unlink($tempPath);

        event(new TenantDataExported(tenantId: $tenantId, actorId: (int) Auth::id()));

        return [
            'content' => $content,
            'filename' => 'meus-dados-' . now()->format('Ymd_His') . '.zip',
        ];
    }

    /**
     * FinalCustomer absorveu Client (2026-07-31): "clientes desta loja"
     * agora é final_customer_tenant_links (registro por-tenant) + nome/
     * e-mail do FinalCustomer global vinculado.
     */
    private function customersCsv(int $tenantId): string
    {
        $rows = DB::table('final_customer_tenant_links')
            ->join('final_customers', 'final_customers.id', '=', 'final_customer_tenant_links.final_customer_id')
            ->where('final_customer_tenant_links.tenant_id', $tenantId)
            ->orderBy('final_customers.name')
            ->select([
                'final_customers.uuid as uuid',
                'final_customers.name as name',
                'final_customer_tenant_links.phone_primary as phone_primary',
                'final_customer_tenant_links.phone_secondary as phone_secondary',
                'final_customer_tenant_links.is_trusted as is_trusted',
                'final_customer_tenant_links.is_active as is_active',
                'final_customer_tenant_links.created_at as created_at',
            ])
            ->get();

        return $this->toCsv(
            ['uuid', 'name', 'phone_primary', 'phone_secondary', 'is_trusted', 'is_active', 'created_at'],
            $rows
        );
    }

    private function productsCsv(int $tenantId): string
    {
        $rows = DB::table('ticket_types')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->select(['uuid', 'name', 'price', 'status', 'quantity_available', 'created_at'])
            ->get();

        return $this->toCsv(
            ['uuid', 'name', 'price', 'status', 'quantity_available', 'created_at'],
            $rows
        );
    }

    private function salesCsv(int $tenantId): string
    {
        $rows = DB::table('sales')
            ->join('final_customers', 'final_customers.id', '=', 'sales.final_customer_id')
            ->where('sales.tenant_id', $tenantId)
            ->whereNull('sales.deleted_at')
            ->orderByDesc('sales.id')
            ->select([
                'sales.uuid as uuid',
                'final_customers.name as client_name',
                'sales.total_amount as total_amount',
                'sales.origin as origin',
                'sales.is_paid as is_paid',
                'sales.cancelled_at as cancelled_at',
                'sales.created_at as created_at',
            ])
            ->get();

        $rows->transform(function (object $row) {
            $row->origin = Sale::normalizeOrigin($row->origin);

            return $row;
        });

        return $this->toCsv(
            ['uuid', 'client_name', 'total_amount', 'origin', 'is_paid', 'cancelled_at', 'created_at'],
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
