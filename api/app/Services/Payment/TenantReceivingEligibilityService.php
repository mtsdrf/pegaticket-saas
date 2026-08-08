<?php

namespace App\Services\Payment;

use App\Models\Tenant\TenantPagBankConnection;
use App\Repositories\Contracts\TenantPagBankConnectionRepositoryInterface;

/**
 * Fonte única de verdade sobre elegibilidade financeira do tenant
 * (roadmap fase R2.3, seção 9.5.3). Antes desta classe, a checagem "o
 * tenant pode vender pago?" estava inline em EventService::publish()
 * (hasEnabledPagBankConnection()) e resolveSplitSettings() olhava só
 * tenant_settings, sem nenhum dos dois consultar a mesma fonte. Todo
 * consumidor novo (checkout, Order, Split, gate de TicketType) deve
 * consultar este service — nunca reimplementar `$status === 'enabled'`
 * em outro Controller/Service.
 *
 * Decisão de modelagem (documentada aqui por exigência do roadmap, não
 * deixar implícita): quais status habilitam recebimento real.
 * - STATUS_ENABLED: caminho Connect (conta PagBank existente, já habilitada
 *   pelo PagBank para receber split) — habilita.
 * - STATUS_VERIFIED: caminho Account/Cadastro (R2.2), KYC aprovado pelo
 *   PagBank (`provider_status=ACTIVE`). Pela máquina de estados da seção
 *   9.4, `verified` é o estado terminal de sucesso desse caminho — não
 *   existe transição documentada de `verified` para `enabled` (R2.2 não
 *   criou esse próximo passo, e a especificação não pede um "ativar
 *   recebimento" adicional após a verificação). Tratar `verified` como
 *   NÃO habilitado deixaria todo tenant que passa pelo caminho
 *   Account/Cadastro permanentemente incapaz de vender pago, o que
 *   contradiz o objetivo do próprio caminho — por isso `verified` conta
 *   como elegível aqui, no mesmo pé que `enabled`. Se uma sub-fase futura
 *   introduzir um passo explícito de ativação pós-KYC, esta decisão deve
 *   ser revisada (só neste service).
 * - Todos os demais status (not_configured, pending_connection,
 *   pending_kyc, under_review, restricted, disabled, started,
 *   pending_submission, submitted, rejected, error) NÃO habilitam.
 */
class TenantReceivingEligibilityService
{
    private const ELIGIBLE_STATUSES = [
        TenantPagBankConnection::STATUS_ENABLED,
        TenantPagBankConnection::STATUS_VERIFIED,
    ];

    private const NO_SETUP_STATUSES = [
        TenantPagBankConnection::STATUS_NOT_CONFIGURED,
        TenantPagBankConnection::STATUS_DISABLED,
    ];

    private const RESTRICTED_STATUSES = [
        TenantPagBankConnection::STATUS_RESTRICTED,
        TenantPagBankConnection::STATUS_REJECTED,
    ];

    public function __construct(
        private TenantPagBankConnectionRepositoryInterface $repository,
    ) {}

    /**
     * True somente quando o tenant tem uma TenantPagBankConnection em
     * `enabled` (Connect) ou `verified` (Account/Cadastro) — ver decisão
     * documentada no docblock da classe.
     */
    public function canReceivePayments(int $tenantId): bool
    {
        $connection = $this->repository->findForTenant($tenantId);

        return $connection !== null && in_array($connection->status, self::ELIGIBLE_STATUSES, true);
    }

    /**
     * Hoje equivalente a canReceivePayments() — mantido como método
     * separado de propósito (nomes de domínio distintos: "pode receber
     * dinheiro" vs "pode publicar/vender evento pago") para permitir
     * evoluir uma das duas regras no futuro (ex.: exigir mais que só a
     * conexão habilitada para liberar publicação, ou vice-versa) sem
     * quebrar os chamadores que já dependem de uma ou de outra.
     */
    public function canPublishPaidEvents(int $tenantId): bool
    {
        return $this->canReceivePayments($tenantId);
    }

    /**
     * True quando o tenant ainda não iniciou nenhum onboarding financeiro
     * (nenhuma conexão) ou desistiu/foi desabilitado explicitamente.
     */
    public function needsReceivingSetup(int $tenantId): bool
    {
        $connection = $this->repository->findForTenant($tenantId);

        return $connection === null || in_array($connection->status, self::NO_SETUP_STATUSES, true);
    }

    /**
     * True quando o tenant tem uma conexão com restrição financeira ativa
     * imposta pelo PagBank (Connect restringido ou Account/Cadastro
     * rejeitado no KYC) — distinto de "ainda não configurou nada".
     */
    public function hasFinancialRestriction(int $tenantId): bool
    {
        $connection = $this->repository->findForTenant($tenantId);

        return $connection !== null && in_array($connection->status, self::RESTRICTED_STATUSES, true);
    }
}
