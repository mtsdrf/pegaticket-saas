<?php

namespace App\Services\Fiscal;

use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Tenant\Tenant;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class OrderFiscalPreviewService
{
    public function __construct(
        private FiscalOperationProfileMatcher $operationProfileMatcher,
        private TaxRuleMatcher $taxRuleMatcher,
    ) {
    }

    public function preview(Order $order): array
    {
        $order->loadMissing(['client.endereco.estado', 'items.product']);

        /** @var Tenant $tenant */
        $tenant = Tenant::query()->with('endereco.estado')->findOrFail($order->tenant_id);
        $provider = $tenant->fiscal_provider ?: 'manual';

        $context = $this->buildContext($tenant, $order);
        $profile = $this->operationProfileMatcher->matchForTenant($tenant->id, $context);
        $documentType = $profile?->document_type ?? $this->fallbackDocumentType($order);

        $issues = [
            ...$this->tenantIssues($tenant),
            ...$this->contextIssues($tenant, $order, $context, $profile, $documentType),
            ...$this->itemIssues($order, $profile, $documentType),
        ];

        return [
            'status' => $issues === [] ? 'ready' : 'attention',
            'can_prepare' => !collect($issues)->contains(fn (array $issue) => $issue['severity'] === 'error'),
            'provider' => $provider,
            'provider_mode' => $provider === 'manual' ? 'manual' : 'official',
            'official_submission_enabled' => $provider !== 'manual',
            'context' => [
                ...$context,
                'document_type' => $documentType,
            ],
            'operation_profile' => $profile ? [
                'uuid' => $profile->uuid,
                'name' => $profile->name,
                'operation_nature' => $profile->operation_nature,
                'document_type' => $profile->document_type,
                'default_cfop' => $profile->default_cfop,
                'scope' => $profile->scope,
            ] : null,
            'line_items' => $order->items->map(fn (OrderItem $item) => $this->buildLineItemPreview($tenant, $order, $item, $profile, $context, $documentType))->values(),
            'issues' => array_values($issues),
        ];
    }

    private function buildContext(Tenant $tenant, Order $order): array
    {
        return [
            'order_origin' => $order->origin,
            'fulfillment_type' => $order->fulfillment_type ?: 'delivery',
            'destination_type' => $this->inferDestinationType($order),
            'uf_origin' => $tenant->endereco?->estado?->uf,
            'uf_dest' => $order->client?->endereco?->estado?->uf,
        ];
    }

    private function inferDestinationType(Order $order): string
    {
        $document = strtoupper((string) preg_replace('/[^A-Z0-9]/', '', (string) $order->client?->cpf_cnpj));

        if ($document !== '' && strlen($document) > 11) {
            return 'business';
        }

        if (!blank($order->client?->ie)) {
            return 'business';
        }

        return 'consumer_final';
    }

    private function fallbackDocumentType(Order $order): string
    {
        return $order->origin === 'counter' || $order->origin === 'pdv'
            ? 'nfce'
            : 'nfe';
    }

    /**
     * @return array<int, array{key:string,label:string,severity:string,details:string}>
     */
    private function tenantIssues(Tenant $tenant): array
    {
        $issues = [];

        foreach ([
            'cnpj' => 'CNPJ da empresa',
            'tax_regime' => 'regime tributário',
            'ibge_city_code' => 'código IBGE do município',
        ] as $field => $label) {
            if (blank($tenant->{$field})) {
                $issues[] = $this->issue(
                    'tenant_' . $field,
                    'Cadastro fiscal da empresa',
                    'error',
                    "Preencha {$label} antes de preparar a emissão fiscal do pedido."
                );
            }
        }

        return $issues;
    }

    /**
     * @return array<int, array{key:string,label:string,severity:string,details:string}>
     */
    private function contextIssues(Tenant $tenant, Order $order, array $context, mixed $profile, string $documentType): array
    {
        $issues = [];

        if (!$profile) {
            $issues[] = $this->issue(
                'operation_profile',
                'Perfil fiscal',
                'error',
                'Nenhum perfil fiscal ativo atende a origem, entrega e destino deste pedido.'
            );
        }

        if (blank($context['uf_origin'])) {
            $issues[] = $this->issue(
                'uf_origin',
                'Origem da operação',
                'warning',
                'A UF de origem da empresa não pôde ser identificada a partir do endereço cadastrado.'
            );
        }

        if (blank($context['uf_dest'])) {
            $issues[] = $this->issue(
                'uf_dest',
                'Destino da operação',
                'warning',
                'A UF do cliente não pôde ser identificada a partir do endereço cadastrado.'
            );
        }

        if (blank($order->client?->cpf_cnpj)) {
            $issues[] = $this->issue(
                'client_document',
                'Documento do cliente',
                'warning',
                'O cliente ainda está sem CPF/CNPJ. Isso pode bloquear ou limitar a emissão conforme o documento fiscal escolhido.'
            );
        }

        foreach ($this->documentConfigurationIssues($tenant, $documentType) as $issue) {
            $issues[] = $issue;
        }

        return $issues;
    }

    /**
     * @return array<int, array{key:string,label:string,severity:string,details:string}>
     */
    private function documentConfigurationIssues(Tenant $tenant, string $documentType): array
    {
        return match ($documentType) {
            'nfce' => array_values(array_filter([
                blank($tenant->fiscal_nfce_series)
                    ? $this->issue('nfce_series', 'Série fiscal', 'warning', 'Configure a série da NFC-e no cadastro da empresa.')
                    : null,
                blank($tenant->fiscal_nfce_csc_id)
                    ? $this->issue('nfce_csc_id', 'CSC da NFC-e', 'warning', 'Configure o identificador do CSC da NFC-e para a UF aplicável.')
                    : null,
            ])),
            'nfe' => array_values(array_filter([
                blank($tenant->fiscal_nfe_series)
                    ? $this->issue('nfe_series', 'Série fiscal', 'warning', 'Configure a série da NF-e no cadastro da empresa.')
                    : null,
            ])),
            'nfse' => array_values(array_filter([
                blank($tenant->fiscal_nfse_series)
                    ? $this->issue('nfse_series', 'Série fiscal', 'warning', 'Configure a série da NFS-e no cadastro da empresa.')
                    : null,
            ])),
            default => [],
        };
    }

    /**
     * @return array<int, array{key:string,label:string,severity:string,details:string}>
     */
    private function itemIssues(Order $order, mixed $profile, string $documentType): array
    {
        $issues = [];

        foreach ($order->items as $item) {
            $cfop = $profile?->default_cfop ?: $item->product?->default_cfop;

            if (blank($cfop)) {
                $issues[] = $this->issue(
                    'item_cfop_' . $item->uuid,
                    'CFOP do item',
                    'error',
                    "O produto {$item->product?->name} está sem CFOP resolvido (perfil fiscal ou cadastro do produto)."
                );
            }

            if ($documentType === 'nfse') {
                continue;
            }

            foreach ([
                'ncm' => 'NCM',
                'origin' => 'origem',
                'csosn_cst' => 'CSOSN/CST',
            ] as $field => $label) {
                if (blank($item->product?->{$field})) {
                    $issues[] = $this->issue(
                        'item_' . $field . '_' . $item->uuid,
                        'Cadastro fiscal do produto',
                        'error',
                        "O produto {$item->product?->name} está sem {$label}."
                    );
                }
            }
        }

        return $issues;
    }

    private function buildLineItemPreview(
        Tenant $tenant,
        Order $order,
        OrderItem $item,
        mixed $profile,
        array $baseContext,
        string $documentType
    ): array {
        $cfop = $profile?->default_cfop ?: $item->product?->default_cfop;
        $taxTypes = $documentType === 'nfse'
            ? ['iss']
            : ['icms', 'icms_st', 'ipi', 'pis', 'cofins'];

        $rules = collect($taxTypes)
            ->map(function (string $taxType) use ($tenant, $item, $baseContext, $cfop) {
                $matched = $this->taxRuleMatcher
                    ->matchForTenant($tenant->id, [
                        ...$baseContext,
                        'tax_type' => $taxType,
                        'cfop' => $cfop,
                        'ncm' => $item->product?->ncm,
                    ])
                    ->first();

                if (!$matched) {
                    return null;
                }

                return [
                    'uuid' => $matched->uuid,
                    'tax_type' => $matched->tax_type,
                    'rate_percent' => (float) $matched->rate_percent,
                    'scope' => $matched->scope,
                ];
            })
            ->filter()
            ->values();

        return [
            'product_uuid' => $item->product?->uuid,
            'product_name' => $item->product?->name,
            'quantity' => $item->quantity,
            'ncm' => $item->product?->ncm,
            'origin' => $item->product?->origin,
            'csosn_cst' => $item->product?->csosn_cst,
            'resolved_cfop' => $cfop,
            'matched_tax_rules' => $rules,
        ];
    }

    /**
     * @return array{key:string,label:string,severity:string,details:string}
     */
    private function issue(string $key, string $label, string $severity, string $details): array
    {
        return compact('key', 'label', 'severity', 'details');
    }
}
