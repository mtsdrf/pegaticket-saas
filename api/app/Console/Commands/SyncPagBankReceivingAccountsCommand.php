<?php

namespace App\Console\Commands;

use App\Jobs\Payment\SyncPagBankTenantAccountJob;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantPagBankConnection;
use Illuminate\Console\Command;

/**
 * Reconciliação ativa dos estados "aguardando confirmação externa" do
 * caminho Account/Cadastro do PagBank (roadmap R2.5, seção 9.5.5) — rede
 * de segurança equivalente aos outros comandos `*:reconcile-*`/`*:sync-*`
 * já existentes (ex. ReconcilePagBankSalePaymentsCommand), para o caso do
 * tenant nunca voltar a consultar o status manualmente e a aprovação de
 * KYC no PagBank não ter nenhum callback ativo que avise a plataforma.
 *
 * Frequência recomendada no Scheduler: 30 minutos — status de KYC não
 * muda com urgência de segundos/minutos, e evitar polling agressivo é
 * requisito explícito do roadmap (seção 9.5.5).
 */
class SyncPagBankReceivingAccountsCommand extends Command
{
    protected $signature = 'pagbank:sync-receiving-accounts {--limit=100} {--tenant_id=}';

    protected $description = 'Sincroniza com o PagBank o status de conexões de recebimento aguardando confirmação externa (submitted/pending_kyc/under_review).';

    public function __construct(
        private SyncPagBankTenantAccountJob $job,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $tenantUuid = $this->option('tenant_id');

        $query = TenantPagBankConnection::query()
            ->whereNull('deleted_at')
            ->whereIn('status', [
                TenantPagBankConnection::STATUS_SUBMITTED,
                TenantPagBankConnection::STATUS_PENDING_KYC,
                TenantPagBankConnection::STATUS_UNDER_REVIEW,
            ])
            ->orderBy('id');

        if (is_string($tenantUuid) && $tenantUuid !== '') {
            $tenant = Tenant::where('uuid', $tenantUuid)->first();
            $query->where('tenant_id', $tenant?->id ?? 0);
        } else {
            $query->limit($limit);
        }

        $connections = $query->get();

        $synced = 0;
        $failed = 0;

        foreach ($connections as $connection) {
            if ($this->job->handle($connection)) {
                $synced++;
            } else {
                $failed++;
            }
        }

        $this->info("Conexões verificadas: {$connections->count()}.");
        $this->info("Sincronizações concluídas: {$synced}.");
        $this->info("Falhas: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
