<?php

namespace App\Console\Commands;

use App\Models\Order\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill de orders.codigo (2026-07-15) — pedidos criados antes do campo
 * existir não têm código de exibição. Por tenant, atribui sequencialmente
 * a partir de 1000 + (quantidade de pedidos daquele tenant que já têm
 * código), na ordem de criação (orders.id asc), e ao final sincroniza
 * tenants.next_order_code para o próximo valor livre — mesma regra de
 * sequência usada em OrderService::create(). Idempotente: pedidos que já
 * têm codigo são ignorados.
 */
class BackfillOrderCodigoCommand extends Command
{
    protected $signature = 'orders:backfill-codigo';

    protected $description = 'Atribui orders.codigo sequencial (por tenant) aos pedidos que ainda não têm.';

    public function handle(): int
    {
        $tenantIds = Order::query()
            ->whereNull('codigo')
            ->distinct()
            ->pluck('tenant_id');

        $totalAssigned = 0;

        foreach ($tenantIds as $tenantId) {
            $assigned = DB::transaction(function () use ($tenantId) {
                $alreadyCoded = Order::where('tenant_id', $tenantId)
                    ->whereNotNull('codigo')
                    ->count();

                // Convenção de tenants.next_order_code: guarda o ÚLTIMO
                // código emitido (não o próximo), mesma semântica de
                // OrderService::create() (increment-then-read). Começa em
                // 999 + já-codificados pra que o primeiro código atribuído
                // aqui seja 1000 + já-codificados.
                $lastAssigned = 999 + $alreadyCoded;
                $assignedForTenant = 0;

                Order::where('tenant_id', $tenantId)
                    ->whereNull('codigo')
                    ->orderBy('id')
                    ->chunkById(200, function ($orders) use (&$lastAssigned, &$assignedForTenant) {
                        foreach ($orders as $order) {
                            $lastAssigned++;
                            $order->codigo = (string) $lastAssigned;
                            $order->save();
                            $assignedForTenant++;
                        }
                    });

                DB::table('tenants')->where('id', $tenantId)->update(['next_order_code' => $lastAssigned]);

                return $assignedForTenant;
            });

            $totalAssigned += $assigned;
        }

        $this->info("{$totalAssigned} pedido(s) com código atribuído em " . $tenantIds->count() . " tenant(s).");

        return self::SUCCESS;
    }
}
