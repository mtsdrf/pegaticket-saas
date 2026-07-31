<?php

namespace App\Http\Controllers\Legal;

use App\Http\Controllers\Controller;
use App\Http\Resources\Legal\LegalDocumentResource;
use App\Services\APIResponse;
use App\Services\Legal\LegalDocumentService;

class LegalDocumentController extends Controller
{
    public function __construct(
        private LegalDocumentService $service
    ) {
    }

    /**
     * Versão ativa do documento legal do tipo informado (terms|privacy).
     * Rota 100% pública — o texto legal precisa ser lido antes do cadastro,
     * sem sessão. {type} validado pela constraint da rota (regex).
     */
    public function show(string $type)
    {
        $document = $this->service->getActiveByType($type);

        return APIResponse::success(
            new LegalDocumentResource($document),
            __('messages.legal_document.shown')
        );
    }
}
