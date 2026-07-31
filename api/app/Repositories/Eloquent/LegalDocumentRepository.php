<?php

namespace App\Repositories\Eloquent;

use App\Models\Legal\LegalDocument;
use App\Repositories\Contracts\LegalDocumentRepositoryInterface;

class LegalDocumentRepository extends BaseRepository implements LegalDocumentRepositoryInterface
{
    public function __construct(LegalDocument $model)
    {
        parent::__construct($model);
    }

    public function findActiveByType(string $type): ?LegalDocument
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('type', $type)
            ->where('is_active', true)
            ->orderByDesc('published_at')
            ->first();
    }
}
