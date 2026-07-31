<?php

namespace App\Services\Fiscal;

use App\Models\Client\Client;
use App\Models\Fiscal\FiscalOperationProfile;
use App\Models\Fiscal\TaxRule;
use App\Models\Product\Product;
use App\Models\Tenant\Tenant;

class FiscalReadinessCheckService
{
    public function __construct(
        private FiscalProviderReadinessService $providerReadinessService,
    ) {
    }

    public function forTenant(Tenant $tenant): array
    {
        $checks = [
            $this->tenantRegistrationCheck($tenant),
            $this->operationProfilesCheck($tenant),
            $this->taxRulesCheck($tenant),
            $this->productsCheck($tenant),
            $this->clientsCheck($tenant),
            $this->providerReadinessService->readinessCheck($tenant),
        ];

        $okCount = count(array_filter($checks, fn (array $check) => $check['status'] === 'ok'));
        $scorePercent = (int) round(($okCount / max(count($checks), 1)) * 100);

        return [
            'status' => $okCount === count($checks) ? 'ready' : 'attention',
            'score_percent' => $scorePercent,
            'checks' => $checks,
        ];
    }

    private function tenantRegistrationCheck(Tenant $tenant): array
    {
        $missing = [];
        foreach ([
            'cnpj' => 'CNPJ',
            'ie' => 'Inscrição Estadual',
            'tax_regime' => 'regime tributário',
            'fiscal_environment' => 'ambiente fiscal',
            'ibge_city_code' => 'código IBGE do município',
        ] as $field => $label) {
            if (blank($tenant->{$field})) {
                $missing[] = $label;
            }
        }

        if ($missing === []) {
            return $this->ok('tenant_registration', 'Cadastro fiscal da empresa', 'Os principais dados do emitente já estão preenchidos.');
        }

        return $this->warning('tenant_registration', 'Cadastro fiscal da empresa', 'Ainda faltam dados do emitente: ' . implode(', ', $missing) . '.');
    }

    private function operationProfilesCheck(Tenant $tenant): array
    {
        $count = FiscalOperationProfile::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->count();

        if ($count > 0) {
            return $this->ok('operation_profiles', 'Perfis fiscais', "A empresa já possui {$count} perfil(is) fiscal(is) ativo(s).");
        }

        return $this->warning('operation_profiles', 'Perfis fiscais', 'Cadastre pelo menos um perfil fiscal para definir CFOP e tipo de documento por operação.');
    }

    private function taxRulesCheck(Tenant $tenant): array
    {
        $count = TaxRule::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->count();

        if ($count > 0) {
            return $this->ok('tax_rules', 'Regras tributárias', "A empresa já possui {$count} regra(s) tributária(s) ativa(s).");
        }

        return $this->warning('tax_rules', 'Regras tributárias', 'Cadastre regras tributárias ativas para preparar cálculo e emissão futura.');
    }

    private function productsCheck(Tenant $tenant): array
    {
        $missingCount = Product::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_available', true)
            ->whereNull('deleted_at')
            ->where(function ($query) {
                $query->whereNull('ncm')
                    ->orWhereNull('origin')
                    ->orWhereNull('default_cfop')
                    ->orWhereNull('csosn_cst');
            })
            ->count();

        if ($missingCount === 0) {
            return $this->ok('products', 'Produtos fiscais', 'Os produtos ativos já possuem os principais campos fiscais preenchidos.');
        }

        return $this->warning('products', 'Produtos fiscais', "{$missingCount} produto(s) ativo(s) ainda estão sem NCM, origem, CFOP ou CSOSN/CST.");
    }

    private function clientsCheck(Tenant $tenant): array
    {
        $missingCount = Client::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereNull('cpf_cnpj')
            ->count();

        if ($missingCount === 0) {
            return $this->ok('clients', 'Clientes fiscais', 'Os clientes ativos já possuem CPF/CNPJ informado.');
        }

        return $this->warning('clients', 'Clientes fiscais', "{$missingCount} cliente(s) ativo(s) ainda estão sem CPF/CNPJ.");
    }

    private function ok(string $key, string $label, string $details): array
    {
        return ['key' => $key, 'label' => $label, 'status' => 'ok', 'details' => $details];
    }

    private function warning(string $key, string $label, string $details): array
    {
        return ['key' => $key, 'label' => $label, 'status' => 'warning', 'details' => $details];
    }
}
