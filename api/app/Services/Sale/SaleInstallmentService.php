<?php

namespace App\Services\Sale;

use App\DTOs\Sale\CreateSaleInstallmentDTO;
use App\DTOs\Sale\UpdateSaleInstallmentDTO;
use App\Events\Sale\SaleInstallmentCreated;
use App\Events\Sale\SaleInstallmentDeleted;
use App\Events\Sale\SaleInstallmentUpdated;
use App\Exceptions\InvalidSaleStateException;
use App\Models\BaseModel;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleInstallment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Gestão manual de parcela — lacuna do legado (`Venda/Parcela`, CRUD
 * livre, inclusive de parcela já paga, sem validar se a soma bate com o
 * total da venda) replicada de forma corrigida: mesmas 3 operações
 * (criar/editar/excluir), mas parcela paga é imutável e toda operação
 * precisa manter a soma de todas as parcelas exatamente igual a
 * `sales.total_amount` — é a correção do bug de integridade do legado,
 * decisão consciente do usuário (não é suposição). Ver
 * `.claude/memory/architecture-decisions.md`.
 *
 * Sem Repository próprio (mesmo padrão de SaleItem/SaleInstallment já
 * documentado no Model) — mutação sempre passa pelo Sale travado, nunca
 * é uma rota RESTful de primeiro nível.
 */
class SaleInstallmentService
{
    public function create(Sale $order, CreateSaleInstallmentDTO $dto): SaleInstallment
    {
        $this->assertBelongsToCurrentTenant($order);

        return DB::transaction(function () use ($order, $dto) {
            $order = $this->lockOrder($order);

            $this->assertMutable($order);
            $this->assertInstallmentNumberAvailable($order, $dto->installmentNumber);

            $amountCents = (int) round($dto->amount * 100);

            $installment = SaleInstallment::create([
                'tenant_id' => $order->tenant_id,
                'sale_id' => $order->id,
                'installment_number' => $dto->installmentNumber,
                'amount' => $this->centsToDecimal($amountCents),
                'due_date' => $dto->dueDate,
                'is_paid' => false,
            ]);

            $this->assertSumMatchesTotal($order);

            event(new SaleInstallmentCreated(
                saleUuid: $order->uuid,
                installmentUuid: $installment->uuid,
                actorId: Auth::id()
            ));

            return $installment;
        });
    }

    public function update(Sale $order, SaleInstallment $installment, UpdateSaleInstallmentDTO $dto): SaleInstallment
    {
        $this->assertBelongsToCurrentTenant($order);
        $this->assertBelongsToCurrentTenant($installment);
        $this->assertInstallmentBelongsToOrder($order, $installment);

        return DB::transaction(function () use ($order, $installment, $dto) {
            $order = $this->lockOrder($order);
            $installment = SaleInstallment::whereKey($installment->id)->lockForUpdate()->firstOrFail();

            $this->assertMutable($order);
            $this->assertInstallmentIsEditable($installment);
            $this->assertInstallmentNumberAvailable($order, $dto->installmentNumber, excludeInstallmentId: $installment->id);

            $original = $installment->getOriginal();

            $amountCents = (int) round($dto->amount * 100);

            $installment->installment_number = $dto->installmentNumber;
            $installment->amount = $this->centsToDecimal($amountCents);
            $installment->due_date = $dto->dueDate;
            $installment->save();

            $this->assertSumMatchesTotal($order);

            $changes = array_diff_assoc($installment->getAttributes(), $original);

            event(new SaleInstallmentUpdated(
                saleUuid: $order->uuid,
                installmentUuid: $installment->uuid,
                actorId: Auth::id(),
                changes: array_keys($changes)
            ));

            return $installment->fresh();
        });
    }

    public function delete(Sale $order, SaleInstallment $installment): void
    {
        $this->assertBelongsToCurrentTenant($order);
        $this->assertBelongsToCurrentTenant($installment);
        $this->assertInstallmentBelongsToOrder($order, $installment);

        DB::transaction(function () use ($order, $installment) {
            $order = $this->lockOrder($order);
            $installment = SaleInstallment::whereKey($installment->id)->lockForUpdate()->firstOrFail();

            $this->assertMutable($order);
            $this->assertInstallmentIsEditable($installment);

            $installmentUuid = $installment->uuid;

            $this->releaseNumberAndDelete($installment);

            $this->assertSumMatchesTotal($order);

            event(new SaleInstallmentDeleted(
                saleUuid: $order->uuid,
                installmentUuid: $installmentUuid,
                actorId: Auth::id()
            ));
        });
    }

    /**
     * Substituição em lote das parcelas NÃO PAGAS da venda — resolve a
     * limitação matemática dos 3 métodos acima (soma validada a cada
     * chamada isolada torna redistribuição entre parcelas impossível sem
     * um 422 intermediário, achado registrado em
     * architecture-decisions.md). Aqui a soma só é validada UMA VEZ, no
     * final da operação inteira: os itens podem estar temporariamente
     * "desbalanceados" entre si durante o processamento, só o resultado
     * final precisa bater com `sales.total_amount`.
     *
     * @param array<int, array{uuid?: string, installment_number: int, amount: float, due_date: string}> $items
     * @return \Illuminate\Support\Collection<int, SaleInstallment> Todas as parcelas ativas da venda após a operação (pagas + as recém-processadas), ordenadas por installment_number.
     */
    public function reallocate(Sale $order, array $items): \Illuminate\Support\Collection
    {
        $this->assertBelongsToCurrentTenant($order);

        return DB::transaction(function () use ($order, $items) {
            $order = $this->lockOrder($order);

            $this->assertMutable($order);

            // Trava TODAS as parcelas da venda (pagas + não pagas) —
            // serializa reallocate() concorrente com payInstallment()/os
            // 3 endpoints individuais sobre o mesma venda.
            $existing = SaleInstallment::where('sale_id', $order->id)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->get()
                ->keyBy('uuid');

            $paidExisting = $existing->filter(fn(SaleInstallment $i) => $i->is_paid);
            $unpaidExisting = $existing->filter(fn(SaleInstallment $i) => !$i->is_paid);
            $paidUuids = $paidExisting->keys()->all();
            $paidNumbers = $paidExisting->pluck('installment_number')->all();

            // Validação do payload inteiro ANTES de mutar qualquer coisa —
            // se algo no array é inválido, nada é tocado.
            $seenUuids = [];
            $seenNumbers = $paidNumbers;

            foreach ($items as $item) {
                $uuid = $item['uuid'] ?? null;

                if ($uuid !== null) {
                    if (in_array($uuid, $seenUuids, true)) {
                        throw new InvalidSaleStateException(__('messages.sale.installment_duplicate_reference'));
                    }
                    $seenUuids[] = $uuid;

                    if (in_array($uuid, $paidUuids, true)) {
                        throw new InvalidSaleStateException(__('messages.sale.installment_immutable_when_paid'));
                    }

                    if (!$unpaidExisting->has($uuid)) {
                        throw new InvalidSaleStateException(__('messages.sale.installment_not_found_in_order'));
                    }
                }

                if (in_array($item['installment_number'], $seenNumbers, true)) {
                    throw new InvalidSaleStateException(__('messages.sale.installment_number_duplicate'));
                }
                $seenNumbers[] = $item['installment_number'];
            }

            // $seenUuids (calculado na validação acima) já é exatamente o
            // conjunto de parcelas existentes referenciadas no payload.
            $touchedUuids = $seenUuids;

            // Fase 0 — libera TODOS os números atualmente em uso pelas
            // parcelas não pagas existentes (move pra um sentinela único
            // fora de qualquer faixa real, `1_000_000 + id`; `id` é PK
            // globalmente única, então o sentinela nunca colide). Duas
            // razões pra isso ser necessário, não só um reordenamento
            // simples de delete/update/create:
            // (a) a constraint única (sale_id, installment_number) NÃO
            //     filtra deleted_at — soft delete não libera o número no
            //     banco, então excluir a parcela #2 e criar uma nova #2
            //     no mesmo lote colide mesmo com a exclusão acontecendo
            //     "antes" na ordem de código (achado real, 2026-07-12,
            //     reproduzido como 500 cru pelo frontend);
            // (b) 2 parcelas sobreviventes podem trocar de número entre
            //     si no mesmo payload (#1 vira #2 e #2 vira #1) — sem
            //     essa fase intermediária, atualizar a primeira colide
            //     com o número que a segunda ainda não largou.
            foreach ($unpaidExisting as $installment) {
                $installment->installment_number = 1_000_000 + $installment->id;
                $installment->save();
            }

            // Fase 1 — exclui as parcelas não pagas existentes que não
            // foram referenciadas no payload (já estão com número
            // sentinela, a exclusão em si não colide com nada).
            foreach ($unpaidExisting as $uuid => $installment) {
                if (in_array($uuid, $touchedUuids, true)) {
                    continue;
                }

                $this->releaseNumberAndDelete($installment);

                event(new SaleInstallmentDeleted(
                    saleUuid: $order->uuid,
                    installmentUuid: $uuid,
                    actorId: Auth::id()
                ));
            }

            // Fase 2 — aplica os valores reais nos itens do payload com
            // uuid (update). Todos os números reais já estão livres
            // (fase 0 moveu tudo pra sentinela), então nenhuma combinação
            // de troca/reuso colide aqui.
            foreach ($items as $item) {
                $uuid = $item['uuid'] ?? null;

                if ($uuid === null) {
                    continue;
                }

                $installment = $unpaidExisting->get($uuid);
                $amountCents = (int) round(((float) $item['amount']) * 100);

                $installment->installment_number = $item['installment_number'];
                $installment->amount = $this->centsToDecimal($amountCents);
                $installment->due_date = $item['due_date'];
                $installment->save();

                event(new SaleInstallmentUpdated(
                    saleUuid: $order->uuid,
                    installmentUuid: $installment->uuid,
                    actorId: Auth::id(),
                    changes: ['installment_number', 'amount', 'due_date']
                ));
            }

            // Fase 3 — cria os itens sem uuid.
            foreach ($items as $item) {
                $uuid = $item['uuid'] ?? null;

                if ($uuid !== null) {
                    continue;
                }

                $amountCents = (int) round(((float) $item['amount']) * 100);

                $created = SaleInstallment::create([
                    'tenant_id' => $order->tenant_id,
                    'sale_id' => $order->id,
                    'installment_number' => $item['installment_number'],
                    'amount' => $this->centsToDecimal($amountCents),
                    'due_date' => $item['due_date'],
                    'is_paid' => false,
                ]);

                event(new SaleInstallmentCreated(
                    saleUuid: $order->uuid,
                    installmentUuid: $created->uuid,
                    actorId: Auth::id()
                ));
            }

            // Única validação de soma da operação inteira — é isso que
            // permite redistribuir valor entre parcelas numa chamada só.
            $this->assertSumMatchesTotal($order);

            return SaleInstallment::where('sale_id', $order->id)
                ->whereNull('deleted_at')
                ->orderBy('installment_number')
                ->get();
        });
    }

    /**
     * Move installment_number pra um sentinela único (fora de qualquer
     * faixa real de uso) antes do soft delete — a constraint única
     * `uniq_order_installment_number` (sale_id, installment_number)
     * NÃO filtra `deleted_at`, então soft delete sozinho não libera o
     * número no banco. Sem isso, criar/editar outra parcela reaproveitando
     * o mesmo número (seja no mesmo `reallocate()` ou numa chamada
     * futura de `create()`/`update()`) colide com a constraint mesmo a
     * parcela antiga estando "excluída" do ponto de vista da aplicação
     * — bug real reproduzido pelo frontend como 500 cru, 2026-07-12.
     * `1_000_000 + id` é garantidamente único (id é PK auto-increment
     * global) e nunca colide com um installment_number real (validado
     * `min:1`, uso real nunca chega perto de 1 milhão de parcelas).
     */
    private function releaseNumberAndDelete(SaleInstallment $installment): void
    {
        $installment->installment_number = 1_000_000 + $installment->id;
        $installment->save();
        $installment->delete();
    }

    /**
     * Regra 1 (só venda parcelado) + regra 3 (venda cancelado bloqueia
     * tudo) — mesma checagem de estado já usada em deliver/pay/cancel.
     */
    private function assertMutable(Sale $order): void
    {
        if (!$order->is_installment) {
            throw new InvalidSaleStateException(__('messages.sale.not_installment'));
        }

        if ($order->cancelled_at !== null) {
            throw new InvalidSaleStateException(__('messages.sale.already_cancelled'));
        }
    }

    /**
     * Regra 2: parcela já paga é imutável (nem editar nem excluir).
     */
    private function assertInstallmentIsEditable(SaleInstallment $installment): void
    {
        if ($installment->is_paid) {
            throw new InvalidSaleStateException(__('messages.sale.installment_immutable_when_paid'));
        }
    }

    /**
     * Regra 5: installment_number único por compra — valida antes de
     * criar/editar pra dar 422 amigável em vez de estourar a constraint
     * `uniq_order_installment_number` do banco.
     */
    private function assertInstallmentNumberAvailable(Sale $order, int $installmentNumber, ?int $excludeInstallmentId = null): void
    {
        $exists = SaleInstallment::where('sale_id', $order->id)
            ->where('installment_number', $installmentNumber)
            ->whereNull('deleted_at')
            ->when($excludeInstallmentId, fn($q) => $q->whereKeyNot($excludeInstallmentId))
            ->exists();

        if ($exists) {
            throw new InvalidSaleStateException(__('messages.sale.installment_number_duplicate'));
        }
    }

    /**
     * Regra 4 — o núcleo da correção do bug do legado: depois de
     * qualquer create/update/delete, a soma de TODAS as parcelas
     * (pagas + não pagas) precisa bater exatamente com
     * sales.total_amount, em centavos (mesmo padrão de precisão do
     * resto do serviço, ver SaleService::centsToDecimal). Se não bater,
     * a exceção propaga e o DB::transaction() do caller reverte a
     * operação inteira — nunca persiste um estado inconsistente.
     */
    private function assertSumMatchesTotal(Sale $order): void
    {
        $totalCents = (int) round(((float) $order->total_amount) * 100);

        $sumCents = 0;
        foreach (
            SaleInstallment::where('sale_id', $order->id)
                ->whereNull('deleted_at')
                ->pluck('amount') as $amount
        ) {
            $sumCents += (int) round(((float) $amount) * 100);
        }

        if ($sumCents === $totalCents) {
            return;
        }

        $diffCents = $totalCents - $sumCents;

        throw new InvalidSaleStateException(__('messages.sale.installment_sum_mismatch', [
            'sum' => $this->centsToDecimal($sumCents),
            'total' => $this->centsToDecimal($totalCents),
            'diff' => $this->centsToDecimal(abs($diffCents)),
        ]));
    }

    private function centsToDecimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /**
     * Mesmo motivo/documentação de SaleService::lockOrder() — trava a
     * linha do Sale antes de qualquer leitura/mutação de parcela, pra
     * serializar operações concorrentes sobre o mesma venda (incluindo
     * concorrência com deliver/pay/payInstallment/cancel, que já travam
     * a mesma linha).
     */
    private function lockOrder(Sale $order): Sale
    {
        return Sale::whereKey($order->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Mesmo motivo/documentação de SaleService::assertBelongsToCurrentTenant().
     */
    private function assertBelongsToCurrentTenant(BaseModel $model): void
    {
        if ((int) $model->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }

    /**
     * Mesmo motivo/documentação de SaleService::assertInstallmentBelongsToOrder()
     * — rota declarada manualmente, não Route::resource, sem scoped bindings.
     */
    private function assertInstallmentBelongsToOrder(Sale $order, SaleInstallment $installment): void
    {
        if ((int) $installment->sale_id !== (int) $order->id) {
            abort(404);
        }
    }
}
