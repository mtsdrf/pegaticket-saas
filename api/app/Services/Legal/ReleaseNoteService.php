<?php

namespace App\Services\Legal;

use App\DTOs\Legal\CreateReleaseNoteDTO;
use App\DTOs\Legal\UpdateReleaseNoteDTO;
use App\Events\Legal\ReleaseNoteCreated;
use App\Events\Legal\ReleaseNoteDeleted;
use App\Events\Legal\ReleaseNoteUpdated;
use App\Models\Legal\ReleaseNote;
use App\Repositories\Contracts\ReleaseNoteRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * CRUD restrito a administradores da plataforma (roadmap A1.6) — release
 * note é conteúdo da plataforma, não do tenant, mesmo padrão de
 * Plan/Functionality. `latest()` é o endpoint informativo consumido por
 * qualquer usuário autenticado (sem perm dedicada).
 */
class ReleaseNoteService
{
    public function __construct(
        private ReleaseNoteRepositoryInterface $repository
    ) {
    }

    /**
     * @return Collection<int, ReleaseNote>
     */
    public function latest(int $limit = 10): Collection
    {
        return $this->repository->latestPublished($limit);
    }

    public function create(CreateReleaseNoteDTO $dto): ReleaseNote
    {
        return DB::transaction(function () use ($dto) {
            $note = $this->repository->create([
                'title' => $dto->title,
                'body' => $dto->body,
                'version' => $dto->version,
                'published_at' => $dto->publishedAt,
            ]);

            event(new ReleaseNoteCreated(
                releaseNoteUuid: $note->uuid,
                actorId: Auth::id()
            ));

            return $note;
        });
    }

    public function update(ReleaseNote $releaseNote, UpdateReleaseNoteDTO $dto): ReleaseNote
    {
        return DB::transaction(function () use ($releaseNote, $dto) {
            $original = $releaseNote->getOriginal();

            $releaseNote = $this->repository->update($releaseNote, [
                'title' => $dto->title,
                'body' => $dto->body,
                'version' => $dto->version,
                'published_at' => $dto->publishedAt,
            ]);

            $changes = array_keys(array_diff_assoc($releaseNote->getAttributes(), $original));

            if ($changes !== []) {
                event(new ReleaseNoteUpdated(
                    releaseNoteUuid: $releaseNote->uuid,
                    actorId: Auth::id(),
                    changes: $changes
                ));
            }

            return $releaseNote;
        });
    }

    public function delete(ReleaseNote $releaseNote): void
    {
        DB::transaction(function () use ($releaseNote) {
            $this->repository->delete($releaseNote);

            event(new ReleaseNoteDeleted(
                releaseNoteUuid: $releaseNote->uuid,
                actorId: Auth::id()
            ));
        });
    }
}
