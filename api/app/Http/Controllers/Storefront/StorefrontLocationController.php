<?php

namespace App\Http\Controllers\Storefront;

use App\Exceptions\CepNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Location\ReverseGeocodeRequest;
use App\Http\Resources\Location\BairroResource;
use App\Http\Resources\Location\CidadeResource;
use App\Http\Resources\Location\EstadoResource;
use App\Services\APIResponse;
use App\Services\Location\BairroService;
use App\Services\Location\CepLookupService;
use App\Services\Location\CidadeService;
use App\Services\Location\EstadoService;
use App\Services\Location\ReverseGeocodeService;
use Illuminate\Http\Request;

/**
 * Descoberta feita durante a implementação do frontend do Delivery Fase 1
 * (2026-07-16): `estados`/`cidades`/`bairros` (Estado/Cidade/Bairro) só
 * existem sob o guard de staff (`middleware(['jwt']) + tenant + perm`,
 * ver routes/api.php). O checkout da loja pública autentica o cliente
 * final via `customer.jwt` (App\Http\Middleware\CustomerJwtAccessMiddleware),
 * um guard DIFERENTE que `JwtAccessMiddleware` rejeita explicitamente
 * (`checkSubjectModel(User::class)`) — logo o formulário de endereço do
 * checkout (que reaproveita `ClientAddressFields` no frontend) não tinha
 * NENHUM endpoint acessível para popular a cascata Estado→Cidade→Bairro.
 *
 * Endpoints somente leitura, públicos (sem jwt/tenant/perm, mesmo espírito
 * de GET /loja/{slug}), reaproveitando os Services/Resources já existentes
 * (dado não sensível, compartilhado entre tenants, já 100% visível a
 * qualquer usuário staff autenticado). Adição aditiva — não altera nenhuma
 * rota/comportamento existente.
 */
class StorefrontLocationController extends Controller
{
    public function __construct(
        private EstadoService $estadoService,
        private CidadeService $cidadeService,
        private BairroService $bairroService
    ) {
    }

    public function estados()
    {
        $list = $this->estadoService->paginate(500, ['is_active' => true], 'name', 'asc');

        return APIResponse::success(
            EstadoResource::collection($list),
            __('messages.estado.list')
        );
    }

    public function cidades(Request $request)
    {
        $request->validate(['estado_uuid' => ['required', 'uuid']]);

        $list = $this->cidadeService->paginate(
            perPage: 500,
            estadoUuid: $request->string('estado_uuid')->toString(),
            filters: ['is_active' => true],
            sortBy: 'name',
            sortDir: 'asc'
        );

        return APIResponse::success(
            CidadeResource::collection($list),
            __('messages.cidade.list')
        );
    }

    public function bairros(Request $request)
    {
        $request->validate(['cidade_uuid' => ['required', 'uuid']]);

        $list = $this->bairroService->paginate(
            perPage: 500,
            cidadeUuid: $request->string('cidade_uuid')->toString(),
            filters: ['is_active' => true],
            sortBy: 'name',
            sortDir: 'asc'
        );

        return APIResponse::success(
            BairroResource::collection($list),
            __('messages.bairro.list')
        );
    }

    /**
     * Consulta de CEP (ViaCEP) pro fluxo "digitar o CEP" do endereço do
     * checkout — público, mesmo espírito das rotas acima.
     */
    public function cep(string $cep, CepLookupService $service)
    {
        try {
            $result = $service->lookup($cep);
        } catch (CepNotFoundException $e) {
            return APIResponse::error($e->getMessage(), 404, 'CEP_NOT_FOUND');
        }

        return APIResponse::success($result, __('messages.location.cep_found'));
    }

    /**
     * Reverse geocode público — mesmo `ReverseGeocodeService` já usado
     * pela rota staff (`LocationController::reverseGeocode`), só uma rota
     * nova sem guard `jwt`. O checkout público autentica o cliente final
     * via `customer.jwt` (guard diferente, rejeitado por `jwt` de staff) —
     * sem esta rota, o botão "usar minha localização" não tinha endpoint
     * acessível no checkout.
     */
    public function reverseGeocode(ReverseGeocodeRequest $request, ReverseGeocodeService $service)
    {
        $validated = $request->validated();

        $result = $service->reverse((float) $validated['lat'], (float) $validated['lng']);

        return APIResponse::success($result, __('messages.location.reverse_geocoded'));
    }
}
