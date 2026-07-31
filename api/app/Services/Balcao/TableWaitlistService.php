<?php

namespace App\Services\Balcao;

use App\DTOs\Balcao\OpenComandaDTO;
use App\Exceptions\Balcao\TableWaitlistException;
use App\Models\Balcao\Table;
use App\Models\Balcao\TableWaitlist;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TableWaitlistService
{
    public function __construct(
        private ComandaService $comandaService,
    ) {
    }

    public function list(int $tenantId): Collection
    {
        return TableWaitlist::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereDate('created_at', today())
            ->with(['table', 'seatedComanda'])
            ->orderByRaw("FIELD(status, 'waiting', 'called', 'seated', 'cancelled')")
            ->orderBy('created_at')
            ->get();
    }

    public function create(int $tenantId, array $data): TableWaitlist
    {
        return TableWaitlist::create([
            'tenant_id' => $tenantId,
            'customer_name' => trim((string) $data['customer_name']),
            'customer_phone' => isset($data['customer_phone']) ? trim((string) $data['customer_phone']) : null,
            'party_size' => (int) $data['party_size'],
            'quoted_wait_minutes' => isset($data['quoted_wait_minutes']) ? (int) $data['quoted_wait_minutes'] : null,
            'status' => TableWaitlist::STATUS_WAITING,
            'notes' => $data['notes'] ?? null,
        ])->load(['table', 'seatedComanda']);
    }

    public function call(int $tenantId, string $uuid): TableWaitlist
    {
        $entry = $this->find($tenantId, $uuid);

        if ($entry->status !== TableWaitlist::STATUS_WAITING) {
            throw new TableWaitlistException(__('messages.table_waitlist.invalid_state_for_call'));
        }

        $entry->status = TableWaitlist::STATUS_CALLED;
        $entry->called_at = now();
        $entry->called_by = Auth::id();
        $entry->save();

        return $entry->load(['table', 'seatedComanda']);
    }

    public function seat(int $tenantId, string $uuid, array $data): TableWaitlist
    {
        return DB::transaction(function () use ($tenantId, $uuid, $data) {
            $entry = $this->find($tenantId, $uuid);

            if (!in_array($entry->status, [TableWaitlist::STATUS_WAITING, TableWaitlist::STATUS_CALLED], true)) {
                throw new TableWaitlistException(__('messages.table_waitlist.invalid_state_for_seating'));
            }

            $table = Table::query()
                ->where('tenant_id', $tenantId)
                ->where('uuid', $data['table_uuid'] ?? null)
                ->whereNull('deleted_at')
                ->first();

            if ($table === null) {
                abort(404);
            }

            if ($table->seats !== null && $table->seats < $entry->party_size) {
                throw new TableWaitlistException(__('messages.table_waitlist.table_capacity_insufficient'));
            }

            $comanda = $this->comandaService->open($tenantId, OpenComandaDTO::fromArray([
                'table_uuid' => $table->uuid,
                'label' => $data['label'] ?? $entry->customer_name,
            ]));

            $entry->table_id = $table->id;
            $entry->status = TableWaitlist::STATUS_SEATED;
            $entry->seated_at = now();
            $entry->seated_by = Auth::id();
            $entry->seated_comanda_id = $comanda->id;
            $entry->save();

            return $entry->load(['table', 'seatedComanda']);
        });
    }

    public function cancel(int $tenantId, string $uuid, string $reason): TableWaitlist
    {
        $entry = $this->find($tenantId, $uuid);

        if (in_array($entry->status, TableWaitlist::TERMINAL_STATUSES, true)) {
            throw new TableWaitlistException(__('messages.table_waitlist.already_finished'));
        }

        $entry->status = TableWaitlist::STATUS_CANCELLED;
        $entry->cancelled_reason = trim($reason);
        $entry->cancelled_at = now();
        $entry->cancelled_by = Auth::id();
        $entry->save();

        return $entry->load(['table', 'seatedComanda']);
    }

    private function find(int $tenantId, string $uuid): TableWaitlist
    {
        $entry = TableWaitlist::query()
            ->where('tenant_id', $tenantId)
            ->where('uuid', $uuid)
            ->whereNull('deleted_at')
            ->with(['table', 'seatedComanda'])
            ->first();

        if ($entry === null) {
            abort(404);
        }

        return $entry;
    }
}
