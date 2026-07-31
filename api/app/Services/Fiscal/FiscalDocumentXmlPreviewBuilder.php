<?php

namespace App\Services\Fiscal;

use App\Models\Fiscal\FiscalDocument;
use XMLWriter;

/**
 * Gera uma prévia técnica em XML a partir do snapshot fiscal congelado no
 * documento preparado. NÃO é XML fiscal oficial, assinado ou transmitível à
 * SEFAZ/prefeitura; serve só para inspeção operacional e futura evolução do
 * provider real.
 */
class FiscalDocumentXmlPreviewBuilder
{
    public function build(FiscalDocument $document): string
    {
        $snapshot = (array) ($document->payload_snapshot ?? []);

        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->setIndent(true);
        $xml->startComment();
        $xml->text('PREVIA TECNICA DO RASCUNHO FISCAL - NAO E UM XML FISCAL OFICIAL OU ASSINADO');
        $xml->endComment();

        $xml->startElement('maskatsFiscalDraft');
        $xml->writeAttribute('version', '1.0');
        $xml->writeAttribute('generatedAt', (string) data_get($snapshot, 'generated_at', now()->toIso8601String()));
        $xml->writeAttribute('documentType', (string) $document->document_type);
        $xml->writeAttribute('status', (string) $document->status);

        $xml->startElement('document');
        $this->writeElement($xml, 'uuid', $document->uuid);
        $this->writeElement($xml, 'series', $document->series);
        $this->writeElement($xml, 'number', $document->document_number);
        $this->writeElement($xml, 'provider', $document->provider);
        $this->writeElement($xml, 'providerDocumentId', $document->provider_document_id);
        $xml->endElement();

        $this->writeNode($xml, 'issuer', (array) data_get($snapshot, 'issuer', []));
        $this->writeNode($xml, 'recipient', (array) data_get($snapshot, 'recipient', []));
        $this->writeNode($xml, 'operation', (array) data_get($snapshot, 'operation', []));

        $xml->startElement('items');
        foreach ((array) data_get($snapshot, 'items', []) as $item) {
            $xml->startElement('item');
            $this->writeNode($xml, 'data', is_array($item) ? $item : []);
            $xml->endElement();
        }
        $xml->endElement();

        $this->writeNode($xml, 'totals', (array) data_get($snapshot, 'totals', []));

        $xml->startElement('issues');
        foreach ((array) data_get($snapshot, 'issues', []) as $issue) {
            $xml->startElement('issue');
            $this->writeNode($xml, 'data', is_array($issue) ? $issue : []);
            $xml->endElement();
        }
        $xml->endElement();

        $xml->endElement();

        return $xml->outputMemory();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeNode(XMLWriter $xml, string $name, array $data): void
    {
        $xml->startElement($name);

        foreach ($data as $key => $value) {
            $elementName = $this->sanitizeElementName((string) $key);

            if (is_array($value)) {
                if (array_is_list($value)) {
                    $xml->startElement($elementName);
                    foreach ($value as $listItem) {
                        if (is_array($listItem)) {
                            $this->writeNode($xml, 'item', $listItem);
                        } else {
                            $this->writeElement($xml, 'item', $this->normalizeScalar($listItem));
                        }
                    }
                    $xml->endElement();
                    continue;
                }

                $this->writeNode($xml, $elementName, $value);
                continue;
            }

            $this->writeElement($xml, $elementName, $this->normalizeScalar($value));
        }

        $xml->endElement();
    }

    private function writeElement(XMLWriter $xml, string $name, mixed $value): void
    {
        $xml->startElement($this->sanitizeElementName($name));
        $xml->text($this->normalizeScalar($value));
        $xml->endElement();
    }

    private function normalizeScalar(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    private function sanitizeElementName(string $value): string
    {
        $normalized = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $value) ?? 'field';

        if ($normalized === '' || preg_match('/^[0-9]/', $normalized)) {
            return 'field_' . $normalized;
        }

        return $normalized;
    }
}
