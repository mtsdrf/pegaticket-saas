<?php

namespace App\Services\Fiscal;

use App\Models\Fiscal\FiscalOperationProfile;
use App\Models\Tenant\Tenant;

class FiscalProviderReadinessService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function issuesForDocument(Tenant $tenant, string $documentType): array
    {
        $issues = [];
        $provider = $tenant->fiscal_provider ?: 'manual';

        if (in_array($provider, ['focus_nfe', 'plugnotas', 'nfeio'], true) && blank($tenant->fiscal_provider_api_token)) {
            $issues[] = $this->error(
                'provider_api_token',
                __('messages.order.fiscal_provider_token_missing')
            );
        }

        if ($provider === 'sped_nfe' && blank($tenant->fiscal_certificate_a1_data)) {
            $issues[] = $this->error(
                'provider_certificate',
                __('messages.order.fiscal_provider_certificate_missing')
            );
        }

        if ($provider === 'sped_nfe' && blank($tenant->fiscal_certificate_a1_password)) {
            $issues[] = $this->error(
                'provider_certificate_password',
                __('messages.order.fiscal_provider_certificate_password_missing')
            );
        }

        if ($documentType === 'nfce' && (blank($tenant->fiscal_nfce_csc_id) || blank($tenant->fiscal_nfce_csc_code))) {
            $issues[] = $this->error(
                'nfce_csc',
                __('messages.order.fiscal_nfce_csc_missing')
            );
        }

        return $issues;
    }

    public function readinessCheck(Tenant $tenant): array
    {
        $documentTypes = FiscalOperationProfile::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->pluck('document_type')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $provider = $tenant->fiscal_provider ?: 'manual';
        $details = [];
        $status = 'ok';

        if ($provider === 'manual') {
            $details[] = __('messages.fiscal_readiness.provider_manual_mode');
            return $this->check('provider', 'Provider fiscal', 'ok', implode(' ', $details));
        }

        if (in_array($provider, ['focus_nfe', 'plugnotas', 'nfeio'], true) && blank($tenant->fiscal_provider_api_token)) {
            $status = 'warning';
            $details[] = __('messages.fiscal_readiness.provider_token_missing');
        }

        if ($provider === 'sped_nfe' && blank($tenant->fiscal_certificate_a1_data)) {
            $status = 'warning';
            $details[] = __('messages.fiscal_readiness.provider_certificate_missing');
        }

        if ($provider === 'sped_nfe' && blank($tenant->fiscal_certificate_a1_password)) {
            $status = 'warning';
            $details[] = __('messages.fiscal_readiness.provider_certificate_password_missing');
        }

        if (in_array('nfce', $documentTypes, true) && (blank($tenant->fiscal_nfce_csc_id) || blank($tenant->fiscal_nfce_csc_code))) {
            $status = 'warning';
            $details[] = __('messages.fiscal_readiness.nfce_csc_missing');
        }

        if ($details === []) {
            $details[] = __('messages.fiscal_readiness.provider_ready', ['provider' => $provider]);
        }

        return $this->check(
            'provider',
            'Provider fiscal',
            $status,
            implode(' ', $details)
        );
    }

    private function check(string $key, string $label, string $status, string $details): array
    {
        return compact('key', 'label', 'status', 'details');
    }

    private function error(string $key, string $details): array
    {
        return [
            'key' => $key,
            'severity' => 'error',
            'details' => $details,
        ];
    }
}
