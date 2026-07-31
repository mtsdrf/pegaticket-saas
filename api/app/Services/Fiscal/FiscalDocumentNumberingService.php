<?php

namespace App\Services\Fiscal;

use App\Models\Fiscal\FiscalDocument;
use App\Models\Tenant\Tenant;

class FiscalDocumentNumberingService
{
    public function assignDraftSequence(FiscalDocument $document): FiscalDocument
    {
        if (!blank($document->series) && $document->document_number !== null) {
            return $document;
        }

        /** @var Tenant $tenant */
        $tenant = Tenant::query()->lockForUpdate()->findOrFail($document->tenant_id);

        $seriesField = $this->seriesField($document->document_type);
        $nextNumberField = $this->nextNumberField($document->document_type);
        $series = $tenant->{$seriesField};

        if (blank($series)) {
            return $document;
        }

        $documentNumber = $tenant->{$nextNumberField} ?? 1;

        $document->fill([
            'series' => (string) $series,
            'document_number' => (int) $documentNumber,
        ])->save();

        $tenant->forceFill([
            $nextNumberField => $documentNumber + 1,
        ])->save();

        return $document;
    }

    private function seriesField(string $documentType): string
    {
        return match ($documentType) {
            'nfce' => 'fiscal_nfce_series',
            'nfse' => 'fiscal_nfse_series',
            default => 'fiscal_nfe_series',
        };
    }

    private function nextNumberField(string $documentType): string
    {
        return match ($documentType) {
            'nfce' => 'fiscal_next_nfce_number',
            'nfse' => 'fiscal_next_nfse_number',
            default => 'fiscal_next_nfe_number',
        };
    }
}
