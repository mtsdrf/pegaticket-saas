<?php

namespace App\Console\Commands;

use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Services\Subscription\InvoiceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Gera faturas no fechamento de ciclo (roadmap 1B). Job diário: para cada
 * assinatura cobrável cujo next_charge_at já venceu, avança o período
 * (current_period_start/end + next_charge_at conforme billing_period) e
 * emite uma fatura. Registrado em routes/console.php (depende de
 * `schedule:run` no cron do servidor).
 */
class GenerateSubscriptionInvoicesCommand extends Command
{
    protected $signature = 'subscriptions:generate-invoices';

    protected $description = 'Gera faturas das assinaturas cujo ciclo fechou.';

    private const PERIOD_MONTHS = [
        'monthly' => 1,
        'quarterly' => 3,
        'yearly' => 12,
    ];

    public function __construct(
        private InvoiceRepositoryInterface $invoiceRepository,
        private InvoiceService $invoiceService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $subscriptions = $this->invoiceRepository->subscriptionsDueForCharge();
        $generated = 0;

        foreach ($subscriptions as $subscription) {
            DB::transaction(function () use ($subscription) {
                $months = self::PERIOD_MONTHS[$subscription->billing_period] ?? 1;

                $newStart = $subscription->next_charge_at ?? now();
                $newEnd = $newStart->copy()->addMonths($months);

                $subscription->fill([
                    'current_period_start' => $newStart,
                    'current_period_end' => $newEnd,
                    'next_charge_at' => $newEnd,
                ]);
                $subscription->save();

                $this->invoiceService->generateForCycle($subscription);
            });

            $generated++;
        }

        $this->info("Faturas geradas: {$generated}.");

        return self::SUCCESS;
    }
}
