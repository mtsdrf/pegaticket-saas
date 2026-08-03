<?php

namespace App\Console\Commands;

use App\Models\Sale\Sale;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill de sales.codigo (2026-07-15) — vendas criados antes do campo
 * existir não têm código de exibição. Por tenant, atribui sequencialmente
 * a partir de 1000 + (quantidade de vendas daquele tenant que já têm
 * código), na ordem de criação (sales.id asc), e ao final sincroniza
 * tenants.next_sale_code para o próximo valor livre — mesma regra de
 * sequência usada em SaleService::create(). Idempotente: vendas que já
 * têm codigo são ignorados.
 */
class BackfillSaleCodigoCommand extends Command
{
    protected $signature = 'sales:backfill-codigo';

    protected $description = 'Atribui sales.codigo sequencial (por tenant) aos vendas que ainda não têm.';

    public function handle(): int
    {
        $tenantIds = Sale::query()
            ->whereNull('codigo')
            ->distinct()
            ->pluck('tenant_id');

        $totalAssigned = 0;

        foreach ($tenantIds as $tenantId) {
            $assigned = DB::transaction(function () use ($tenantId) {
                $alreadyCoded = Sale::where('tenant_id', $tenantId)
                    ->whereNotNull('codigo')
                    ->count();

                // Convenção de tenants.next_sale_code: guarda o ÚLTIMO
                // código emitido (não o próximo), mesma semântica de
                // SaleService::create() (increment-then-read). Começa em
                // 999 + já-codificados pra que o primeiro código atribuído
                // aqui seja 1000 + já-codificados.
                $lastAssigned = 999 + $alreadyCoded;
                $assignedForTenant = 0;

                Sale::where('tenant_id', $tenantId)
                    ->whereNull('codigo')
                    ->orderBy('id')
                    ->chunkById(200, function ($sales) use (&$lastAssigned, &$assignedForTenant) {
                        foreach ($sales as $order) {
                            $lastAssigned++;
                            $order->codigo = (string) $lastAssigned;
                            $order->save();
                            $assignedForTenant++;
                        }
                    });

                DB::table('tenants')->where('id', $tenantId)->update(['next_sale_code' => $lastAssigned]);

                return $assignedForTenant;
            });

            $totalAssigned += $assigned;
        }

        $this->info("{$totalAssigned} venda(s) com código atribuído em " . $tenantIds->count() . " tenant(s).");

        return self::SUCCESS;
    }
}
