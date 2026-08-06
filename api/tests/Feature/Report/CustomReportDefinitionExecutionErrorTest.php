<?php

namespace Tests\Feature\Report;

use App\Exceptions\ReportExecutionException;
use App\Services\Report\CustomReportQueryBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

/**
 * Requisito de segurança #5: erro de banco cru nunca é exposto ao usuário.
 * Força uma falha real na camada de banco (DB::table lança) e confere que
 * só uma exceção genérica sobe, com log interno separado.
 */
class CustomReportDefinitionExecutionErrorTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('custom-report-error@test.com');
        $this->grantPermission('custom_reports', 'read');
    }

    #[Test]
    public function db_failure_is_wrapped_in_generic_exception_and_logged(): void
    {
        Log::shouldReceive('error')->once()->with('custom_report.execution_failed', \Mockery::type('array'));

        DB::shouldReceive('table')
            ->once()
            ->andThrow(new \RuntimeException('SQLSTATE[42S02]: connection string user=root password=SECRET123 leaked'));

        $builder = app(CustomReportQueryBuilder::class);

        try {
            $builder->execute(
                tenantId: (int) $this->tenant->id,
                dataSource: 'sales',
                dimensions: [],
                metrics: ['total_revenue'],
                calculatedMetrics: [],
                filters: []
            );
            $this->fail('Esperava ReportExecutionException.');
        } catch (ReportExecutionException $e) {
            $this->assertSame('Falha ao executar o relatório.', $e->getMessage());
            $this->assertStringNotContainsString('SECRET123', $e->getMessage());
            $this->assertStringNotContainsString('SQLSTATE', $e->getMessage());
        }
    }

    /**
     * Mesma verificação, mas pelo caminho HTTP completo (Request->
     * Controller->Service), confirmando que o controller converte
     * ReportExecutionException em 500 genérico. Mocka
     * CustomReportQueryBuilder no container (em vez do facade DB::table
     * diretamente) porque middlewares de permissão/tenant/plano/assinatura
     * também usam DB::table em toda requisição autenticada — mockar o
     * facade global quebraria esse caminho antes mesmo de chegar no
     * controller.
     */
    #[Test]
    public function http_response_never_leaks_db_error_message(): void
    {
        $mockBuilder = \Mockery::mock(CustomReportQueryBuilder::class);
        $mockBuilder->shouldReceive('execute')
            ->once()
            ->andThrow(new ReportExecutionException('Falha ao executar o relatório.'));

        $this->app->instance(CustomReportQueryBuilder::class, $mockBuilder);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/reports/custom-report-definitions/preview', [
                'data_source' => 'sales',
                'metrics' => ['total_revenue'],
            ]);

        $response->assertStatus(500);
        $body = $response->getContent();
        $this->assertStringNotContainsString('SECRET123', $body);
        $this->assertStringNotContainsString('SQLSTATE', $body);
        $response->assertJsonPath('code', 'REPORT_EXECUTION_FAILED');
    }
}
