<?php

namespace App\Console\Commands;

use App\Enums\Subscription\SubscriptionStatus;
use App\Models\Subscription\Subscription;
use App\Services\Subscription\SubscriptionStateMachine;
use Illuminate\Console\Command;

/**
 * Expira trials vencidos (roadmap 1B). Job diário: assinatura em trialing
 * com trial_ends_at vencido e SEM pagamento associado transiciona para
 * past_due (nunca apaga dado, só marca). Depois disso o
 * GenerateSubscriptionInvoicesCommand passa a cobrá-la.
 */
class ProcessTrialExpirationCommand extends Command
{
    protected $signature = 'subscriptions:process-trial';

    protected $description = 'Move para past_due os trials vencidos sem pagamento.';

    public function __construct(
        private SubscriptionStateMachine $stateMachine
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $expired = Subscription::query()
            ->whereNull('deleted_at')
            ->where('status', SubscriptionStatus::Trialing->value)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->get();

        $moved = 0;

        foreach ($expired as $subscription) {
            $hasPayment = $subscription->invoices()
                ->whereHas('payments', fn ($q) => $q->where('status', 'paid'))
                ->exists();

            if ($hasPayment) {
                continue;
            }

            $this->stateMachine->transition(
                $subscription,
                SubscriptionStatus::PastDue,
                ['reason' => 'trial_expired']
            );

            $moved++;
        }

        $this->info("Trials expirados movidos para past_due: {$moved}.");

        return self::SUCCESS;
    }
}
