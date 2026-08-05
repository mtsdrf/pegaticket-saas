<?php

namespace App\Services\Event;

use App\DTOs\Event\CreateTicketTypeChannelQuotaDTO;
use App\DTOs\Event\UpdateTicketTypeChannelQuotaDTO;
use App\Events\Event\TicketTypeChannelQuotaCreated;
use App\Events\Event\TicketTypeChannelQuotaDeleted;
use App\Events\Event\TicketTypeChannelQuotaUpdated;
use App\Models\Event\TicketType;
use App\Models\Event\TicketTypeChannelQuota;
use App\Models\Sale\SaleItem;
use App\Repositories\Contracts\TicketTypeChannelQuotaRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Cota de inventário por canal (opt-in, roadmap "cotas por canal") — sem
 * nenhuma linha cadastrada para (ticket_type_id, channel), o canal segue
 * limitado só pelo estoque geral (comportamento anterior 100%
 * preservado). Ver availableForChannel(), consumido por
 * StorefrontHoldService (canal storefront/affiliate, na reserva) e
 * SaleService (canal staff/affiliate sem hold, na criação da venda).
 */
class TicketTypeChannelQuotaService
{
    public const EAGER_RELATIONS = ['ticketType'];

    public function __construct(
        private TicketTypeChannelQuotaRepositoryInterface $repository
    ) {}

    public function find(TicketTypeChannelQuota $quota): TicketTypeChannelQuota
    {
        $this->assertBelongsToCurrentTenant($quota);

        return $quota;
    }

    public function paginate(
        int $tenantId,
        string $ticketTypeUuid,
        int $perPage = 15
    ): LengthAwarePaginator {
        $ticketType = $this->resolveTicketType($tenantId, $ticketTypeUuid);

        return TicketTypeChannelQuota::query()
            ->where('ticket_type_channel_quotas.tenant_id', $tenantId)
            ->where('ticket_type_channel_quotas.ticket_type_id', $ticketType->id)
            ->whereNull('ticket_type_channel_quotas.deleted_at')
            ->with(self::EAGER_RELATIONS)
            ->orderBy('ticket_type_channel_quotas.channel')
            ->paginate($perPage);
    }

    public function create(CreateTicketTypeChannelQuotaDTO $dto): TicketTypeChannelQuota
    {
        return DB::transaction(function () use ($dto) {
            $ticketType = $this->resolveTicketType($dto->tenantId, $dto->ticketTypeUuid);

            $quota = $this->repository->create([
                'tenant_id' => $dto->tenantId,
                'ticket_type_id' => $ticketType->id,
                'channel' => $dto->channel,
                'quota' => $dto->quota,
            ]);

            event(new TicketTypeChannelQuotaCreated(
                ticketTypeChannelQuotaUuid: $quota->uuid,
                actorId: Auth::id()
            ));

            return $quota;
        });
    }

    public function update(TicketTypeChannelQuota $quota, UpdateTicketTypeChannelQuotaDTO $dto): TicketTypeChannelQuota
    {
        $this->assertBelongsToCurrentTenant($quota);

        return DB::transaction(function () use ($quota, $dto) {
            $original = $quota->getOriginal();

            $data = array_filter([
                'quota' => $dto->quota,
            ], fn ($v) => ! is_null($v));

            if (! empty($data)) {
                $quota = $this->repository->update($quota, $data);

                $changes = array_diff_assoc($quota->getAttributes(), $original);

                if (! empty($changes)) {
                    event(new TicketTypeChannelQuotaUpdated(
                        ticketTypeChannelQuotaUuid: $quota->uuid,
                        actorId: Auth::id(),
                        changes: array_keys($changes)
                    ));
                }
            }

            return $quota;
        });
    }

    public function delete(TicketTypeChannelQuota $quota): void
    {
        $this->assertBelongsToCurrentTenant($quota);

        DB::transaction(function () use ($quota) {
            $this->repository->delete($quota);

            event(new TicketTypeChannelQuotaDeleted(
                ticketTypeChannelQuotaUuid: $quota->uuid,
                actorId: Auth::id()
            ));
        });
    }

    /**
     * Guard barato usado pelo caller ANTES de calcular o estoque geral
     * (StorefrontHoldService::availableQuantityForTicketType() — consulta
     * com joins). Sem nenhuma cota cadastrada pro par (ticket_type,
     * channel), o caller deve pular o cálculo por completo (não só "não
     * limitar"), pra não introduzir um check de estoque geral que não
     * existia antes desta feature em canais como `staff`
     * (SaleService::create() nunca validou quantity_available).
     */
    public function quotaExists(TicketType $ticketType, string $channel): bool
    {
        return $this->findQuota($ticketType, $channel) !== null;
    }

    /**
     * Disponibilidade final para um canal — $generalStockRemaining é
     * calculado pelo caller (mesma régua já usada hoje: para
     * TicketType, StorefrontHoldService::availableQuantityForTicketType()/
     * resolveTicketTypeAvailability()). Sem cota configurada para o canal,
     * retorna o próprio estoque geral (opt-in). Com cota, aplica
     * min(estoque geral, cota - já vendido no canal) — nenhum canal pode
     * vender além da própria cota nem além do estoque geral.
     */
    public function availableForChannel(TicketType $ticketType, string $channel, int $generalStockRemaining): int
    {
        $quota = $this->findQuota($ticketType, $channel);

        if (! $quota) {
            return max(0, $generalStockRemaining);
        }

        $soldInChannel = $this->soldQuantityForChannel($ticketType->id, $channel);
        $channelRemaining = max(0, $quota->quota - $soldInChannel);

        return max(0, min($generalStockRemaining, $channelRemaining));
    }

    private function findQuota(TicketType $ticketType, string $channel): ?TicketTypeChannelQuota
    {
        return TicketTypeChannelQuota::query()
            ->where('tenant_id', $ticketType->tenant_id)
            ->where('ticket_type_id', $ticketType->id)
            ->where('channel', $channel)
            ->first();
    }

    private function soldQuantityForChannel(int $ticketTypeId, string $channel): int
    {
        return (int) SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sale_items.ticket_type_id', $ticketTypeId)
            ->where('sales.channel', $channel)
            ->whereNull('sale_items.deleted_at')
            ->whereNull('sales.deleted_at')
            ->whereNull('sales.cancelled_at')
            ->where('sales.status', '!=', 'rejected')
            ->sum('sale_items.quantity');
    }

    private function resolveTicketType(int $tenantId, string $ticketTypeUuid): TicketType
    {
        return TicketType::where('uuid', $ticketTypeUuid)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->firstOrFail();
    }

    private function assertBelongsToCurrentTenant(TicketTypeChannelQuota $quota): void
    {
        if ((int) $quota->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }
}
