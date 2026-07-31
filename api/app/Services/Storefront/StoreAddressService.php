<?php

namespace App\Services\Storefront;

use App\DTOs\Location\CreateEnderecoDTO;
use App\DTOs\Location\UpdateEnderecoDTO;
use App\Models\Location\Endereco;
use App\Models\Tenant\Tenant;
use App\Services\Location\EnderecoService;
use Illuminate\Support\Facades\DB;

/**
 * Endereço próprio da empresa (loja pública) — reaproveita o EnderecoService
 * genérico (mesmas regras de validação de cadeia + geocoding automático) e
 * apenas orquestra o vínculo tenants.endereco_id: cria + associa na primeira
 * vez, atualiza o existente nas seguintes.
 */
class StoreAddressService
{
    public function __construct(
        private EnderecoService $enderecoService
    ) {
    }

    public function getForTenant(int $tenantId): ?Endereco
    {
        $tenant = Tenant::findOrFail($tenantId);

        if (!$tenant->endereco_id) {
            return null;
        }

        return Endereco::with(['estado', 'cidade', 'bairro'])->find($tenant->endereco_id);
    }

    /**
     * Cria o endereço e associa a tenants.endereco_id na primeira vez; nas
     * seguintes, atualiza o endereço já vinculado. EnderecoService cuida do
     * geocoding (dispatch do GeocodeEnderecoJob) e da auditoria de Endereco.
     */
    public function updateForTenant(int $tenantId, array $validated): Endereco
    {
        return DB::transaction(function () use ($tenantId, $validated) {
            $tenant = Tenant::findOrFail($tenantId);

            if ($tenant->endereco_id) {
                $endereco = Endereco::findOrFail($tenant->endereco_id);
                $endereco = $this->enderecoService->update($endereco, UpdateEnderecoDTO::fromArray($validated));
            } else {
                $endereco = $this->enderecoService->create(CreateEnderecoDTO::fromArray($validated, $tenantId));

                $tenant->endereco_id = $endereco->id;
                $tenant->save();
            }

            return $endereco->load(['estado', 'cidade', 'bairro']);
        });
    }
}
