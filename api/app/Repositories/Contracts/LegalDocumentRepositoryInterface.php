<?php

namespace App\Repositories\Contracts;

use App\Models\Legal\LegalDocument;

interface LegalDocumentRepositoryInterface extends BaseRepositoryInterface
{
    public function findActiveByType(string $type): ?LegalDocument;
}
