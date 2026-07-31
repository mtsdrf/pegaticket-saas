<?php

namespace App\Repositories\Eloquent;

use App\Models\Legal\ReleaseNote;
use App\Repositories\Contracts\ReleaseNoteRepositoryInterface;
use Illuminate\Support\Collection;

class ReleaseNoteRepository extends BaseRepository implements ReleaseNoteRepositoryInterface
{
    public function __construct(ReleaseNote $model)
    {
        parent::__construct($model);
    }

    public function latestPublished(int $limit): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }
}
