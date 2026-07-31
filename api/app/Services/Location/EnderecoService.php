<?php

namespace App\Services\Location;

use App\DTOs\Location\CreateEnderecoDTO;
use App\DTOs\Location\UpdateEnderecoDTO;
use App\Events\Location\EnderecoCreated;
use App\Events\Location\EnderecoUpdated;
use App\Events\Location\EnderecoDeleted;
use App\Exceptions\LocationChainException;
use App\Jobs\Location\GeocodeEnderecoJob;
use App\Models\Location\Bairro;
use App\Models\Location\Cidade;
use App\Models\Location\Endereco;
use App\Models\Location\Estado;
use App\Repositories\Contracts\EnderecoRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EnderecoService
{
    public function __construct(
        private EnderecoRepositoryInterface $repository
    ) {
    }

    /**
     * Whitelist de sort_by aceito pelo grid — cidade_name/bairro_name exigem
     * leftJoin (endereco belongsTo cidade/bairro diretamente via
     * cidade_id/bairro_id, 1:1, não duplica linhas). select('enderecos.*')
     * evita ambiguidade de coluna ("name"/"is_active" existem nas tabelas
     * relacionadas também).
     */
    private const SORTABLE = [
        'logradouro' => 'enderecos.logradouro',
        'cep' => 'enderecos.cep',
        'cidade_name' => 'cidades.name',
        'bairro_name' => 'bairros.name',
        'is_active' => 'enderecos.is_active',
    ];

    /**
     * Campos que afetam o texto de busca do Nominatim — só um update que
     * mude algum deles justifica regeocodificar (ver GeocodeEnderecoJob).
     */
    private const GEOCODE_RELEVANT_FIELDS = [
        'logradouro',
        'numero',
        'cep',
        'estado_id',
        'cidade_id',
        'bairro_id',
    ];

    public function paginate(
        int $tenantId,
        int $perPage = 15,
        array $filters = [],
        ?string $sortBy = null,
        string $sortDir = 'asc'
    ): LengthAwarePaginator {
        $sortColumn = self::SORTABLE[$sortBy] ?? null;
        $needsCidadeJoin = $sortColumn === 'cidades.name' || !empty($filters['cidade_name']);
        $needsBairroJoin = $sortColumn === 'bairros.name' || !empty($filters['bairro_name']);

        $query = Endereco::query()
            ->select('enderecos.*')
            ->where('enderecos.tenant_id', $tenantId)
            ->whereNull('enderecos.deleted_at')
            ->with(['estado', 'cidade', 'bairro']);

        if ($needsCidadeJoin) {
            $query->leftJoin('cidades', 'cidades.id', '=', 'enderecos.cidade_id');
        }

        if ($needsBairroJoin) {
            $query->leftJoin('bairros', 'bairros.id', '=', 'enderecos.bairro_id');
        }

        if (!empty($filters['logradouro'])) {
            $query->where('enderecos.logradouro', 'like', '%' . $filters['logradouro'] . '%');
        }

        if (!empty($filters['cep'])) {
            $query->where('enderecos.cep', 'like', '%' . $filters['cep'] . '%');
        }

        if (!empty($filters['cidade_name'])) {
            $query->where('cidades.name', 'like', '%' . $filters['cidade_name'] . '%');
        }

        if (!empty($filters['bairro_name'])) {
            $query->where('bairros.name', 'like', '%' . $filters['bairro_name'] . '%');
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('enderecos.is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $dir = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

        if ($sortColumn) {
            $query->orderBy($sortColumn, $dir);
        } else {
            $query->orderBy('enderecos.logradouro');
        }

        return $query->paginate($perPage);
    }

    public function create(CreateEnderecoDTO $dto): Endereco
    {
        return DB::transaction(function () use ($dto) {

            $estado = Estado::where('uuid', $dto->estadoUuid)->whereNull('deleted_at')->firstOrFail();
            $cidade = Cidade::where('uuid', $dto->cidadeUuid)->whereNull('deleted_at')->firstOrFail();
            $bairro = Bairro::where('uuid', $dto->bairroUuid)->whereNull('deleted_at')->firstOrFail();

            $this->assertConsistentChain($estado, $cidade, $bairro);

            // lat/lng manual (ambos preenchidos) bypassa o Nominatim — se só
            // um dos dois vier, ignora ambos silenciosamente (não é erro de
            // validação, é a request não trazer a informação completa).
            $hasManualCoords = $dto->lat !== null && $dto->lng !== null;

            $endereco = $this->repository->create(array_merge([
                'tenant_id' => $dto->tenantId,
                'estado_id' => $estado->id,
                'cidade_id' => $cidade->id,
                'bairro_id' => $bairro->id,
                'logradouro' => $dto->logradouro,
                'numero' => $dto->numero,
                'complemento' => $dto->complemento,
                'cep' => $dto->cep,
                'is_active' => $dto->isActive,
            ], $hasManualCoords ? [
                'lat' => $dto->lat,
                'lng' => $dto->lng,
                'geocode_status' => 'manual',
                'geocoded_at' => now(),
            ] : []));

            event(new EnderecoCreated(
                enderecoUuid: $endereco->uuid,
                actorId: Auth::id()
            ));

            if (!$hasManualCoords) {
                GeocodeEnderecoJob::dispatch($endereco->id)->afterCommit();
            }

            return $endereco;
        });
    }

    public function update(Endereco $endereco, UpdateEnderecoDTO $dto): Endereco
    {
        $this->assertBelongsToCurrentTenant($endereco);

        return DB::transaction(function () use ($endereco, $dto) {

            $original = $endereco->getOriginal();

            $estadoId = null;
            $cidadeId = null;
            $bairroId = null;

            // Só resolve/valida a cadeia geográfica quando o request realmente
            // muda algum nível dela — evita 3 queries e um 404 enganoso (via
            // findOrFail respeitando soft-delete) num update que só muda
            // logradouro/cep/is_active. Ver .claude/memory/architecture-decisions.md.
            if ($dto->estadoUuid || $dto->cidadeUuid || $dto->bairroUuid) {
                $estado = $dto->estadoUuid
                    ? Estado::where('uuid', $dto->estadoUuid)->whereNull('deleted_at')->firstOrFail()
                    : Estado::withTrashed()->findOrFail($endereco->estado_id);

                $cidade = $dto->cidadeUuid
                    ? Cidade::where('uuid', $dto->cidadeUuid)->whereNull('deleted_at')->firstOrFail()
                    : Cidade::withTrashed()->findOrFail($endereco->cidade_id);

                $bairro = $dto->bairroUuid
                    ? Bairro::where('uuid', $dto->bairroUuid)->whereNull('deleted_at')->firstOrFail()
                    : Bairro::withTrashed()->findOrFail($endereco->bairro_id);

                $this->assertConsistentChain($estado, $cidade, $bairro);

                $estadoId = $estado->id;
                $cidadeId = $cidade->id;
                $bairroId = $bairro->id;
            }

            $data = array_filter([
                'logradouro' => $dto->logradouro,
                'numero' => $dto->numero,
                'complemento' => $dto->complemento,
                'cep' => $dto->cep,
                'is_active' => $dto->isActive,
                'estado_id' => $estadoId,
                'cidade_id' => $cidadeId,
                'bairro_id' => $bairroId,
            ], fn($v) => !is_null($v));

            // lat/lng manual (ambos preenchidos) bypassa o Nominatim e tem
            // prioridade sobre o texto NESSA chamada — mesmo que campos
            // relevantes também tenham mudado, não regeocodifica.
            $hasManualCoords = $dto->lat !== null && $dto->lng !== null;

            // Regeocodificar só se algum campo que afeta o texto de busca do
            // Nominatim realmente mudar de valor (não só "veio no request").
            $needsGeocode = false;

            if (!$hasManualCoords) {
                foreach (self::GEOCODE_RELEVANT_FIELDS as $field) {
                    if (array_key_exists($field, $data) && (string) $data[$field] !== (string) ($original[$field] ?? null)) {
                        $needsGeocode = true;
                        break;
                    }
                }
            }

            if ($needsGeocode) {
                $data['geocode_status'] = 'pending';
                $data['lat'] = null;
                $data['lng'] = null;
            } elseif ($hasManualCoords) {
                $data['lat'] = $dto->lat;
                $data['lng'] = $dto->lng;
                $data['geocode_status'] = 'manual';
                $data['geocoded_at'] = now();
            }

            if (!empty($data)) {
                $endereco = $this->repository->update($endereco, $data);

                $changes = array_diff_assoc($endereco->getAttributes(), $original);

                event(new EnderecoUpdated(
                    enderecoUuid: $endereco->uuid,
                    actorId: Auth::id(),
                    changes: array_keys($changes)
                ));

                if ($needsGeocode) {
                    GeocodeEnderecoJob::dispatch($endereco->id)->afterCommit();
                }
            }

            return $endereco;
        });
    }

    public function delete(Endereco $endereco): void
    {
        $this->assertBelongsToCurrentTenant($endereco);

        DB::transaction(function () use ($endereco) {
            $this->repository->delete($endereco);

            event(new EnderecoDeleted(
                enderecoUuid: $endereco->uuid,
                actorId: Auth::id()
            ));
        });
    }

    /**
     * Cadeia Estado→Cidade→Bairro precisa ser consistente: a cidade tem que
     * pertencer ao estado informado, e o bairro tem que pertencer à cidade
     * informada. Sem isso, um endereço poderia referenciar um bairro de
     * uma cidade/estado diferente do informado.
     */
    private function assertConsistentChain(Estado $estado, Cidade $cidade, Bairro $bairro): void
    {
        if ((int) $cidade->estado_id !== (int) $estado->id) {
            throw new LocationChainException(__('messages.endereco.invalid_chain'));
        }

        if ((int) $bairro->cidade_id !== (int) $cidade->id) {
            throw new LocationChainException(__('messages.endereco.invalid_chain'));
        }
    }

    /**
     * Route-model-binding resolve só por uuid, sem escopo de tenant — sem
     * esta checagem, um usuário com permissão poderia mutar registro de
     * outro tenant só sabendo o uuid (IDOR).
     */
    private function assertBelongsToCurrentTenant(Endereco $endereco): void
    {
        if ((int) $endereco->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }
}
