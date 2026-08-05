<?php

namespace App\Console\Commands;

use App\Mail\RecompraNudgeMail;
use App\Models\Sale\Sale;
use App\Services\Communication\CommunicationDispatcherService;
use App\Services\Logging\ApplicationLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Automação de recompra (roadmap Fase 6, fatia final) — identifica, por
 * (tenant, comprador), a venda PAGA mais recente; quando essa venda tem
 * mais de N dias (--days, default técnico 60 — não validado com o
 * usuário) e o comprador não comprou de novo desde então, envia um e-mail
 * "sentimos sua falta" (RecompraNudgeMail) e marca
 * sales.recompra_nudge_sent_at nessa venda. Se o comprador comprar de
 * novo, a venda mais recente passa a ser outra (mais nova, dentro da
 * janela) e ele naturalmente sai da lista — sem precisar "desmarcar" nada.
 * Mesmo espírito de SendEventReminderMailsCommand (reaproveita Mail já
 * existente, marca a data pra não duplicar envio).
 */
class SendRecompraNudgeMailsCommand extends Command
{
    protected $signature = 'sales:send-recompra-nudges {--days=60}';

    protected $description = 'Envia e-mail de recompra para compradores que não compram há mais de N dias (default 60).';

    public function handle(CommunicationDispatcherService $communicationDispatcher): int
    {
        $days = max(1, (int) $this->option('days'));
        $threshold = now()->subDays($days);

        $latestPairs = DB::table('sales')
            ->select('tenant_id', 'final_customer_id', DB::raw('MAX(paid_at) as latest_paid_at'))
            ->where('is_paid', true)
            ->whereNull('cancelled_at')
            ->whereNull('deleted_at')
            ->groupBy('tenant_id', 'final_customer_id')
            ->having('latest_paid_at', '<=', $threshold)
            ->get();

        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($latestPairs as $pair) {
            $sale = Sale::where('tenant_id', $pair->tenant_id)
                ->where('final_customer_id', $pair->final_customer_id)
                ->where('paid_at', $pair->latest_paid_at)
                ->whereNull('recompra_nudge_sent_at')
                ->whereNull('deleted_at')
                ->with(['tenant', 'finalCustomer'])
                ->first();

            if (! $sale || ! $sale->tenant || ! $sale->finalCustomer) {
                $skipped++;

                continue;
            }

            $email = trim((string) ($sale->finalCustomer->email ?? ''));

            if ($email === '') {
                $skipped++;

                continue;
            }

            try {
                $storefrontUrl = rtrim((string) config('app.frontend_url'), '/').'/eventos/'.$sale->tenant->slug;

                $communicationDispatcher->send(
                    'recompra_nudge',
                    new RecompraNudgeMail(
                        tenant: $sale->tenant,
                        finalCustomer: $sale->finalCustomer,
                        storefrontUrl: $storefrontUrl,
                    ),
                    $email,
                    $sale->tenant_id
                );

                $sale->forceFill(['recompra_nudge_sent_at' => now()])->save();
                $sent++;
            } catch (\Throwable $e) {
                $failed++;

                ApplicationLogger::error('Falha ao enviar e-mail de recompra', [
                    'sale_uuid' => $sale->uuid,
                    'exception_class' => get_class($e),
                    'exception_message' => $e->getMessage(),
                ]);
            }
        }

        $this->info('Pares comprador/tenant elegíveis: '.count($latestPairs).'.');
        $this->info("Nudges enviados: {$sent}.");
        $this->info("Ignorados (sem venda elegível/sem e-mail): {$skipped}.");
        $this->info("Falhas: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
