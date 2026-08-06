<?php

namespace App\Http\Controllers\Report;

use App\DTOs\Report\CreateCustomReportDefinitionDTO;
use App\DTOs\Report\PreviewCustomReportDTO;
use App\DTOs\Report\UpdateCustomReportDefinitionDTO;
use App\Exceptions\InvalidReportDefinitionException;
use App\Exceptions\InvalidReportFormulaException;
use App\Exceptions\ReportExecutionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\PreviewCustomReportRequest;
use App\Http\Requests\Report\StoreCustomReportDefinitionRequest;
use App\Http\Requests\Report\UpdateCustomReportDefinitionRequest;
use App\Http\Resources\Report\CustomReportDefinitionResource;
use App\Models\Report\CustomReportDefinition;
use App\Services\APIResponse;
use App\Services\Report\CustomReportDefinitionService;
use App\Support\Report\CustomReportFieldWhitelist;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomReportDefinitionController extends Controller
{
    public function __construct(private readonly CustomReportDefinitionService $service) {}

    /**
     * Fontes de dados/dimensões/métricas disponíveis (a mesma whitelist que
     * a query builder usa) — pro frontend montar os seletores do
     * construtor sem hardcodar nada em duplicado.
     */
    public function schema()
    {
        $schema = [];

        foreach (CustomReportFieldWhitelist::dataSources() as $dataSource) {
            $schema[] = [
                'data_source' => $dataSource,
                'dimensions' => $this->describeFields(CustomReportFieldWhitelist::dimensions($dataSource)),
                'metrics' => $this->describeFields(CustomReportFieldWhitelist::metrics($dataSource)),
            ];
        }

        return APIResponse::success($schema, __('messages.custom_report.schema'));
    }

    private function describeFields(array $fields): array
    {
        $result = [];

        foreach ($fields as $key => $field) {
            $result[] = ['key' => $key, 'label' => $field['label']];
        }

        return $result;
    }

    public function index(Request $request)
    {
        $definitions = $this->service->paginate((int) app('tenant_id'), (int) $request->get('per_page', 15));

        return APIResponse::success(
            CustomReportDefinitionResource::collection($definitions),
            __('messages.custom_report.list'),
            200,
            [
                'pagination' => [
                    'current_page' => $definitions->currentPage(),
                    'per_page' => $definitions->perPage(),
                    'total' => $definitions->total(),
                    'last_page' => $definitions->lastPage(),
                ],
            ]
        );
    }

    public function show(CustomReportDefinition $customReportDefinition)
    {
        if ((int) $customReportDefinition->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }

        return APIResponse::success(
            new CustomReportDefinitionResource($customReportDefinition),
            __('messages.custom_report.show')
        );
    }

    public function store(StoreCustomReportDefinitionRequest $request)
    {
        $dto = CreateCustomReportDefinitionDTO::fromArray($request->validated(), (int) app('tenant_id'));

        try {
            $definition = $this->service->create($dto);
        } catch (InvalidReportDefinitionException|InvalidReportFormulaException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_REPORT_DEFINITION');
        }

        return APIResponse::success(
            new CustomReportDefinitionResource($definition),
            __('messages.custom_report.created'),
            201
        );
    }

    public function update(UpdateCustomReportDefinitionRequest $request, CustomReportDefinition $customReportDefinition)
    {
        $dto = UpdateCustomReportDefinitionDTO::fromArray($request->validated());

        try {
            $definition = $this->service->update($customReportDefinition, $dto);
        } catch (InvalidReportDefinitionException|InvalidReportFormulaException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_REPORT_DEFINITION');
        }

        return APIResponse::success(
            new CustomReportDefinitionResource($definition),
            __('messages.custom_report.updated')
        );
    }

    public function destroy(CustomReportDefinition $customReportDefinition)
    {
        $this->service->delete($customReportDefinition);

        return APIResponse::success(null, __('messages.custom_report.deleted'), 204);
    }

    public function execute(Request $request, CustomReportDefinition $customReportDefinition)
    {
        $page = max(1, (int) $request->get('page', 1));
        $perPage = (int) $request->get('per_page', 20);

        try {
            $result = $this->service->execute($customReportDefinition, (int) app('tenant_id'), $page, $perPage);
        } catch (InvalidReportDefinitionException|InvalidReportFormulaException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_REPORT_DEFINITION');
        } catch (ReportExecutionException $e) {
            return APIResponse::error(__('messages.custom_report.execution_failed'), 500, 'REPORT_EXECUTION_FAILED');
        }

        return $this->paginatedResponse($result);
    }

    public function preview(PreviewCustomReportRequest $request)
    {
        $dto = PreviewCustomReportDTO::fromArray($request->validated());

        try {
            $result = $this->service->preview((int) app('tenant_id'), $dto);
        } catch (InvalidReportDefinitionException|InvalidReportFormulaException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_REPORT_DEFINITION');
        } catch (ReportExecutionException $e) {
            return APIResponse::error(__('messages.custom_report.execution_failed'), 500, 'REPORT_EXECUTION_FAILED');
        }

        return $this->paginatedResponse($result);
    }

    private function paginatedResponse(LengthAwarePaginator $result)
    {
        return APIResponse::success(
            $result->items(),
            __('messages.custom_report.executed'),
            200,
            [
                'pagination' => [
                    'current_page' => $result->currentPage(),
                    'per_page' => $result->perPage(),
                    'total' => $result->total(),
                    'last_page' => $result->lastPage(),
                ],
            ]
        );
    }
}
