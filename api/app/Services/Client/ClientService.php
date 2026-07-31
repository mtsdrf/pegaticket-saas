<?php

namespace App\Services\Client;

use App\DTOs\Client\CreateClientDTO;
use App\DTOs\Client\UpdateClientDTO;
use App\DTOs\Client\SyncClientCategoriesDTO;
use App\DTOs\Location\CreateEnderecoDTO;
use App\DTOs\Location\UpdateEnderecoDTO;
use App\Events\Client\ClientCreated;
use App\Events\Client\ClientUpdated;
use App\Events\Client\ClientDeleted;
use App\Events\Client\ClientCategoriesSynced;
use App\Models\Client\Client;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Services\Location\EnderecoService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClientService
{
    public function __construct(
        private ClientRepositoryInterface $repository,
        private EnderecoService $enderecoService,
    ) {
    }

    public function find(Client $client): Client
    {
        $this->assertBelongsToCurrentTenant($client);

        return $client;
    }

    public const EAGER_RELATIONS = ['endereco.estado', 'endereco.cidade', 'endereco.bairro', 'categories'];
    public const LIST_EAGER_RELATIONS = ['endereco.cidade'];

    /**
     * Whitelist de sort_by aceito pelo grid (ag-Grid Infinite Row Model) —
     * mapeia o nome público da coluna para a expressão SQL qualificada.
     * cidade_name exige leftJoin (endereco é belongsTo 1:1 por cliente,
     * cidade é belongsTo 1:1 por endereco — leftJoin não duplica linhas).
     */
    private const SORTABLE = [
        'name' => 'clients.name',
        'phone_primary' => 'clients.phone_primary',
        'cidade_name' => 'cidades.name',
        'is_active' => 'clients.is_active',
    ];

    public function paginate(
        int $tenantId,
        array $filters = [],
        int $perPage = 15,
        ?string $sortBy = null,
        string $sortDir = 'asc'
    ): LengthAwarePaginator {
        $sortColumn = is_string($sortBy) ? (self::SORTABLE[$sortBy] ?? null) : null;
        $needsCidadeJoin = $sortColumn === 'cidades.name' || !empty($filters['cidade_name']);

        $query = Client::query()
            ->select([
                'clients.id',
                'clients.uuid',
                'clients.endereco_id',
                'clients.name',
                'clients.phone_primary',
                'clients.is_active',
                'clients.created_at',
            ])
            ->where('clients.tenant_id', $tenantId)
            ->whereNull('clients.deleted_at')
            ->with(self::LIST_EAGER_RELATIONS);

        if ($needsCidadeJoin) {
            $query->leftJoin('enderecos', 'enderecos.id', '=', 'clients.endereco_id')
                ->leftJoin('cidades', 'cidades.id', '=', 'enderecos.cidade_id');
        }

        if (!empty($filters['name'])) {
            $query->where('clients.name', 'like', '%' . $filters['name'] . '%');
        }

        if (!empty($filters['phone_primary'])) {
            $query->where('clients.phone_primary', 'like', '%' . $filters['phone_primary'] . '%');
        }

        if (!empty($filters['cidade_name'])) {
            $query->where('cidades.name', 'like', '%' . $filters['cidade_name'] . '%');
        }

        if (array_key_exists('is_trusted', $filters) && $filters['is_trusted'] !== null) {
            $query->where('clients.is_trusted', filter_var($filters['is_trusted'], FILTER_VALIDATE_BOOLEAN));
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('clients.is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['category_uuid'])) {
            $query->whereHas('categories', fn($q) => $q->where('uuid', $filters['category_uuid']));
        }

        if (!empty($filters['cidade_uuid'])) {
            $query->whereHas('endereco.cidade', fn($q) => $q->where('uuid', $filters['cidade_uuid']));
        }

        if (!empty($filters['bairro_uuid'])) {
            $query->whereHas('endereco.bairro', fn($q) => $q->where('uuid', $filters['bairro_uuid']));
        }

        // Busca global (campo único acima do grid) — OR entre as colunas
        // buscáveis, agrupado num closure próprio para não virar AND com os
        // filtros por coluna acima nem com o tenant scoping/soft-delete já
        // aplicados fora deste grupo. Independente do leftJoin de cidade
        // usado pelo sort_by/filtro cidade_name — usa whereHas, que gera
        // subquery própria e não exige o join estar presente.
        if (!empty($filters['q'])) {
            $term = $filters['q'];
            $query->where(function ($sub) use ($term) {
                $sub->where('clients.name', 'like', '%' . $term . '%')
                    ->orWhere('clients.phone_primary', 'like', '%' . $term . '%')
                    ->orWhereHas('endereco.cidade', fn($r) => $r->where('name', 'like', '%' . $term . '%'));
            });
        }

        $dir = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

        if ($sortColumn) {
            $query->orderBy($sortColumn, $dir);
        } else {
            $query->orderBy('clients.name');
        }

        return $query->paginate($perPage);
    }

    /**
     * Diretório completo (sem paginação) pro PDF de cadastro — eager load
     * completo (self::EAGER_RELATIONS: endereço com estado/cidade/bairro +
     * categorias), diferente do select restrito + LIST_EAGER_RELATIONS de
     * paginate() (otimizado pro grid). O export precisa do endereço inteiro.
     */
    public function forPdf(int $tenantId, array $filters = []): \Illuminate\Support\Collection
    {
        $query = Client::query()
            ->where('clients.tenant_id', $tenantId)
            ->whereNull('clients.deleted_at')
            ->with(self::EAGER_RELATIONS);

        if (!empty($filters['name'])) {
            $query->where('clients.name', 'like', '%' . $filters['name'] . '%');
        }

        if (!empty($filters['phone_primary'])) {
            $query->where('clients.phone_primary', 'like', '%' . $filters['phone_primary'] . '%');
        }

        if (!empty($filters['cidade_name'])) {
            $query->whereHas('endereco.cidade', fn($q) => $q->where('name', 'like', '%' . $filters['cidade_name'] . '%'));
        }

        if (array_key_exists('is_trusted', $filters) && $filters['is_trusted'] !== null) {
            $query->where('clients.is_trusted', filter_var($filters['is_trusted'], FILTER_VALIDATE_BOOLEAN));
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('clients.is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['category_uuid'])) {
            $query->whereHas('categories', fn($q) => $q->where('uuid', $filters['category_uuid']));
        }

        if (!empty($filters['cidade_uuid'])) {
            $query->whereHas('endereco.cidade', fn($q) => $q->where('uuid', $filters['cidade_uuid']));
        }

        if (!empty($filters['bairro_uuid'])) {
            $query->whereHas('endereco.bairro', fn($q) => $q->where('uuid', $filters['bairro_uuid']));
        }

        if (!empty($filters['q'])) {
            $term = $filters['q'];
            $query->where(function ($sub) use ($term) {
                $sub->where('clients.name', 'like', '%' . $term . '%')
                    ->orWhere('clients.phone_primary', 'like', '%' . $term . '%')
                    ->orWhereHas('endereco.cidade', fn($r) => $r->where('name', 'like', '%' . $term . '%'));
            });
        }

        return $query->orderBy('clients.name')->get();
    }

    public function create(CreateClientDTO $dto): Client
    {
        return DB::transaction(function () use ($dto) {

            $endereco = $this->enderecoService->create(new CreateEnderecoDTO(
                tenantId: $dto->tenantId,
                estadoUuid: $dto->estadoUuid,
                cidadeUuid: $dto->cidadeUuid,
                bairroUuid: $dto->bairroUuid,
                logradouro: $dto->logradouro,
                numero: $dto->numero,
                complemento: $dto->complemento,
                cep: $dto->cep,
                isActive: true,
                lat: $dto->lat,
                lng: $dto->lng,
            ));

            $client = $this->repository->create([
                'tenant_id' => $dto->tenantId,
                'endereco_id' => $endereco->id,
                'name' => $dto->name,
                'last_name' => $dto->lastName,
                'cpf_cnpj' => $dto->cpfCnpj,
                'ie' => $dto->ie,
                'ie_indicator' => $dto->ieIndicator,
                'phone_primary' => $dto->phonePrimary,
                'phone_secondary' => $dto->phoneSecondary,
                'notes' => $dto->notes,
                'is_trusted' => $dto->isTrusted,
                'is_active' => $dto->isActive,
            ]);

            event(new ClientCreated(
                clientUuid: $client->uuid,
                actorId: Auth::id()
            ));

            return $client;
        });
    }

    public function update(Client $client, UpdateClientDTO $dto): Client
    {
        $this->assertBelongsToCurrentTenant($client);

        return DB::transaction(function () use ($client, $dto) {

            $original = $client->getOriginal();

            // Só delega para EnderecoService::update quando o request realmente
            // traz algum campo de endereço — update parcial de outro campo
            // (ex: telefone) não deve mexer no endereço vinculado.
            if ($dto->hasEnderecoChanges()) {
                $this->enderecoService->update($client->endereco, new UpdateEnderecoDTO(
                    estadoUuid: $dto->estadoUuid,
                    cidadeUuid: $dto->cidadeUuid,
                    bairroUuid: $dto->bairroUuid,
                    logradouro: $dto->logradouro,
                    numero: $dto->numero,
                    complemento: $dto->complemento,
                    cep: $dto->cep,
                    isActive: null,
                    lat: $dto->lat,
                    lng: $dto->lng,
                ));
            }

            $data = array_filter([
                'name' => $dto->name,
                'cpf_cnpj' => $dto->cpfCnpj,
                'ie' => $dto->ie,
                'ie_indicator' => $dto->ieIndicator,
                'phone_primary' => $dto->phonePrimary,
                'phone_secondary' => $dto->phoneSecondary,
                'notes' => $dto->notes,
                'is_trusted' => $dto->isTrusted,
                'is_active' => $dto->isActive,
            ], fn($v) => !is_null($v));

            if (!empty($data)) {
                $client = $this->repository->update($client, $data);
            }

            $client->refresh();

            $changes = array_diff_assoc($client->getAttributes(), $original);

            if (!empty($changes)) {
                event(new ClientUpdated(
                    clientUuid: $client->uuid,
                    actorId: Auth::id(),
                    changes: array_keys($changes)
                ));
            }

            return $client;
        });
    }

    public function delete(Client $client): void
    {
        $this->assertBelongsToCurrentTenant($client);

        DB::transaction(function () use ($client) {
            $this->repository->delete($client);

            event(new ClientDeleted(
                clientUuid: $client->uuid,
                actorId: Auth::id()
            ));
        });
    }

    public function syncCategoriesSoft(Client $client, SyncClientCategoriesDTO $dto): Client
    {
        $this->assertBelongsToCurrentTenant($client);

        return DB::transaction(function () use ($client, $dto) {
            $this->repository->syncCategories($client, $dto->categoryUuids);

            event(new ClientCategoriesSynced(
                clientUuid: $client->uuid,
                categoryUuids: $dto->categoryUuids,
                actorId: Auth::id()
            ));

            return $client->refresh();
        });
    }

    /**
     * Route-model-binding resolve só por uuid, sem escopo de tenant — sem
     * esta checagem, um usuário com permissão poderia mutar registro de
     * outro tenant só sabendo o uuid (IDOR).
     */
    private function assertBelongsToCurrentTenant(Client $client): void
    {
        if ((int) $client->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }
}
