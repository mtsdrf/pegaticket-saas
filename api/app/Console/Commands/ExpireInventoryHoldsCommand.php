<?php

namespace App\Console\Commands;

use App\Models\Inventory\InventoryHold;
use Illuminate\Console\Command;

/**
 * Varredura proativa de holds expirados (spec 5.9 — endurecimento de
 * disponibilidade, roadmap Fase 1). StorefrontHoldService já expira
 * "on read" (toda chamada a availability()/createHold()/showHold() varre
 * o próprio tenant+evento antes de agir), o que já impede overbooking —
 * mas um hold de um evento que ninguém mais consulta fica com
 * status='reservado' indefinidamente no banco até esse read acontecer,
 * o que distorce relatório/observabilidade administrativa. Este comando
 * fecha esse gap sem duplicar a regra de negócio (mesmo update usado em
 * StorefrontHoldService::expireHolds()).
 */
class ExpireInventoryHoldsCommand extends Command
{
    protected $signature = 'inventory:expire-holds';

    protected $description = 'Marca como expirado todo hold de inventário reservado cujo prazo já venceu (varredura global, todos os tenants).';

    public function handle(): int
    {
        $expired = InventoryHold::query()
            ->where('status', InventoryHold::STATUS_RESERVED)
            ->where('expires_at', '<=', now())
            ->update([
                'status' => InventoryHold::STATUS_EXPIRED,
                'updated_at' => now(),
            ]);

        $this->info("Holds expirados nesta varredura: {$expired}.");

        return self::SUCCESS;
    }
}
