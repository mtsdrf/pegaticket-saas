<?php

namespace App\Services\Fiscal;

use App\Contracts\Fiscal\FiscalProviderInterface;
use App\Models\Fiscal\FiscalDocument;
use App\Models\Tenant\Tenant;

class FiscalProviderRegistry
{
    public function forTenant(Tenant $tenant): FiscalProviderInterface
    {
        return match ($this->providerSlug($tenant)) {
            'focus_nfe' => new DraftOnlyFiscalProvider('focus_nfe'),
            'plugnotas' => new DraftOnlyFiscalProvider('plugnotas'),
            'nfeio' => new DraftOnlyFiscalProvider('nfeio'),
            'sped_nfe' => new DraftOnlyFiscalProvider('sped_nfe'),
            default => new ManualFiscalProvider(),
        };
    }

    public function forDocument(FiscalDocument $document): FiscalProviderInterface
    {
        if ($document->relationLoaded('tenant') && $document->tenant) {
            return $this->forTenant($document->tenant);
        }

        /** @var Tenant|null $tenant */
        $tenant = Tenant::query()->find($document->tenant_id);

        return $tenant ? $this->forTenant($tenant) : new ManualFiscalProvider();
    }

    public function providerSlug(Tenant $tenant): string
    {
        return $tenant->fiscal_provider ?: 'manual';
    }
}
