<?php

namespace App\Services\Event;

use App\DTOs\Event\CreateEventDTO;
use App\DTOs\Event\UpdateEventDTO;
use App\Events\Event\EventCreated;
use App\Events\Event\EventUpdated;
use App\Events\Event\EventDeleted;
use App\Models\Event\Event;
use App\Models\Event\EventCategory;
use App\Models\Tenant\Tenant;
use App\Models\Venue\Venue;
use App\Models\Venue\VenueMapVersion;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Services\Media\MediaStorageService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EventService
{
    public const EAGER_RELATIONS = ['category', 'venueMapVersion.venue'];

    public const DETAIL_RELATIONS = ['category', 'ticketTypes', 'eventProducts', 'venueMapVersion.venue'];

    /**
     * Colunas de `events` exceto `cover_image_data` (LONGBLOB) — mesmo
     * cuidado de ProductService::listColumns() para listagem/grid.
     */
    public static function listColumns(): array
    {
        static $columns;

        return $columns ??= array_map(
            fn($c) => "events.$c",
            array_diff(Schema::getColumnListing('events'), ['cover_image_data'])
        );
    }

    public function __construct(
        private EventRepositoryInterface $repository,
        private MediaStorageService $mediaStorage
    ) {
    }

    public function find(Event $event): Event
    {
        $this->assertBelongsToCurrentTenant($event);

        return $event;
    }

    private const SORTABLE = [
        'name' => 'events.name',
        'starts_at' => 'events.starts_at',
        'status' => 'events.status',
    ];

    public function paginate(
        int $tenantId,
        array $filters = [],
        int $perPage = 15,
        ?string $sortBy = null,
        string $sortDir = 'asc'
    ): LengthAwarePaginator {
        $query = Event::query()
            ->select(self::listColumns())
            ->where('events.tenant_id', $tenantId)
            ->whereNull('events.deleted_at')
            ->with(self::EAGER_RELATIONS);

        if (!empty($filters['name'])) {
            $query->where('events.name', 'like', '%' . $filters['name'] . '%');
        }

        if (!empty($filters['event_category_uuid'])) {
            $query->whereHas('category', fn($q) => $q->where('uuid', $filters['event_category_uuid']));
        }

        if (!empty($filters['type'])) {
            $query->where('events.type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $query->where('events.status', $filters['status']);
        }

        if (!empty($filters['visibility'])) {
            $query->where('events.visibility', $filters['visibility']);
        }

        if (!empty($filters['q'])) {
            $term = $filters['q'];
            $query->where(function ($sub) use ($term) {
                $sub->where('events.name', 'like', '%' . $term . '%')
                    ->orWhere('events.location_name', 'like', '%' . $term . '%');
            });
        }

        $sortColumn = self::SORTABLE[$sortBy] ?? null;
        $dir = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

        if ($sortColumn) {
            $query->orderBy($sortColumn, $dir);
        } else {
            $query->orderBy('events.starts_at', 'desc');
        }

        return $query->paginate($perPage);
    }

    public function create(CreateEventDTO $dto): Event
    {
        $newMedia = null;

        if ($dto->coverImage) {
            $newMedia = $this->mediaStorage->storePublicImage($dto->coverImage, $this->resolveMediaDirectory($dto->tenantId), 'event');
        }

        try {
            return DB::transaction(function () use ($dto, $newMedia) {
                $categoryId = null;

                if ($dto->eventCategoryUuid) {
                    $categoryId = EventCategory::where('uuid', $dto->eventCategoryUuid)
                        ->where('tenant_id', $dto->tenantId)
                        ->whereNull('deleted_at')
                        ->firstOrFail()
                        ->id;
                }

                $venueMapVersionId = $this->resolveVenueMapVersionId($dto->tenantId, $dto->venueUuid);

                $event = $this->repository->create([
                    'tenant_id' => $dto->tenantId,
                    'event_category_id' => $categoryId,
                    'venue_map_version_id' => $venueMapVersionId,
                    'name' => $dto->name,
                    'slug' => $dto->slug,
                    'description_short' => $dto->descriptionShort,
                    'description_full' => $dto->descriptionFull,
                    'cover_image_path' => $newMedia['path'] ?? null,
                    'cover_image_data' => null,
                    'cover_image_mime' => $newMedia['mime'] ?? null,
                    'cover_image_updated_at' => $newMedia['updated_at'] ?? null,
                    'type' => $dto->type,
                    'location_name' => $dto->locationName,
                    'location_address' => $dto->locationAddress,
                    'starts_at' => $dto->startsAt,
                    'ends_at' => $dto->endsAt,
                    'visibility' => $dto->visibility,
                    'status' => $dto->status,
                ]);

                event(new EventCreated(
                    eventUuid: $event->uuid,
                    actorId: Auth::id()
                ));

                return $event;
            });
        } catch (\Throwable $e) {
            if ($newMedia) {
                $this->mediaStorage->deletePublicMedia($newMedia['path'], 'event');
            }

            throw $e;
        }
    }

    public function update(Event $event, UpdateEventDTO $dto): Event
    {
        $this->assertBelongsToCurrentTenant($event);

        $newMedia = null;
        $oldPath = $event->cover_image_path;

        if ($dto->coverImage) {
            $newMedia = $this->mediaStorage->storePublicImage($dto->coverImage, $this->resolveMediaDirectory($event->tenant_id), 'event');
        }

        try {
            return DB::transaction(function () use ($event, $dto, $newMedia, $oldPath) {
                $original = $event->getOriginal();

                $data = array_filter([
                    'name' => $dto->name,
                    'slug' => $dto->slug,
                    'description_short' => $dto->descriptionShort,
                    'description_full' => $dto->descriptionFull,
                    'type' => $dto->type,
                    'location_name' => $dto->locationName,
                    'location_address' => $dto->locationAddress,
                    'starts_at' => $dto->startsAt,
                    'ends_at' => $dto->endsAt,
                    'visibility' => $dto->visibility,
                    'status' => $dto->status,
                ], fn($v) => !is_null($v));

                if ($dto->eventCategoryUuid) {
                    $data['event_category_id'] = EventCategory::where('uuid', $dto->eventCategoryUuid)
                        ->where('tenant_id', $event->tenant_id)
                        ->whereNull('deleted_at')
                        ->firstOrFail()
                        ->id;
                }

                if ($dto->venueUuidProvided) {
                    $data['venue_map_version_id'] = $this->resolveVenueMapVersionId($event->tenant_id, $dto->venueUuid);
                }

                if ($newMedia) {
                    $data['cover_image_path'] = $newMedia['path'];
                    $data['cover_image_data'] = null;
                    $data['cover_image_mime'] = $newMedia['mime'];
                    $data['cover_image_updated_at'] = $newMedia['updated_at'];
                }

                if (!empty($data)) {
                    $event = $this->repository->update($event, $data);
                }

                if ($newMedia && $oldPath && $oldPath !== $newMedia['path']) {
                    DB::afterCommit(fn() => $this->mediaStorage->deletePublicMedia($oldPath, 'event'));
                }

                $changes = array_diff_assoc($event->getAttributes(), $original);

                if (!empty($changes)) {
                    event(new EventUpdated(
                        eventUuid: $event->uuid,
                        actorId: Auth::id(),
                        changes: array_keys($changes)
                    ));
                }

                return $event;
            });
        } catch (\Throwable $e) {
            if ($newMedia) {
                $this->mediaStorage->deletePublicMedia($newMedia['path'], 'event');
            }

            throw $e;
        }
    }

    public function delete(Event $event): void
    {
        $this->assertBelongsToCurrentTenant($event);

        DB::transaction(function () use ($event) {
            $this->repository->delete($event);

            event(new EventDeleted(
                eventUuid: $event->uuid,
                actorId: Auth::id()
            ));
        });
    }

    private function assertBelongsToCurrentTenant(Event $event): void
    {
        if ((int) $event->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }

    private function resolveVenueMapVersionId(int $tenantId, ?string $venueUuid): ?int
    {
        if (!$venueUuid) {
            return null;
        }

        $venue = Venue::query()
            ->where('uuid', $venueUuid)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $publishedVersion = VenueMapVersion::query()
            ->where('venue_id', $venue->id)
            ->where('tenant_id', $tenantId)
            ->where('is_published', true)
            ->whereNull('deleted_at')
            ->orderByDesc('version_number')
            ->first();

        if (!$publishedVersion) {
            abort(422, __('messages.event.venue_requires_published_map'));
        }

        return $publishedVersion->id;
    }

    private function resolveMediaDirectory(int $tenantId): string
    {
        return (string) Tenant::query()
            ->whereKey($tenantId)
            ->value('uuid');
    }
}
