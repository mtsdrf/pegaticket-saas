<?php

namespace App\Services\Legal;

use App\Models\Legal\LegalDocument;
use App\Repositories\Contracts\LegalDocumentRepositoryInterface;

class LegalDocumentService
{
    public function __construct(
        private LegalDocumentRepositoryInterface $repository
    ) {
    }

    /**
     * Versão vigente de um documento legal por tipo (terms|privacy).
     * 404 se não houver versão ativa publicada (ModelNotFoundException →
     * 404 automático do Laravel).
     */
    public function getActiveByType(string $type): LegalDocument
    {
        $document = $this->repository->findActiveByType($type);

        if (! $document) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException();
        }

        return $document;
    }
}
