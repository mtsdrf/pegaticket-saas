<?php

namespace App\Console\Commands;

use App\Services\Storefront\ReactivationDispatchService;
use Illuminate\Console\Command;

/**
 * Passe único diário (roadmap A5, item 18): para cada tenant com régua de
 * reativação ativa, gera cupom + push para clientes sem pedido há N dias.
 * Registrado em routes/console.php via Schedule::command(...)->daily(),
 * mesmo padrão de cashback:process.
 */
class ProcessReactivationRulesCommand extends Command
{
    protected $signature = 'reactivation:process';

    protected $description = 'Gera cupom de reativação + push para clientes inativos, por tenant com régua ativa.';

    public function handle(ReactivationDispatchService $service): int
    {
        $dispatched = $service->processAll();

        $this->info("Reativação: {$dispatched} cupom(ns) de reativação disparado(s).");

        return self::SUCCESS;
    }
}
