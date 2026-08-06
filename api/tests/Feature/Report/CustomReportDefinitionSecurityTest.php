<?php

namespace Tests\Feature\Report;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

/**
 * Cobertura de segurança do construtor de relatórios personalizados
 * (roadmap 5.6) — prioridade sobre cobertura de feature, conforme decisão
 * do usuário pela versão COMPLETA (fórmulas calculadas livres).
 */
class CustomReportDefinitionSecurityTest extends TestCase
{
    use CreatesSaleFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('custom-report@test.com');
        $this->grantPermission('custom_reports', 'read');
        $this->grantPermission('custom_reports', 'create');
        $this->grantPermission('custom_reports', 'update');
        $this->grantPermission('custom_reports', 'delete');
        $this->grantPermission('sales', 'create');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    private function createSale(float $price): void
    {
        $ticketType = $this->createProduct($this->tenant->id, ['price' => $price]);
        $client = $this->createClient($this->tenant->id);

        $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'mark_as_paid' => true,
            'items' => [
                ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 1],
            ],
        ])->assertStatus(201);
    }

    #[Test]
    public function dimension_outside_whitelist_is_rejected_and_never_executes(): void
    {
        $response = $this->auth()->postJson('/api/v1/reports/custom-report-definitions/preview', [
            'data_source' => 'sales',
            'dimensions' => ['password_hash'],
            'metrics' => ['total_revenue'],
        ]);

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
    }

    #[Test]
    public function metric_outside_whitelist_is_rejected_and_never_executes(): void
    {
        $response = $this->auth()->postJson('/api/v1/reports/custom-report-definitions/preview', [
            'data_source' => 'sales',
            'metrics' => ['users.password'],
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function unknown_data_source_is_rejected_at_request_level(): void
    {
        $response = $this->auth()->postJson('/api/v1/reports/custom-report-definitions/preview', [
            'data_source' => 'raw_sql',
            'metrics' => ['total_revenue'],
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function formula_referencing_variable_outside_metric_whitelist_is_rejected_on_save(): void
    {
        $response = $this->auth()->postJson('/api/v1/reports/custom-report-definitions', [
            'name' => 'Fórmula inválida',
            'data_source' => 'sales',
            'metrics' => ['total_revenue', 'sale_count'],
            'calculated_metrics' => [
                ['name' => 'leak', 'formula' => 'unknown_metric_name * 2'],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('custom_report_definitions', 0);
    }

    #[Test]
    public function formula_calling_a_function_is_rejected_no_functions_registered(): void
    {
        $response = $this->auth()->postJson('/api/v1/reports/custom-report-definitions', [
            'name' => 'Função proibida',
            'data_source' => 'sales',
            'metrics' => ['total_revenue'],
            'calculated_metrics' => [
                ['name' => 'leak', 'formula' => 'abs(total_revenue)'],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('custom_report_definitions', 0);
    }

    #[Test]
    public function formula_with_disallowed_characters_is_rejected(): void
    {
        $response = $this->auth()->postJson('/api/v1/reports/custom-report-definitions', [
            'name' => 'Caracteres proibidos',
            'data_source' => 'sales',
            'metrics' => ['total_revenue'],
            'calculated_metrics' => [
                ['name' => 'leak', 'formula' => 'total_revenue; DROP TABLE sales;'],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('custom_report_definitions', 0);
    }

    #[Test]
    public function valid_formula_over_allowed_metrics_is_accepted_and_evaluated(): void
    {
        $this->createSale(100);
        $this->createSale(200);

        $response = $this->auth()->postJson('/api/v1/reports/custom-report-definitions/preview', [
            'data_source' => 'sales',
            'metrics' => ['total_revenue', 'sale_count'],
            'calculated_metrics' => [
                ['name' => 'avg_manual', 'formula' => 'total_revenue / sale_count'],
            ],
        ]);

        $response->assertStatus(200);
        $row = $response->json('data.0');
        $this->assertSame(300.0, (float) $row['total_revenue']);
        $this->assertSame(2, (int) $row['sale_count']);
        $this->assertSame(150.0, (float) $row['avg_manual']);
    }

    #[Test]
    public function division_by_zero_in_calculated_metric_does_not_leak_internal_error(): void
    {
        $this->createSale(100);

        $response = $this->auth()->postJson('/api/v1/reports/custom-report-definitions/preview', [
            'data_source' => 'sales',
            'dimensions' => ['status'],
            'metrics' => ['total_revenue'],
            'calculated_metrics' => [
                ['name' => 'bad_div', 'formula' => 'total_revenue / (sale_count_placeholder)'],
            ],
            'filters' => [],
        ]);

        // "sale_count_placeholder" não está entre as métricas selecionadas
        // -> rejeitado no save/preview antes mesmo de tentar avaliar.
        $response->assertStatus(422);

        $responseDivZero = $this->auth()->postJson('/api/v1/reports/custom-report-definitions/preview', [
            'data_source' => 'sales',
            'metrics' => ['total_revenue', 'discount_total'],
            'calculated_metrics' => [
                ['name' => 'bad_div', 'formula' => 'total_revenue / discount_total'],
            ],
        ]);

        $responseDivZero->assertStatus(200);
        $row = $responseDivZero->json('data.0');
        $this->assertArrayHasKey('bad_div', $row);
        $this->assertNull($row['bad_div']);
        $body = $responseDivZero->getContent();
        $this->assertStringNotContainsString('DivisionByZeroError', $body);
        $this->assertStringNotContainsString('Symfony\\Component', $body);
    }

    #[Test]
    public function dimension_limit_is_enforced(): void
    {
        $response = $this->auth()->postJson('/api/v1/reports/custom-report-definitions/preview', [
            'data_source' => 'sales',
            'dimensions' => ['status', 'origin', 'channel', 'payment_method'],
            'metrics' => ['total_revenue'],
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function calculated_metric_limit_is_enforced(): void
    {
        $calculated = [];

        for ($i = 0; $i < 6; $i++) {
            $calculated[] = ['name' => "calc_{$i}", 'formula' => 'total_revenue'];
        }

        $response = $this->auth()->postJson('/api/v1/reports/custom-report-definitions/preview', [
            'data_source' => 'sales',
            'metrics' => ['total_revenue'],
            'calculated_metrics' => $calculated,
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function result_is_always_paginated(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createSale(10 + $i);
        }

        $response = $this->auth()->postJson('/api/v1/reports/custom-report-definitions/preview', [
            'data_source' => 'sales',
            'dimensions' => ['day'],
            'metrics' => ['total_revenue'],
            'per_page' => 2,
        ]);

        $response->assertStatus(200);
        $this->assertIsArray($response->json('meta.pagination'));
        $this->assertSame(2, $response->json('meta.pagination.per_page'));
        $this->assertLessThanOrEqual(2, count($response->json('data')));
    }

    #[Test]
    public function saved_execute_endpoint_clamps_per_page_to_max(): void
    {
        $this->createSale(50);

        $definitionUuid = $this->auth()->postJson('/api/v1/reports/custom-report-definitions', [
            'name' => 'Clamp per_page',
            'data_source' => 'sales',
            'metrics' => ['total_revenue'],
        ])->assertStatus(201)->json('data.uuid');

        $response = $this->auth()->getJson(
            "/api/v1/reports/custom-report-definitions/{$definitionUuid}/execute?per_page=999999"
        );

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(100, $response->json('meta.pagination.per_page'));
    }

    #[Test]
    public function cross_tenant_data_never_leaks_into_report_results(): void
    {
        // Tenant A
        $this->createSale(111);
        $tenantAUuid = $this->tenant->uuid;
        $tokenA = $this->token;

        $definitionUuid = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->postJson('/api/v1/reports/custom-report-definitions', [
                'name' => 'Receita Tenant A',
                'data_source' => 'sales',
                'metrics' => ['total_revenue'],
            ])->assertStatus(201)->json('data.uuid');

        // Tenant B — troca completamente $this->tenant/$this->token.
        $this->setUpTenantScopedUser('custom-report-tenant-b@test.com');
        $this->grantPermission('custom_reports', 'read');
        $this->grantPermission('custom_reports', 'create');
        $this->grantPermission('custom_reports', 'delete');
        $this->grantPermission('sales', 'create');
        $tokenB = $this->token;

        $this->createSale(999);

        $this->assertNotSame($tenantAUuid, $this->tenant->uuid);

        // 1) Executar a definição salva do tenant A usando o token do
        //    tenant A tem que ver SÓ a receita do tenant A (111), nunca a
        //    soma cross-tenant (111+999) nem a receita do tenant B.
        $resultA = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson("/api/v1/reports/custom-report-definitions/{$definitionUuid}/execute")
            ->assertStatus(200);

        $this->assertSame(111.0, (float) $resultA->json('data.0.total_revenue'));

        // 2) IDOR: token do tenant B tentando executar/ler a definição do
        //    tenant A (sabendo o uuid) tem que ser bloqueado com 404, nunca
        //    vazar dado do tenant A.
        $this->withHeader('Authorization', 'Bearer '.$tokenB)
            ->getJson("/api/v1/reports/custom-report-definitions/{$definitionUuid}/execute")
            ->assertStatus(404);

        $this->withHeader('Authorization', 'Bearer '.$tokenB)
            ->getJson("/api/v1/reports/custom-report-definitions/{$definitionUuid}")
            ->assertStatus(404);

        $this->withHeader('Authorization', 'Bearer '.$tokenB)
            ->deleteJson("/api/v1/reports/custom-report-definitions/{$definitionUuid}")
            ->assertStatus(404);

        // 3) Preview ad-hoc do tenant B só pode ver a própria receita.
        $previewB = $this->withHeader('Authorization', 'Bearer '.$tokenB)
            ->postJson('/api/v1/reports/custom-report-definitions/preview', [
                'data_source' => 'sales',
                'metrics' => ['total_revenue'],
            ])->assertStatus(200);

        $this->assertSame(999.0, (float) $previewB->json('data.0.total_revenue'));
    }
}
