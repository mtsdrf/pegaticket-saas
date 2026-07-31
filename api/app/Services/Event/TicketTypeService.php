<?php

namespace App\Services\Event;

use App\DTOs\Event\CreateTicketTypeDTO;
use App\DTOs\Event\UpdateTicketTypeDTO;
use App\Events\Event\TicketTypeCreated;
use App\Events\Event\TicketTypeUpdated;
use App\Events\Event\TicketTypeDeleted;
use App\Models\Event\Event;
use App\Models\Event\TicketType;
use App\Models\Tenant\Tenant;
use App\Repositories\Contracts\TicketTypeRepositoryInterface;
use App\Services\Media\MediaStorageService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TicketTypeService
{
    public const EAGER_RELATIONS = ['event'];

    public const DETAIL_RELATIONS = ['event'];

    /**
     * Colunas de `ticket_types` exceto `image_data` (LONGBLOB) — mesmo
     * cuidado de ProductService::listColumns() (herdado do domínio antigo).
     * Reaproveitado por StorefrontCatalogService (catálogo público).
     */
    public static function listColumns(): array
    {
        static $columns;

        return $columns ??= array_map(
            fn($c) => "ticket_types.$c",
            array_diff(Schema::getColumnListing('ticket_types'), ['image_data'])
        );
    }

    public function __construct(
        private TicketTypeRepositoryInterface $repository,
        private MediaStorageService $mediaStorage
    ) {
    }

    public function find(TicketType $ticketType): TicketType
    {
        $this->assertBelongsToCurrentTenant($ticketType);

        return $ticketType;
    }

    private const SORTABLE = [
        'name' => 'ticket_types.name',
        'price' => 'ticket_types.price',
        'status' => 'ticket_types.status',
    ];

    public function paginate(
        int $tenantId,
        array $filters = [],
        int $perPage = 15,
        ?string $sortBy = null,
        string $sortDir = 'asc'
    ): LengthAwarePaginator {
        $query = TicketType::query()
            ->select(self::listColumns())
            ->where('ticket_types.tenant_id', $tenantId)
            ->whereNull('ticket_types.deleted_at')
            ->with(self::EAGER_RELATIONS)
            ->withSum('stockBalances', 'quantity_on_hand');

        if (!empty($filters['name'])) {
            $query->where('ticket_types.name', 'like', '%' . $filters['name'] . '%');
        }

        if (!empty($filters['event_uuid'])) {
            $query->whereHas('event', fn($q) => $q->where('uuid', $filters['event_uuid']));
        }

        if (!empty($filters['status'])) {
            $query->where('ticket_types.status', $filters['status']);
        }

        if (array_key_exists('price_min', $filters) && $filters['price_min'] !== null && $filters['price_min'] !== '') {
            $query->where('ticket_types.price', '>=', $filters['price_min']);
        }

        if (array_key_exists('price_max', $filters) && $filters['price_max'] !== null && $filters['price_max'] !== '') {
            $query->where('ticket_types.price', '<=', $filters['price_max']);
        }

        if (!empty($filters['q'])) {
            $term = $filters['q'];
            $query->where(function ($sub) use ($term) {
                $sub->where('ticket_types.name', 'like', '%' . $term . '%')
                    ->orWhereHas('event', fn($r) => $r->where('name', 'like', '%' . $term . '%'));
            });
        }

        $sortColumn = self::SORTABLE[$sortBy] ?? null;
        $dir = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

        if ($sortColumn) {
            $query->orderBy($sortColumn, $dir);
        } else {
            $query->orderBy('ticket_types.name');
        }

        return $query->paginate($perPage);
    }

    public function create(CreateTicketTypeDTO $dto): TicketType
    {
        $newMedia = null;

        if ($dto->image) {
            $newMedia = $this->mediaStorage->storePublicImage($dto->image, $this->resolveMediaDirectory($dto->tenantId), 'ticket_type');
        }

        try {
            return DB::transaction(function () use ($dto, $newMedia) {
                $event = Event::where('uuid', $dto->eventUuid)
                    ->where('tenant_id', $dto->tenantId)
                    ->whereNull('deleted_at')
                    ->firstOrFail();

                $ticketType = $this->repository->create([
                    'tenant_id' => $dto->tenantId,
                    'event_id' => $event->id,
                    'name' => $dto->name,
                    'description' => $dto->description,
                    'price' => $dto->price,
                    'image_path' => $newMedia['path'] ?? null,
                    'image_data' => null,
                    'image_mime' => $newMedia['mime'] ?? null,
                    'image_updated_at' => $newMedia['updated_at'] ?? null,
                    'quantity_available' => $dto->quantityAvailable,
                    'min_per_order' => $dto->minPerOrder,
                    'max_per_order' => $dto->maxPerOrder,
                    'sales_start_at' => $dto->salesStartAt,
                    'sales_end_at' => $dto->salesEndAt,
                    'status' => $dto->status,
                    'sku' => $dto->sku,
                    'barcode' => $dto->barcode,
                    'brand' => $dto->brand,
                    'unit' => $dto->unit,
                ]);

                event(new TicketTypeCreated(
                    ticketTypeUuid: $ticketType->uuid,
                    actorId: Auth::id()
                ));

                return $ticketType;
            });
        } catch (\Throwable $e) {
            if ($newMedia) {
                $this->mediaStorage->deletePublicMedia($newMedia['path'], 'ticket_type');
            }

            throw $e;
        }
    }

    public function update(TicketType $ticketType, UpdateTicketTypeDTO $dto): TicketType
    {
        $this->assertBelongsToCurrentTenant($ticketType);

        $newMedia = null;
        $oldPath = $ticketType->image_path;

        if ($dto->image) {
            $newMedia = $this->mediaStorage->storePublicImage($dto->image, $this->resolveMediaDirectory($ticketType->tenant_id), 'ticket_type');
        }

        try {
            return DB::transaction(function () use ($ticketType, $dto, $newMedia, $oldPath) {
                $original = $ticketType->getOriginal();

                $data = array_filter([
                    'name' => $dto->name,
                    'description' => $dto->description,
                    'price' => $dto->price,
                    'quantity_available' => $dto->quantityAvailable,
                    'min_per_order' => $dto->minPerOrder,
                    'max_per_order' => $dto->maxPerOrder,
                    'sales_start_at' => $dto->salesStartAt,
                    'sales_end_at' => $dto->salesEndAt,
                    'status' => $dto->status,
                    'sku' => $dto->sku,
                    'barcode' => $dto->barcode,
                    'brand' => $dto->brand,
                    'unit' => $dto->unit,
                ], fn($v) => !is_null($v));

                if ($dto->eventUuid) {
                    $event = Event::where('uuid', $dto->eventUuid)
                        ->where('tenant_id', $ticketType->tenant_id)
                        ->whereNull('deleted_at')
                        ->firstOrFail();

                    $data['event_id'] = $event->id;
                }

                if ($newMedia) {
                    $data['image_path'] = $newMedia['path'];
                    $data['image_data'] = null;
                    $data['image_mime'] = $newMedia['mime'];
                    $data['image_updated_at'] = $newMedia['updated_at'];
                }

                if (!empty($data)) {
                    $ticketType = $this->repository->update($ticketType, $data);
                }

                if ($newMedia && $oldPath && $oldPath !== $newMedia['path']) {
                    DB::afterCommit(fn() => $this->mediaStorage->deletePublicMedia($oldPath, 'ticket_type'));
                }

                $changes = array_diff_assoc($ticketType->getAttributes(), $original);

                if (!empty($changes)) {
                    event(new TicketTypeUpdated(
                        ticketTypeUuid: $ticketType->uuid,
                        actorId: Auth::id(),
                        changes: array_keys($changes)
                    ));
                }

                return $ticketType;
            });
        } catch (\Throwable $e) {
            if ($newMedia) {
                $this->mediaStorage->deletePublicMedia($newMedia['path'], 'ticket_type');
            }

            throw $e;
        }
    }

    /**
     * Atalho pra alternar status ativo/pausado sem payload inteiro — mesmo
     * espírito de ProductController::toggleAvailability(), adaptado pro
     * enum de status do ingresso. Sem $status explícito, alterna entre
     * 'ativo' e 'pausado'.
     */
    public function toggleStatus(TicketType $ticketType, ?string $status = null): TicketType
    {
        $target = $status ?? ($ticketType->status === 'ativo' ? 'pausado' : 'ativo');

        $dto = UpdateTicketTypeDTO::fromArray(['status' => $target]);

        return $this->update($ticketType, $dto);
    }

    public function delete(TicketType $ticketType): void
    {
        $this->assertBelongsToCurrentTenant($ticketType);

        DB::transaction(function () use ($ticketType) {
            $this->repository->delete($ticketType);

            event(new TicketTypeDeleted(
                ticketTypeUuid: $ticketType->uuid,
                actorId: Auth::id()
            ));
        });
    }

    private function assertBelongsToCurrentTenant(TicketType $ticketType): void
    {
        if ((int) $ticketType->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }

    private function resolveMediaDirectory(int $tenantId): string
    {
        return (string) Tenant::query()
            ->whereKey($tenantId)
            ->value('uuid');
    }
}
