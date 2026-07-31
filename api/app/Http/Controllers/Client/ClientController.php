<?php

namespace App\Http\Controllers\Client;

use App\DTOs\Client\CreateClientDTO;
use App\DTOs\Client\SyncClientCategoriesDTO;
use App\DTOs\Client\UpdateClientDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ListClientRequest;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\SyncClientCategoriesRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Http\Resources\Client\ClientListResource;
use App\Http\Resources\Client\ClientResource;
use App\Models\Client\Client;
use App\Services\APIResponse;
use App\Services\Client\ClientService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    private const EAGER_RELATIONS = ClientService::EAGER_RELATIONS;

    private const PDF_FILTERS = [
        'q',
        'name',
        'phone_primary',
        'cidade_name',
        'category_uuid',
        'cidade_uuid',
        'bairro_uuid',
        'dia_ideal_uuid',
        'periodo_ideal_uuid',
        'is_trusted',
        'is_active',
    ];

    public function __construct(
        private ClientService $service
    ) {
    }

    public function index(ListClientRequest $request)
    {
        $tenantId = app('tenant_id');
        $validated = $request->validated();

        $filters = collect($validated)->only([
            'q',
            'name',
            'phone_primary',
            'cidade_name',
            'category_uuid',
            'cidade_uuid',
            'bairro_uuid',
            'dia_ideal_uuid',
            'periodo_ideal_uuid',
            'is_trusted',
            'is_active',
        ])->all();

        $list = $this->service->paginate(
            $tenantId,
            $filters,
            (int) ($validated['per_page'] ?? 15),
            $validated['sort_by'] ?? null,
            $validated['sort_dir'] ?? 'asc'
        );

        return APIResponse::success(
            ClientListResource::collection($list),
            __('messages.client.list'),
            200,
            [
                'pagination' => [
                    'current_page' => $list->currentPage(),
                    'per_page' => $list->perPage(),
                    'total' => $list->total(),
                    'last_page' => $list->lastPage(),
                ]
            ]
        );
    }

    public function show(Client $client)
    {
        $client = $this->service->find($client);
        $client->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new ClientResource($client),
            __('messages.client.show')
        );
    }

    public function store(StoreClientRequest $request)
    {
        $dto = CreateClientDTO::fromArray(
            $request->validated(),
            app('tenant_id')
        );

        try {
            $client = $this->service->create($dto);
        } catch (\App\Exceptions\LocationChainException $e) {
            return APIResponse::error($e->getMessage(), 422, 'LOCATION_CHAIN_MISMATCH');
        }

        $client->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new ClientResource($client),
            __('messages.client.created'),
            201
        );
    }

    public function update(UpdateClientRequest $request, Client $client)
    {
        $dto = UpdateClientDTO::fromArray($request->validated());

        try {
            $client = $this->service->update($client, $dto);
        } catch (\App\Exceptions\LocationChainException $e) {
            return APIResponse::error($e->getMessage(), 422, 'LOCATION_CHAIN_MISMATCH');
        }

        $client->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new ClientResource($client),
            __('messages.client.updated')
        );
    }

    public function destroy(Client $client)
    {
        $this->service->delete($client);

        return APIResponse::success(
            null,
            __('messages.client.deleted'),
            204
        );
    }

    public function syncCategories(SyncClientCategoriesRequest $request, Client $client)
    {
        $dto = SyncClientCategoriesDTO::fromArray($request->validated());

        $client = $this->service->syncCategoriesSoft($client, $dto);
        $client->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new ClientResource($client),
            __('messages.client.categories_synced')
        );
    }

    /**
     * Diretório completo de clientes em PDF (cadastro com endereço
     * completo) — mesmos filtros de index(), sem paginação
     * (ClientService::forPdf). Rota separada de reports/clients/pdf, que é
     * o relatório financeiro (clientes em dia), não um export de cadastro.
     */
    public function pdf(Request $request)
    {
        $clients = $this->service->forPdf(
            app('tenant_id'),
            $request->only(self::PDF_FILTERS)
        );

        $pdf = Pdf::loadView('clients.pdf', [
            'clients' => $clients,
            'tenantName' => tenant()?->name,
            'generatedAt' => now(),
        ]);

        return response()->streamDownload(
            fn() => print($pdf->output()),
            'diretorio-clientes-' . now()->format('Ymd_His') . '.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }
}
