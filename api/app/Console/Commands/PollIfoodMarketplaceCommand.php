<?php

namespace App\Console\Commands;

use App\Services\Marketplace\MarketplaceIntegrationService;
use Illuminate\Console\Command;

class PollIfoodMarketplaceCommand extends Command
{
    protected $signature = 'marketplace:poll-ifood {--limit=20}';

    protected $description = 'Executa o polling das integrações ativas do iFood.';

    public function handle(MarketplaceIntegrationService $service): int
    {
        $limit = max((int) $this->option('limit'), 1);
        $integrations = $service->dueForPolling($limit);

        foreach ($integrations as $integration) {
            try {
                $result = $service->pollEvents($integration);
                $this->info(sprintf(
                    '[%s] %d evento(s) processado(s), %d acknowledgment(s).',
                    $integration->name,
                    $result['processed'],
                    $result['acknowledged']
                ));
            } catch (\Throwable $e) {
                $this->error(sprintf('[%s] %s', $integration->name, $e->getMessage()));
            }
        }

        return self::SUCCESS;
    }
}
