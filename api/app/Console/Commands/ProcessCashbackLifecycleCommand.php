<?php

namespace App\Console\Commands;

use App\Models\Storefront\CashbackEarning;
use Illuminate\Console\Command;

/**
 * Passe único diário (roadmap Delivery, Fase 5): (1) promove lotes pending→
 * available quando a carência (available_at) já venceu; (2) expira lotes
 * available com saldo ainda não consumido quando expires_at já venceu.
 * Registrado em routes/console.php via Schedule::command(...)->daily().
 */
class ProcessCashbackLifecycleCommand extends Command
{
    protected $signature = 'cashback:process';

    protected $description = 'Libera cashback em carência e expira cashback vencido.';

    public function handle(): int
    {
        $released = CashbackEarning::where('status', 'pending')
            ->where('available_at', '<=', now())
            ->update(['status' => 'available']);

        $expired = CashbackEarning::where('status', 'available')
            ->where('remaining_amount', '>', 0)
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired', 'remaining_amount' => 0]);

        $this->info("Cashback: {$released} lote(s) liberado(s) da carência, {$expired} lote(s) expirado(s).");

        return self::SUCCESS;
    }
}
