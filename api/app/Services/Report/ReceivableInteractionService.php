<?php

namespace App\Services\Report;

use App\DTOs\Report\CreateReceivableInteractionDTO;
use App\Events\Report\ReceivableInteractionCreated;
use App\Models\Order\Order;
use App\Models\Order\OrderInstallment;
use App\Models\Report\ReceivableInteraction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReceivableInteractionService
{
    public function list(int $tenantId, Order $order, ?string $installmentUuid = null): Collection
    {
        $this->assertOrderBelongsToTenant($order, $tenantId);
        $installment = $installmentUuid ? $this->resolveInstallment($tenantId, $order, $installmentUuid) : null;

        return ReceivableInteraction::where('tenant_id', $tenantId)
            ->where('order_id', $order->id)
            ->when($installment, fn($q) => $q->where('order_installment_id', $installment->id))
            ->when(!$installment, fn($q) => $q->whereNull('order_installment_id'))
            ->with('createdByUser', 'installment')
            ->orderByDesc('contacted_at')
            ->orderByDesc('id')
            ->get();
    }

    public function create(int $tenantId, Order $order, CreateReceivableInteractionDTO $dto): ReceivableInteraction
    {
        $this->assertOrderBelongsToTenant($order, $tenantId);
        $installment = $dto->installmentUuid ? $this->resolveInstallment($tenantId, $order, $dto->installmentUuid) : null;

        return DB::transaction(function () use ($tenantId, $order, $dto, $installment) {
            $interaction = ReceivableInteraction::create([
                'tenant_id' => $tenantId,
                'order_id' => $order->id,
                'order_installment_id' => $installment?->id,
                'interaction_type' => $dto->interactionType,
                'channel' => $dto->channel ?? ($dto->interactionType === 'whatsapp' ? 'whatsapp' : 'manual'),
                'notes' => $dto->notes,
                'promised_amount' => $dto->promisedAmount,
                'promised_due_date' => $dto->promisedDueDate,
                'contacted_at' => $dto->contactedAt ?? now(),
            ]);

            event(new ReceivableInteractionCreated(
                interactionUuid: $interaction->uuid,
                orderUuid: $order->uuid,
                installmentUuid: $installment?->uuid,
                interactionType: $interaction->interaction_type,
                actorId: (int) Auth::id(),
            ));

            return $interaction->load('createdByUser', 'installment');
        });
    }

    private function assertOrderBelongsToTenant(Order $order, int $tenantId): void
    {
        if ((int) $order->tenant_id !== $tenantId) {
            abort(404);
        }
    }

    private function resolveInstallment(int $tenantId, Order $order, string $installmentUuid): OrderInstallment
    {
        $installment = OrderInstallment::where('tenant_id', $tenantId)
            ->where('order_id', $order->id)
            ->where('uuid', $installmentUuid)
            ->firstOrFail();

        return $installment;
    }
}
