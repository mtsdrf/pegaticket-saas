<?php

namespace App\Jobs\Payment;

use App\Exceptions\Payment\PagBankAccountException;
use App\Models\Tenant\TenantPagBankConnection;
use App\Services\Logging\ApplicationLogger;
use App\Services\Payment\PagBankAccountService;

/**
 * Sincronização periódica de uma conexão PagBank (roadmap R2.5, seção
 * 9.5.5) — reconsulta GET /accounts/{id} via
 * PagBankAccountService::syncStatus() para mover conexões em estado
 * "aguardando confirmação externa" (ver
 * TenantPagBankConnection::needsPeriodicStatusSync()) adiante, sem
 * depender só do callback OAuth/criação de conta.
 *
 * Deliberadamente NÃO implementa `ShouldQueue`: o projeto não tem worker
 * de fila persistente rodando em produção (gap operacional documentado
 * em `.claude/memory/api-patterns.md`, entrada de Geocodificação de
 * endereços, 2026-07-14) — despachar isto para a fila só acumularia na
 * tabela `jobs` sem nunca ser processado. Mesmo padrão de todo comando de
 * reconciliação já existente (ex. ReconcilePagBankSalePaymentsCommand):
 * uma classe de unidade de trabalho, chamada de forma síncrona dentro de
 * um laço em `SyncPagBankReceivingAccountsCommand`, cada iteração isolada
 * por try/catch para uma falha individual não derrubar o lote inteiro.
 */
class SyncPagBankTenantAccountJob
{
    public function __construct(
        private PagBankAccountService $accountService
    ) {}

    /**
     * Sincroniza uma conexão. Silenciosamente ignora conexões cujo status
     * não faz sentido sincronizar (idempotente/seguro chamar em lote sem
     * filtrar antes) — só loga e segue em caso de falha, nunca propaga a
     * exceção para não derrubar o restante do lote no chamador.
     */
    public function handle(TenantPagBankConnection $connection): bool
    {
        if (! $connection->needsPeriodicStatusSync()) {
            return false;
        }

        try {
            $this->accountService->syncStatus($connection);

            return true;
        } catch (PagBankAccountException|\Throwable $e) {
            ApplicationLogger::error('Falha na sincronização periódica de conta PagBank', [
                'tenant_id' => $connection->tenant_id,
                'connection_uuid' => $connection->uuid,
                'account_id' => $connection->account_id,
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
