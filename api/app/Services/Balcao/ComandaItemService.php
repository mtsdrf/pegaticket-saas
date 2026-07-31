<?php

namespace App\Services\Balcao;

use App\DTOs\Balcao\UpdatePrepStatusDTO;
use App\Events\Balcao\ComandaItemCancelled;
use App\Events\Balcao\ComandaItemPrepStatusUpdated;
use App\Events\Balcao\ComandaItemSentToStation;
use App\Exceptions\Balcao\ComandaException;
use App\Models\Balcao\Comanda;
use App\Models\Balcao\ComandaItem;
use App\Models\Product\Product;
use App\Models\Stock\StockLocation;
use App\Services\Stock\StockService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Máquina de estados de preparo do item de comanda (roadmap Balcão, Fases
 * 1+2). Transições permitidas:
 *
 *   queued ──(sendToStation)──▶ sent_to_station ──▶ preparing ──▶ ready
 *                                                              ──▶ delivered_to_table
 *   e cancelled a partir de QUALQUER estado não-terminal (com motivo).
 *
 * A entrada em `sent_to_station` é a única transição que mexe em estoque
 * (baixa real, decisão #1) e por isso tem método próprio (sendToStation);
 * updatePrepStatus() cuida das demais transições puras + cancelamento.
 */
class ComandaItemService
{
    /**
     * Transições válidas de updatePrepStatus() (sem contar 'cancelled', que é
     * permitido de qualquer estado não-terminal, e sem 'sent_to_station', que
     * passa por sendToStation()).
     */
    private const ALLOWED = [
        ComandaItem::STATUS_SENT_TO_STATION => [ComandaItem::STATUS_PREPARING],
        ComandaItem::STATUS_PREPARING => [ComandaItem::STATUS_READY],
        ComandaItem::STATUS_READY => [ComandaItem::STATUS_DELIVERED_TO_TABLE],
    ];

    public function __construct(
        private StockService $stockService
    ) {
    }

    /**
     * queued → sent_to_station + baixa real de estoque vinculada à origem
     * polimórfica do item (decisão #1/#2). Idempotente contra reenvio: item
     * que já saiu da fila `queued` é rejeitado (não baixa estoque duas vezes).
     */
    public function sendToStation(int $tenantId, string $comandaUuid, string $itemUuid): ComandaItem
    {
        return DB::transaction(function () use ($tenantId, $comandaUuid, $itemUuid) {
            [$comanda, $item] = $this->resolveOwnedItem($tenantId, $comandaUuid, $itemUuid, lock: true);

            if ($item->prep_status !== ComandaItem::STATUS_QUEUED) {
                throw new ComandaException(__('messages.comanda.item_already_sent'));
            }

            $product = Product::whereKey($item->product_id)->firstOrFail();
            $location = $this->resolveDefaultStockLocation($tenantId);

            $this->stockService->exit(
                product: $product,
                location: $location,
                quantity: (float) $item->qty,
                reason: "Envio à estação (comanda {$comanda->uuid})",
                sourceType: ComandaItem::class,
                sourceId: $item->id,
            );

            $item->prep_status = ComandaItem::STATUS_SENT_TO_STATION;
            $item->sent_to_station_at = now();
            $item->save();

            event(new ComandaItemSentToStation(
                itemId: $item->id,
                comandaUuid: $comanda->uuid,
                itemUuid: $item->uuid,
                stationUuid: $item->station?->uuid,
                actorId: (int) Auth::id()
            ));

            return $item->load(['product', 'station']);
        });
    }

    public function updatePrepStatus(int $tenantId, string $comandaUuid, string $itemUuid, UpdatePrepStatusDTO $dto): ComandaItem
    {
        return DB::transaction(function () use ($tenantId, $comandaUuid, $itemUuid, $dto) {
            [$comanda, $item] = $this->resolveOwnedItem($tenantId, $comandaUuid, $itemUuid, lock: true);

            $from = $item->prep_status;

            if (in_array($from, ComandaItem::TERMINAL_STATUSES, true)) {
                throw new ComandaException(__('messages.comanda.item_terminal_state'));
            }

            if ($dto->prepStatus === ComandaItem::STATUS_CANCELLED) {
                return $this->cancelItem($comanda, $item, $dto->cancelledReason, $tenantId);
            }

            $allowed = self::ALLOWED[$from] ?? [];

            if (!in_array($dto->prepStatus, $allowed, true)) {
                throw new ComandaException(__('messages.comanda.invalid_prep_transition'));
            }

            $item->prep_status = $dto->prepStatus;
            $item->{$this->timestampColumnFor($dto->prepStatus)} = now();
            $item->save();

            event(new ComandaItemPrepStatusUpdated(
                itemId: $item->id,
                comandaUuid: $comanda->uuid,
                itemUuid: $item->uuid,
                fromStatus: $from,
                toStatus: $dto->prepStatus,
                actorId: (int) Auth::id()
            ));

            return $item->load(['product', 'station']);
        });
    }

    /**
     * Cancelamento com motivo obrigatório e auditado. Se o item já tinha
     * baixado estoque (passou por sent_to_station), devolve o estoque — não
     * deixar inventário vazar por item cancelado depois de enviado.
     */
    private function cancelItem(Comanda $comanda, ComandaItem $item, ?string $reason, int $tenantId): ComandaItem
    {
        if ($reason === null || trim($reason) === '') {
            throw new ComandaException(__('messages.comanda.cancel_reason_required'));
        }

        $from = $item->prep_status;

        if ($item->sent_to_station_at !== null) {
            $product = Product::whereKey($item->product_id)->firstOrFail();
            $location = $this->resolveDefaultStockLocation($tenantId);

            $this->stockService->returnStock(
                product: $product,
                location: $location,
                quantity: (float) $item->qty,
                reason: "Cancelamento de item enviado (comanda {$comanda->uuid})",
            );
        }

        $item->prep_status = ComandaItem::STATUS_CANCELLED;
        $item->cancelled_at = now();
        $item->cancelled_reason = $reason;
        $item->save();

        event(new ComandaItemCancelled(
            itemId: $item->id,
            comandaUuid: $comanda->uuid,
            itemUuid: $item->uuid,
            fromStatus: $from,
            reason: $reason,
            actorId: (int) Auth::id()
        ));

        return $item->load(['product', 'station']);
    }

    private function timestampColumnFor(string $status): string
    {
        return match ($status) {
            ComandaItem::STATUS_PREPARING => 'preparing_at',
            ComandaItem::STATUS_READY => 'ready_at',
            ComandaItem::STATUS_DELIVERED_TO_TABLE => 'delivered_at',
            default => 'updated_at',
        };
    }

    /**
     * Resolve comanda (por uuid + tenant) e item (por uuid + comanda), com
     * lock opcional na linha do item. 404 se a comanda não é do tenant ou o
     * item não pertence à comanda — route-model-binding não é usado aqui
     * (uuids crus na URL), mesmo cuidado de IDOR do OrderService.
     */
    private function resolveOwnedItem(int $tenantId, string $comandaUuid, string $itemUuid, bool $lock = false): array
    {
        $comanda = Comanda::where('uuid', $comandaUuid)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->first();

        if ($comanda === null) {
            abort(404);
        }

        $query = ComandaItem::where('uuid', $itemUuid)
            ->where('comanda_id', $comanda->id)
            ->whereNull('deleted_at');

        if ($lock) {
            $query->lockForUpdate();
        }

        $item = $query->first();

        if ($item === null) {
            abort(404);
        }

        return [$comanda, $item];
    }

    private function resolveDefaultStockLocation(int $tenantId): StockLocation
    {
        $default = StockLocation::where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->whereNull('deleted_at')
            ->first();

        if ($default === null) {
            throw new ComandaException(__('messages.comanda.no_default_stock_location'));
        }

        return $default;
    }
}
