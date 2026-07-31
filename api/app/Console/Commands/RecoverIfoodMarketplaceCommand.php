<?php

namespace App\Console\Commands;

use App\Services\Marketplace\MarketplaceIntegrationService;
use Illuminate\Console\Command;

class RecoverIfoodMarketplaceCommand extends Command
{
    protected $signature = 'marketplace:recover-ifood {--limit=20} {--events=20} {--orders=20}';

    protected $description = 'Reprocessa falhas recentes e tenta recuperar pedidos externos pendentes do iFood.';

    public function handle(MarketplaceIntegrationService $service): int
    {
        $limit = max((int) $this->option('limit'), 1);
        $eventLimit = max((int) $this->option('events'), 1);
        $orderLimit = max((int) $this->option('orders'), 1);

        $integrations = $service->dueForRecovery($limit);

        foreach ($integrations as $integration) {
            try {
                $result = $service->recoverIntegration($integration, $eventLimit, $orderLimit);
                $this->info(sprintf(
                    '[%s] %d evento(s) reprocessado(s), %d pedido(s) resincronizado(s).',
                    $integration->name,
                    $result['retried_events'],
                    $result['refreshed_orders']
                ));
            } catch (\Throwable $e) {
                $this->error(sprintf('[%s] %s', $integration->name, $e->getMessage()));
            }
        }

        return self::SUCCESS;
    }
}
