<?php

namespace App\Services\Venue;

use App\DTOs\Venue\CreateVenueDTO;
use App\DTOs\Venue\UpdateVenueDTO;
use App\Events\Venue\VenueCreated;
use App\Events\Venue\VenueUpdated;
use App\Events\Venue\VenueDeleted;
use App\Events\Venue\VenuePublished;
use App\Models\Tenant\Tenant;
use App\Models\Venue\Seat;
use App\Models\Venue\Venue;
use App\Models\Venue\VenueMapVersion;
use App\Repositories\Contracts\VenueRepositoryInterface;
use App\Services\Media\MediaStorageService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Desenho de versionamento de mapa (spec 5.7 — "a versão do mapa
 * utilizada pelo evento deverá ser preservada"), sem editor visual e sem
 * controller próprio para VenueMapVersion:
 *
 * - Todo Venue nasce com uma VenueMapVersion "draft" (is_published=false,
 *   version_number=1) — é nela que SeatService opera por padrão.
 * - publish() congela o draft atual (is_published=true) e abre
 *   automaticamente um novo draft (version_number+1) com os assentos
 *   clonados, para que a edição continue possível sem afetar a versão já
 *   publicada (a que um Event pode referenciar via
 *   events.venue_map_version_id).
 */
class VenueService
{
    public function __construct(
        private VenueRepositoryInterface $repository,
        private MediaStorageService $mediaStorage
    ) {
    }

    public function find(Venue $venue): Venue
    {
        $this->assertBelongsToCurrentTenant($venue);

        return $venue;
    }

    public function paginate(
        int $tenantId,
        array $filters = [],
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = Venue::query()
            ->where('venues.tenant_id', $tenantId)
            ->whereNull('venues.deleted_at');

        if (!empty($filters['name'])) {
            $query->where('venues.name', 'like', '%' . $filters['name'] . '%');
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('venues.is_active', (bool) $filters['is_active']);
        }

        return $query->orderBy('venues.name')->paginate($perPage);
    }

    public function create(CreateVenueDTO $dto): Venue
    {
        $newMedia = null;

        if ($dto->backgroundImage) {
            $newMedia = $this->mediaStorage->storePublicImage($dto->backgroundImage, $this->resolveMediaDirectory($dto->tenantId), 'venue');
        }

        try {
            return DB::transaction(function () use ($dto, $newMedia) {
                $venue = $this->repository->create([
                    'tenant_id' => $dto->tenantId,
                    'name' => $dto->name,
                    'background_image_path' => $newMedia['path'] ?? null,
                    'background_image_data' => null,
                    'background_image_mime' => $newMedia['mime'] ?? null,
                    'width' => $dto->width,
                    'height' => $dto->height,
                    'is_active' => $dto->isActive,
                ]);

                VenueMapVersion::create([
                    'tenant_id' => $dto->tenantId,
                    'venue_id' => $venue->id,
                    'version_number' => 1,
                    'is_published' => false,
                ]);

                event(new VenueCreated(
                    venueUuid: $venue->uuid,
                    actorId: Auth::id()
                ));

                return $venue;
            });
        } catch (\Throwable $e) {
            if ($newMedia) {
                $this->mediaStorage->deletePublicMedia($newMedia['path'], 'venue');
            }

            throw $e;
        }
    }

    public function update(Venue $venue, UpdateVenueDTO $dto): Venue
    {
        $this->assertBelongsToCurrentTenant($venue);

        $newMedia = null;
        $oldPath = $venue->background_image_path;

        if ($dto->backgroundImage) {
            $newMedia = $this->mediaStorage->storePublicImage($dto->backgroundImage, $this->resolveMediaDirectory($venue->tenant_id), 'venue');
        }

        try {
            return DB::transaction(function () use ($venue, $dto, $newMedia, $oldPath) {
                $original = $venue->getOriginal();

                $data = array_filter([
                    'name' => $dto->name,
                    'width' => $dto->width,
                    'height' => $dto->height,
                ], fn($v) => !is_null($v));

                if ($dto->isActive !== null) {
                    $data['is_active'] = $dto->isActive;
                }

                if ($newMedia) {
                    $data['background_image_path'] = $newMedia['path'];
                    $data['background_image_data'] = null;
                    $data['background_image_mime'] = $newMedia['mime'];
                }

                if (!empty($data)) {
                    $venue = $this->repository->update($venue, $data);
                }

                if ($newMedia && $oldPath && $oldPath !== $newMedia['path']) {
                    DB::afterCommit(fn() => $this->mediaStorage->deletePublicMedia($oldPath, 'venue'));
                }

                $changes = array_diff_assoc($venue->getAttributes(), $original);

                if (!empty($changes)) {
                    event(new VenueUpdated(
                        venueUuid: $venue->uuid,
                        actorId: Auth::id(),
                        changes: array_keys($changes)
                    ));
                }

                return $venue;
            });
        } catch (\Throwable $e) {
            if ($newMedia) {
                $this->mediaStorage->deletePublicMedia($newMedia['path'], 'venue');
            }

            throw $e;
        }
    }

    public function delete(Venue $venue): void
    {
        $this->assertBelongsToCurrentTenant($venue);

        DB::transaction(function () use ($venue) {
            $this->repository->delete($venue);

            event(new VenueDeleted(
                venueUuid: $venue->uuid,
                actorId: Auth::id()
            ));
        });
    }

    /**
     * Draft (não publicado) mais recente do Venue — cria um se por algum
     * motivo não existir (defensivo; o fluxo normal sempre tem um, criado
     * em create()/publish()).
     */
    public function currentDraftVersion(Venue $venue): VenueMapVersion
    {
        $draft = VenueMapVersion::where('venue_id', $venue->id)
            ->where('is_published', false)
            ->whereNull('deleted_at')
            ->orderByDesc('version_number')
            ->first();

        if ($draft) {
            return $draft;
        }

        $nextNumber = ((int) VenueMapVersion::where('venue_id', $venue->id)->max('version_number')) + 1;

        return VenueMapVersion::create([
            'tenant_id' => $venue->tenant_id,
            'venue_id' => $venue->id,
            'version_number' => $nextNumber,
            'is_published' => false,
        ]);
    }

    public function publish(Venue $venue): VenueMapVersion
    {
        $this->assertBelongsToCurrentTenant($venue);

        return DB::transaction(function () use ($venue) {
            $draft = $this->currentDraftVersion($venue);

            $draft = tap($draft)->update(['is_published' => true]);

            $nextNumber = $draft->version_number + 1;

            $newDraft = VenueMapVersion::create([
                'tenant_id' => $venue->tenant_id,
                'venue_id' => $venue->id,
                'version_number' => $nextNumber,
                'is_published' => false,
            ]);

            foreach (Seat::where('venue_map_version_id', $draft->id)->whereNull('deleted_at')->get() as $seat) {
                Seat::create([
                    'tenant_id' => $seat->tenant_id,
                    'venue_map_version_id' => $newDraft->id,
                    'sector_name' => $seat->sector_name,
                    'label' => $seat->label,
                    'kind' => $seat->kind,
                    'capacity' => $seat->capacity,
                    'pos_x' => $seat->pos_x,
                    'pos_y' => $seat->pos_y,
                    'is_accessible' => $seat->is_accessible,
                    'status' => $seat->status,
                ]);
            }

            event(new VenuePublished(
                venueUuid: $venue->uuid,
                venueMapVersionUuid: $draft->uuid,
                actorId: Auth::id()
            ));

            return $draft;
        });
    }

    private function assertBelongsToCurrentTenant(Venue $venue): void
    {
        if ((int) $venue->tenant_id !== (int) app('tenant_id')) {
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
