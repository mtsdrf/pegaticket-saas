<?php

namespace App\Console\Commands;

use App\Repositories\Contracts\SubscriptionRepositoryInterface;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Console\Command;

/**
 * Suspende assinaturas cuja tolerância de 7 dias após falha de cobrança do
 * Mercado Pago (Preapproval) já venceu sem regularização (roadmap Fase B,
 * item 1 — assinatura). Job diário, mesmo padrão de
 * GenerateSubscriptionInvoicesCommand: registrado em routes/console.php,
 * depende de `schedule:run` no cron do servidor.
 */
class EnforceSubscriptionGracePeriodCommand extends Command
{
    protected $signature = 'subscriptions:enforce-grace-period';

    protected $description = 'Suspende assinaturas com período de graça vencido sem pagamento confirmado.';

    public function __construct(
        private SubscriptionRepositoryInterface $repository,
        private SubscriptionService $service
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $subscriptions = $this->repository->pastGracePeriod();
        $suspended = 0;

        foreach ($subscriptions as $subscription) {
            $this->service->suspendExpiredGracePeriod($subscription);
            $suspended++;
        }

        $this->info("Assinaturas suspensas: {$suspended}.");

        return self::SUCCESS;
    }
}
