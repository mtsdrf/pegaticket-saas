<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Location\Bairro;
use App\Models\Location\Endereco;
use App\Repositories\Contracts\FinalCustomerTenantLinkRepositoryInterface;
use App\Services\APIResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * "Meus endereços" do cliente final (roadmap Loja) — edita os endereços já
 * vinculados via Client.endereco de cada loja onde o cliente comprou (NÃO
 * um book de endereço global novo). Todo acesso é escopado pelos
 * FinalCustomerTenantLink CONFIRMADOS do portal_customer() autenticado:
 * um client_uuid sem vínculo confirmado do cliente responde 404 (guard de
 * posse — route-model-binding não se aplica, é resolução manual por uuid).
 */
class PortalAddressController extends Controller
{
    public function __construct(
        private FinalCustomerTenantLinkRepositoryInterface $linkRepository,
    ) {
    }

    public function index()
    {
        $links = $this->linkRepository->confirmedLinksFor((int) portal_customer()->id);

        $data = $links->map(function ($link) {
            $link->client->loadMissing('endereco.bairro', 'endereco.cidade', 'endereco.estado');

            return [
                'client_uuid' => $link->client->uuid,
                'tenant_name' => $link->tenant->name,
                'tenant_slug' => $link->tenant->slug,
                // Nome/telefone já cadastrados nessa loja (Fase 1 do
                // checkout) — usados pelo checkout público pra pré-preencher
                // o formulário no 2º pedido, além do próprio endereço.
                'client_name' => $link->client->name,
                'client_phone' => $link->client->phone_primary,
                'endereco' => $this->serializeEndereco($link->client->endereco),
            ];
        })->values();

        return APIResponse::success($data, __('messages.portal.addresses_listed'));
    }

    public function update(Request $request, string $clientUuid)
    {
        $data = $request->validate([
            'logradouro' => ['required', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'cep' => ['nullable', 'string', 'max:9'],
            'bairro_uuid' => ['required', 'uuid', Rule::exists('bairros', 'uuid')->whereNull('deleted_at')],
        ]);

        // Guard de posse: o client_uuid tem que pertencer a um vínculo
        // CONFIRMADO do cliente autenticado — 404 genérico caso contrário
        // (nunca revela se o Client existe pra outra loja/cliente).
        $link = $this->linkRepository->confirmedLinksFor((int) portal_customer()->id)
            ->first(fn($l) => $l->client->uuid === $clientUuid);

        if (!$link) {
            abort(404);
        }

        $endereco = $link->client->endereco;

        if (!$endereco) {
            abort(404);
        }

        $bairro = Bairro::where('uuid', $data['bairro_uuid'])
            ->whereNull('deleted_at')
            ->with('cidade')
            ->firstOrFail();

        DB::transaction(function () use ($endereco, $data, $bairro) {
            $endereco->logradouro = $data['logradouro'];
            $endereco->numero = $data['numero'] ?? null;
            $endereco->complemento = $data['complemento'] ?? null;
            $endereco->cep = $data['cep'] ?? null;

            // Deriva cidade/estado do próprio bairro escolhido — mantém a
            // cadeia Estado→Cidade→Bairro consistente sem exigir esses uuids
            // no payload (o cliente só escolhe o bairro).
            $endereco->bairro_id = $bairro->id;
            $endereco->cidade_id = $bairro->cidade_id;
            $endereco->estado_id = $bairro->cidade->estado_id;

            // Coordenada antiga fica errada pro endereço novo — reseta pra
            // re-geocodificação futura (backfill), sem disparar job aqui.
            $endereco->lat = null;
            $endereco->lng = null;
            $endereco->geocode_status = 'pending';

            $endereco->save();
        });

        $endereco->loadMissing('bairro', 'cidade', 'estado');

        return APIResponse::success(
            $this->serializeEndereco($endereco),
            __('messages.portal.address_updated')
        );
    }

    private function serializeEndereco(?Endereco $endereco): ?array
    {
        if (!$endereco) {
            return null;
        }

        return [
            'logradouro' => $endereco->logradouro,
            'numero' => $endereco->numero,
            'complemento' => $endereco->complemento,
            'cep' => $endereco->cep,
            'estado_uuid' => $endereco->estado?->uuid,
            'estado_name' => $endereco->estado?->name,
            'cidade_uuid' => $endereco->cidade?->uuid,
            'cidade_name' => $endereco->cidade?->name,
            'bairro_uuid' => $endereco->bairro?->uuid,
            'bairro_name' => $endereco->bairro?->name,
        ];
    }
}
