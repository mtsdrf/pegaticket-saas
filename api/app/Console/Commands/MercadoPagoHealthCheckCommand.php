<?php

namespace App\Console\Commands;

use App\Services\Payment\MercadoPagoPaymentProvider;
use Illuminate\Console\Command;

class MercadoPagoHealthCheckCommand extends Command
{
    protected $signature = 'payments:mercadopago-health-check';

    protected $description = 'Valida conectividade/autenticação do Mercado Pago com uma chamada read-only.';

    public function __construct(
        private MercadoPagoPaymentProvider $provider,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->provider->healthCheck();

        $this->line('Ambiente: ' . ($result['environment'] ?: 'não definido'));
        $this->line('HTTP status: ' . (string) $result['status']);
        $this->line('Conectividade: ' . ($result['reachable'] ? 'ok' : 'falhou'));
        $this->line('Autenticação: ' . ($result['authenticated'] ? 'ok' : 'falhou'));

        if ($result['authenticated']) {
            $this->line('Meios de pagamento retornados: ' . (string) $result['payment_methods_count']);

            return self::SUCCESS;
        }

        $this->error('Mercado Pago não validou a integração.');
        if (!empty($result['error'])) {
            $this->line('Detalhe: ' . (string) $result['error']);
        }

        return self::FAILURE;
    }
}
