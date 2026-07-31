<?php

namespace App\Services\Support;

use App\DTOs\Support\CreateSupportTicketDTO;
use App\Events\Support\SupportTicketCreated;
use App\Models\AuditLog;
use App\Models\Support\SupportTicket;
use App\Models\Subscription\Subscription;
use App\Models\Tenant\TenantUser;
use App\Repositories\Contracts\SupportTicketRepositoryInterface;
use App\Services\Health\HealthCheckService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Central de chamados nativa (roadmap A4, item 17). Reaproveita o padrão de
 * dado já usado no módulo do contador (`AccountingRequestMessage` — anexo
 * opcional via Storage::disk('public'), status simples). O botão
 * "diagnóstico" preenche `diagnostics` automaticamente na criação quando
 * `include_diagnostics=true`.
 */
class SupportTicketService
{
    private const DIAGNOSTICS_AUDIT_LIMIT = 20;

    public function __construct(
        private SupportTicketRepositoryInterface $repository,
        private HealthCheckService $healthCheckService,
    ) {
    }

    public function listForTenant(int $tenantId): Collection
    {
        return $this->repository->listForTenant($tenantId);
    }

    public function create(int $tenantId, CreateSupportTicketDTO $dto, ?UploadedFile $attachment): SupportTicket
    {
        return DB::transaction(function () use ($tenantId, $dto, $attachment) {
            $attachmentPath = null;
            $attachmentName = null;

            if ($attachment) {
                $attachmentPath = $attachment->store('support-tickets', 'public');
                $attachmentName = $attachment->getClientOriginalName();
            }

            $ticket = $this->repository->create([
                'tenant_id' => $tenantId,
                'subject' => $dto->subject,
                'description' => $dto->description,
                'status' => SupportTicket::STATUS_OPEN,
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentName,
                'diagnostics' => $dto->includeDiagnostics ? $this->buildDiagnostics($tenantId) : null,
            ]);

            event(new SupportTicketCreated(
                ticketUuid: $ticket->uuid,
                tenantId: $tenantId,
                actorId: (int) Auth::id()
            ));

            return $ticket;
        });
    }

    /**
     * Snapshot anexado pelo botão "diagnóstico": plano atual, últimas N
     * linhas de auditoria do tenant, status de fila e versão da aplicação.
     *
     * `audit_logs` NÃO tem `tenant_id` (tabela técnica/global) — o recorte
     * "do tenant" é derivado por aproximação: eventos cujo `user_id` é (ou
     * já foi) um staff vinculado a este tenant via `tenant_users`. Isso é
     * best-effort, não um filtro exato: pode incluir uma ação de um staff
     * que hoje pertence a outro tenant também, e nunca inclui eventos sem
     * ator (`user_id` null, ex.: falha de login/webhook). Aceitável para um
     * anexo de diagnóstico de suporte, não para relatório de auditoria
     * formal (esse já existe em `/audit-logs`, escopado de outra forma).
     */
    public function buildDiagnostics(int $tenantId): array
    {
        $planName = Subscription::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->with('plan')
            ->latest('id')
            ->first()?->plan?->name;

        $tenantUserIds = TenantUser::where('tenant_id', $tenantId)
            ->pluck('user_id');

        $recentAuditLogs = AuditLog::whereIn('user_id', $tenantUserIds)
            ->orderByDesc('id')
            ->limit(self::DIAGNOSTICS_AUDIT_LIMIT)
            ->get(['event', 'route', 'created_at'])
            ->map(fn (AuditLog $log) => [
                'event' => $log->event,
                'route' => $log->route,
                'created_at' => optional($log->created_at)->toIso8601String(),
            ])
            ->all();

        return [
            'plan_name' => $planName,
            'app_version' => (string) config('app.version'),
            'queue_health' => $this->healthCheckService->run()['checks']['queue'] ?? null,
            'recent_audit_logs' => $recentAuditLogs,
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
