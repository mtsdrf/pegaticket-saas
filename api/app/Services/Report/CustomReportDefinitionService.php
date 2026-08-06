<?php

namespace App\Services\Report;

use App\DTOs\Report\CreateCustomReportDefinitionDTO;
use App\DTOs\Report\PreviewCustomReportDTO;
use App\DTOs\Report\UpdateCustomReportDefinitionDTO;
use App\Events\Report\CustomReportDefinitionCreated;
use App\Events\Report\CustomReportDefinitionDeleted;
use App\Events\Report\CustomReportDefinitionUpdated;
use App\Exceptions\ReportExecutionException;
use App\Models\Report\CustomReportDefinition;
use App\Repositories\Contracts\CustomReportDefinitionRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Orquestra o CRUD de definições de relatório personalizado (roadmap 5.6).
 * Toda definição — ao criar, editar ou executar — é revalidada contra
 * App\Support\Report\CustomReportFieldWhitelist via
 * CustomReportQueryBuilder::validateDefinition(), nunca confiada só porque
 * já foi salva antes (a whitelist pode mudar entre o save e o execute).
 */
class CustomReportDefinitionService
{
    public function __construct(
        private readonly CustomReportDefinitionRepositoryInterface $repository,
        private readonly CustomReportQueryBuilder $queryBuilder
    ) {}

    public function paginate(int $tenantId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginateForTenant($tenantId, $perPage);
    }

    public function create(CreateCustomReportDefinitionDTO $dto): CustomReportDefinition
    {
        $this->queryBuilder->validateDefinition(
            $dto->dataSource,
            $dto->dimensions,
            $dto->metrics,
            $dto->calculatedMetrics,
            $dto->filters
        );

        return DB::transaction(function () use ($dto) {
            $definition = $this->repository->create([
                'tenant_id' => $dto->tenantId,
                'name' => $dto->name,
                'data_source' => $dto->dataSource,
                'dimensions' => $dto->dimensions,
                'metrics' => $dto->metrics,
                'calculated_metrics' => $dto->calculatedMetrics,
                'filters' => $dto->filters,
            ]);

            event(new CustomReportDefinitionCreated(
                definitionUuid: $definition->uuid,
                tenantId: $dto->tenantId,
                actorId: (int) Auth::id()
            ));

            return $definition;
        });
    }

    public function update(CustomReportDefinition $definition, UpdateCustomReportDefinitionDTO $dto): CustomReportDefinition
    {
        $this->assertBelongsToCurrentTenant($definition);

        $dataSource = $dto->dataSource ?? $definition->data_source;
        $dimensions = $dto->dimensions ?? $definition->dimensions ?? [];
        $metrics = $dto->metrics ?? $definition->metrics ?? [];
        $calculatedMetrics = $dto->calculatedMetrics ?? $definition->calculated_metrics ?? [];
        $filters = $dto->filters ?? $definition->filters ?? [];

        $this->queryBuilder->validateDefinition($dataSource, $dimensions, $metrics, $calculatedMetrics, $filters);

        return DB::transaction(function () use ($definition, $dto, $dataSource, $dimensions, $metrics, $calculatedMetrics, $filters) {
            $original = $definition->getOriginal();

            $data = array_filter([
                'name' => $dto->name,
                'data_source' => $dataSource,
                'dimensions' => $dimensions,
                'metrics' => $metrics,
                'calculated_metrics' => $calculatedMetrics,
                'filters' => $filters,
            ], fn ($v) => $v !== null);

            $definition = $this->repository->update($definition, $data);

            $changes = array_diff_assoc(
                array_map(fn ($v) => is_array($v) ? json_encode($v) : $v, $definition->getAttributes()),
                array_map(fn ($v) => is_array($v) ? json_encode($v) : $v, $original)
            );

            event(new CustomReportDefinitionUpdated(
                definitionUuid: $definition->uuid,
                tenantId: (int) $definition->tenant_id,
                actorId: (int) Auth::id(),
                changes: array_keys($changes)
            ));

            return $definition;
        });
    }

    public function delete(CustomReportDefinition $definition): void
    {
        $this->assertBelongsToCurrentTenant($definition);

        DB::transaction(function () use ($definition) {
            $tenantId = (int) $definition->tenant_id;
            $uuid = $definition->uuid;

            $this->repository->delete($definition);

            event(new CustomReportDefinitionDeleted(
                definitionUuid: $uuid,
                tenantId: $tenantId,
                actorId: (int) Auth::id()
            ));
        });
    }

    /**
     * Executa uma definição SALVA — tenant_id sempre o do contexto
     * autenticado (`$tenantId`), nunca o gravado na definição por si só
     * (a checagem de posse ainda roda antes, mas quem decide QUAL
     * tenant_id filtra a query é sempre o parâmetro explícito abaixo).
     *
     * @throws ReportExecutionException
     */
    public function execute(CustomReportDefinition $definition, int $tenantId, int $page, int $perPage): LengthAwarePaginator
    {
        $this->assertBelongsToCurrentTenant($definition);

        return $this->queryBuilder->execute(
            $tenantId,
            $definition->data_source,
            $definition->dimensions ?? [],
            $definition->metrics ?? [],
            $definition->calculated_metrics ?? [],
            $definition->filters ?? [],
            $page,
            $perPage
        );
    }

    /**
     * Pré-visualização ad-hoc (não salva).
     *
     * @throws ReportExecutionException
     */
    public function preview(int $tenantId, PreviewCustomReportDTO $dto): LengthAwarePaginator
    {
        return $this->queryBuilder->execute(
            $tenantId,
            $dto->dataSource,
            $dto->dimensions,
            $dto->metrics,
            $dto->calculatedMetrics,
            $dto->filters,
            $dto->page,
            $dto->perPage
        );
    }

    /**
     * Route-model-binding resolve só por uuid, sem escopo de tenant — sem
     * esta checagem, um usuário com permissão poderia ler/mutar/executar
     * definição de outro tenant só sabendo o uuid (IDOR). Mesmo padrão de
     * App\Services\Tenant\TenantRoleService::assertBelongsToCurrentTenant.
     */
    private function assertBelongsToCurrentTenant(CustomReportDefinition $definition): void
    {
        if ((int) $definition->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }
}
