<?php

namespace Tests\Feature\Tenant;

use App\Models\AuditLog;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\FinalCustomer\FinalCustomerTenantLink;
use App\Models\Location\Bairro;
use App\Models\Location\Cidade;
use App\Models\Location\Endereco;
use App\Models\Location\Estado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\GeneratesUniqueUf;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;
use ZipArchive;

/**
 * Roadmap A1.2 — POST /tenant-data-export, ZIP com 1 CSV por entidade
 * principal do tenant.
 */
class TenantDataExportTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use GeneratesUniqueUf;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('export-user@test.com');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    protected function createFinalCustomer(int $tenantId): FinalCustomer
    {
        $estado = Estado::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Estado ' . Str::random(6),
            'uf' => $this->nextUf(),
        ]);

        $cidade = Cidade::create([
            'uuid' => (string) Str::uuid(),
            'estado_id' => $estado->id,
            'name' => 'Cidade ' . Str::random(6),
        ]);

        $bairro = Bairro::create([
            'uuid' => (string) Str::uuid(),
            'cidade_id' => $cidade->id,
            'name' => 'Bairro ' . Str::random(6),
        ]);

        $endereco = Endereco::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'estado_id' => $estado->id,
            'cidade_id' => $cidade->id,
            'bairro_id' => $bairro->id,
            'logradouro' => 'Rua Teste, 123',
            'is_active' => true,
        ]);

        $finalCustomer = FinalCustomer::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Client Export ' . Str::random(6),
            'email' => 'export-' . Str::random(8) . '@test.com',
        ]);

        FinalCustomerTenantLink::create([
            'uuid' => (string) Str::uuid(),
            'final_customer_id' => $finalCustomer->id,
            'tenant_id' => $tenantId,
            'endereco_id' => $endereco->id,
            'is_trusted' => true,
            'is_active' => true,
            'confirmed_at' => now(),
        ]);

        return $finalCustomer;
    }

    #[Test]
    public function user_without_permission_cannot_export_data(): void
    {
        $this->auth()->postJson('/api/v1/tenant-data-export')->assertStatus(403);
    }

    #[Test]
    public function exports_a_zip_with_one_csv_per_entity_and_writes_audit_log(): void
    {
        $this->grantPermission('tenant-profile', 'export');
        $finalCustomer = $this->createFinalCustomer($this->tenant->id);

        AuditLog::query()->delete();

        $response = $this->auth()->postJson('/api/v1/tenant-data-export');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/zip');

        $tempFile = tempnam(sys_get_temp_dir(), 'export-test-');
        file_put_contents($tempFile, $response->streamedContent());

        $zip = new ZipArchive();
        $opened = $zip->open($tempFile);
        $this->assertTrue($opened === true);

        $this->assertNotFalse($zip->locateName('customers.csv'));
        $this->assertNotFalse($zip->locateName('ticket_types.csv'));
        $this->assertNotFalse($zip->locateName('orders.csv'));

        $customersCsv = $zip->getFromName('customers.csv');
        $this->assertStringContainsString($finalCustomer->name, $customersCsv);

        $zip->close();
        unlink($tempFile);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'tenant_data_exported',
        ]);
    }
}
