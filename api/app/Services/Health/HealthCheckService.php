<?php

namespace App\Services\Health;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Checagem de saúde pra monitoramento externo (roadmap A1.1 — base pra
 * UptimeRobot/Sentry numa fase futura, ainda não configurada). Puramente
 * leitura/diagnóstico, sem persistir nada de domínio — por isso não segue
 * o fluxo Controller->Service->Repository->Resource completo (não há
 * Repository nem Resource envolvidos, só 3 checagens de infraestrutura).
 * Cada check é isolado (try/catch próprio) para uma falha não mascarar as
 * outras — o objetivo é sempre reportar o detalhe de QUAL check falhou.
 */
class HealthCheckService
{
    /**
     * @return array{healthy: bool, checks: array<string, array{status: string, message?: string}>}
     */
    public function run(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'storage' => $this->checkStorage(),
            'queue' => $this->checkQueue(),
            'payments' => $this->checkPayments(),
        ];

        $healthy = collect($checks)->every(fn (array $check) => $check['status'] === 'ok');

        return [
            'healthy' => $healthy,
            'checks' => $checks,
        ];
    }

    /**
     * @return array{status: string, message?: string}
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return ['status' => 'ok'];
        } catch (Throwable $e) {
            return ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * Grava e remove um arquivo de teste no disco 'public' (mesmo disco
     * usado por upload de imagem de produto) — confirma que o disco está
     * montado e gravável, não só configurado.
     */
    private function checkStorage(): array
    {
        $path = 'health-check/' . Str::random(20) . '.txt';

        try {
            Storage::disk('public')->put($path, 'ok');
            $written = Storage::disk('public')->exists($path);
            Storage::disk('public')->delete($path);

            if (!$written) {
                return ['status' => 'failed', 'message' => 'Arquivo de teste não foi persistido no disco public.'];
            }

            return ['status' => 'ok'];
        } catch (Throwable $e) {
            return ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * Não executa um job de verdade (isso exigiria um worker rodando, o
     * que já é um gap operacional conhecido — ver architecture-decisions.md
     * sobre queue worker permanente). Confirma só que as tabelas
     * jobs/failed_jobs existem e são consultáveis, base mínima pra saber
     * se a infraestrutura de fila está migrada e o banco responde por ela.
     */
    private function checkQueue(): array
    {
        try {
            if (!Schema::hasTable('jobs') || !Schema::hasTable('failed_jobs')) {
                return ['status' => 'failed', 'message' => 'Tabelas jobs/failed_jobs ausentes.'];
            }

            DB::table('jobs')->count();
            DB::table('failed_jobs')->count();

            return ['status' => 'ok'];
        } catch (Throwable $e) {
            return ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * Validação operacional mínima do provedor de pagamentos configurado.
     * Não toca na API externa nem revela segredos; confirma só se o app
     * está pronto para iniciar o fluxo real sem cair em 500 por env ausente.
     */
    private function checkPayments(): array
    {
        try {
            $provider = (string) config('services.payments.provider', 'manual');

            if ($provider !== 'mercadopago') {
                return ['status' => 'ok', 'message' => 'Provedor manual ativo.'];
            }

            $environment = (string) config('services.mercadopago.environment', '');
            $accessToken = (string) config('services.mercadopago.access_token', '');
            $publicKey = (string) config('services.mercadopago.public_key', '');
            $webhookSecret = (string) config('services.mercadopago.webhook_secret', '');

            if (! in_array($environment, ['test', 'production'], true)) {
                return ['status' => 'failed', 'message' => 'Ambiente do Mercado Pago inválido. Use test ou production.'];
            }

            if ($accessToken === '' || $publicKey === '' || $webhookSecret === '') {
                return ['status' => 'failed', 'message' => 'Credenciais do Mercado Pago incompletas.'];
            }

            return ['status' => 'ok', 'message' => 'Mercado Pago configurado para ' . $environment . '.'];
        } catch (Throwable $e) {
            return ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }
}
