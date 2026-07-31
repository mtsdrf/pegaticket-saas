<?php

namespace App\Repositories\Contracts;

use App\Models\Legal\ReleaseNote;
use Illuminate\Support\Collection;

interface ReleaseNoteRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @return Collection<int, ReleaseNote>
     */
    public function latestPublished(int $limit): Collection;
}
