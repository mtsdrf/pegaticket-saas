<?php

namespace App\Contracts\Fiscal;

use App\Models\Fiscal\FiscalDocument;

/**
 * Contrato de provedor fiscal (roadmap Fiscal D0), mesmo espírito de
 * PaymentProviderInterface. Abstrai a emissão real (SEFAZ/prefeitura) do
 * domínio. Implementação única nesta fatia: ManualFiscalProvider (no-op, sem
 * SEFAZ/certificado/XML real). Trocável por um adapter real (serviço pago ou
 * lib sped-nfe) via binding no AppServiceProvider, SEM tocar na modelagem de
 * dados (fiscal_documents) nem em quem consome esta interface.
 */
interface FiscalProviderInterface
{
    /**
     * Emite (ou registra a intenção de emitir) um documento fiscal. Retorna
     * metadados do provedor (uuid do documento, status, etc.).
     *
     * @return array<string, mixed>
     */
    public function issue(FiscalDocument $document): array;

    /**
     * Cancela um documento fiscal já emitido, com justificativa.
     *
     * @return array<string, mixed>
     */
    public function cancel(FiscalDocument $document, string $reason): array;

    /**
     * Consulta o estado atual de um documento no provedor.
     *
     * @return array<string, mixed>
     */
    public function getStatus(string $providerDocumentId): array;
}
