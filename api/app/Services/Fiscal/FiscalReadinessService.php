<?php

namespace App\Services\Fiscal;

use App\Models\Fiscal\FiscalOperationProfile;
use App\Models\Fiscal\TaxRule;
use App\Models\Product\Product;
use App\Models\Tenant\Tenant;

class FiscalReadinessService
{
    public function build(Tenant $tenant): array
    {
        $productQuery = Product::query()
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at');

        $totalProducts = (clone $productQuery)->count();
        $productsReady = (clone $productQuery)
            ->whereNotNull('ncm')
            ->whereNotNull('default_cfop')
            ->whereNotNull('origin')
            ->whereNotNull('csosn_cst')
            ->count();

        $taxRulesCount = TaxRule::query()
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->count();

        $profilesCount = FiscalOperationProfile::query()
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->count();

        $issuerReady = filled($tenant->cnpj)
            && filled($tenant->tax_regime)
            && filled($tenant->ibge_city_code)
            && $tenant->endereco_id !== null;

        $productsReadyCheck = $totalProducts > 0 && $productsReady === $totalProducts;
        $rulesReady = $taxRulesCount > 0 && $profilesCount > 0;

        $checks = [
            [
                'key' => 'issuer',
                'label' => 'Cadastro da empresa',
                'status' => $issuerReady ? 'ok' : 'warning',
                'details' => $issuerReady
                    ? 'Emitente com cadastro fiscal básico e endereço preenchidos.'
                    : 'Preencha CNPJ, regime tributário, código IBGE e endereço da empresa.',
            ],
            [
                'key' => 'products',
                'label' => 'Produtos fiscais',
                'status' => $productsReadyCheck ? 'ok' : 'warning',
                'details' => $productsReadyCheck
                    ? 'Todos os produtos ativos já possuem NCM, CFOP, origem e CSOSN/CST.'
                    : sprintf('%d de %d produtos já têm NCM, CFOP, origem e CSOSN/CST preenchidos.', $productsReady, $totalProducts),
            ],
            [
                'key' => 'rules',
                'label' => 'Regras e perfis fiscais',
                'status' => $rulesReady ? 'ok' : 'warning',
                'details' => $rulesReady
                    ? 'A empresa já possui regras tributárias e perfis fiscais ativos.'
                    : 'Cadastre pelo menos uma regra tributária e um perfil fiscal ativo.',
            ],
            [
                'key' => 'provider',
                'label' => 'Integração fiscal',
                ...$this->providerCheck($tenant),
            ],
        ];

        $okCount = collect($checks)->where('status', 'ok')->count();
        $score = (int) round(($okCount / max(count($checks), 1)) * 100);

        return [
            'status' => $score === 100 ? 'ok' : 'attention',
            'score_percent' => $score,
            'checks' => $checks,
        ];
    }

    private function providerCheck(Tenant $tenant): array
    {
        if (!$tenant->fiscal_provider || $tenant->fiscal_provider === 'manual') {
            return [
                'status' => 'warning',
                'details' => __('messages.fiscal_readiness.provider_manual_mode'),
            ];
        }

        if (blank($tenant->fiscal_provider_api_token)) {
            return [
                'status' => 'warning',
                'details' => __('messages.fiscal_readiness.provider_token_missing'),
            ];
        }

        if ($tenant->fiscal_provider === 'sped_nfe' && blank($tenant->fiscal_certificate_a1_data)) {
            return [
                'status' => 'warning',
                'details' => __('messages.fiscal_readiness.provider_certificate_missing'),
            ];
        }

        if ($tenant->fiscal_provider === 'sped_nfe' && blank($tenant->fiscal_certificate_a1_password)) {
            return [
                'status' => 'warning',
                'details' => __('messages.fiscal_readiness.provider_certificate_password_missing'),
            ];
        }

        return [
            'status' => 'ok',
            'details' => __('messages.fiscal_readiness.provider_ready', ['provider' => $tenant->fiscal_provider]),
        ];
    }
}
