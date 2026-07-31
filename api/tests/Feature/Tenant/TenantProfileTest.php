<?php

namespace Tests\Feature\Tenant;

use App\Models\Plan\Plan;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class TenantProfileTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake((string) config('media.public_disks.tenant'));
        $this->setUpTenantScopedUser('tenant-profile@test.com');
    }

    #[Test]
    public function owner_can_update_own_tenant_name(): void
    {
        $this->grantPermission('tenant-profile', 'update');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/tenant-profile', [
                'name' => 'Nome Atualizado',
            ]);

        $response->assertOk();
        $this->assertEquals('Nome Atualizado', $response->json('data.name'));
        $this->assertEquals(
            'Nome Atualizado',
            Tenant::whereKey($this->tenant->id)->value('name')
        );
    }

    #[Test]
    public function owner_can_update_own_tenant_logo(): void
    {
        $this->grantPermission('tenant-profile', 'update');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->post('/api/v1/tenant-profile', [
                '_method' => 'PUT',
                'name' => 'Com Logo',
                'logo' => UploadedFile::fake()->image('logo.png', 60, 60),
            ]);

        $response->assertOk();
        $this->assertNotNull($response->json('data.logo_url'));

        $tenant = Tenant::whereKey($this->tenant->id)->first();
        $this->assertNotNull($tenant->logo_path);
        $this->assertNotNull($tenant->logo_mime);
        Storage::disk((string) config('media.public_disks.tenant'))->assertExists($tenant->logo_path);
    }

    #[Test]
    public function user_without_permission_gets_403(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/tenant-profile', [
                'name' => 'Sem Permissao',
            ]);

        $response->assertStatus(403);
        $this->assertEquals(
            'Test Tenant',
            Tenant::whereKey($this->tenant->id)->value('name')
        );
    }

    #[Test]
    public function protected_fields_in_payload_are_ignored(): void
    {
        $this->grantPermission('tenant-profile', 'update');

        $otherPlan = Plan::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Outro Plano',
            'slug' => 'outro-plano',
            'description' => 'x',
            'sort_order' => 99,
            'is_active' => true,
        ]);

        $originalSlug = $this->tenant->slug;

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/tenant-profile', [
                'name' => 'Somente Nome Muda',
                'slug' => 'slug-hackeado',
                'is_active' => false,
                'plan_uuid' => $otherPlan->uuid,
            ]);

        $response->assertOk();

        $tenant = Tenant::whereKey($this->tenant->id)->first();
        $this->assertEquals('Somente Nome Muda', $tenant->name);
        $this->assertEquals($originalSlug, $tenant->slug);
        $this->assertTrue((bool) $tenant->is_active);
        $this->assertNull($tenant->plan_id);
    }

    #[Test]
    public function owner_can_read_own_tenant_profile(): void
    {
        $this->grantPermission('tenant-profile', 'read');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/tenant-profile');

        $response->assertOk();
        $this->assertEquals($this->tenant->uuid, $response->json('data.uuid'));
        $this->assertEquals('Test Tenant', $response->json('data.name'));
    }

    #[Test]
    public function owner_can_update_tenant_with_alphanumeric_cnpj(): void
    {
        $this->grantPermission('tenant-profile', 'update');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/tenant-profile', [
                'name' => 'Empresa Fiscal',
                'cnpj' => 'AA.345.678/000A-29',
            ]);

        $response->assertOk();
        $this->assertSame('AA345678000A29', Tenant::whereKey($this->tenant->id)->value('cnpj'));
        $this->assertSame('AA345678000A29', $response->json('data.cnpj'));
    }

    #[Test]
    public function owner_can_update_fiscal_profile_fields(): void
    {
        $this->grantPermission('tenant-profile', 'update');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/tenant-profile', [
                'name' => 'Empresa Completa',
                'cnpj' => '12.345.678/0001-99',
                'ie' => 'ISENTO',
                'im' => '998877',
                'cnae' => '5611201',
                'tax_regime' => 'simples_nacional',
                'fiscal_environment' => 'homologacao',
                'ibge_city_code' => '3550308',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.cnpj', '12345678000199')
            ->assertJsonPath('data.ie', 'ISENTO')
            ->assertJsonPath('data.im', '998877')
            ->assertJsonPath('data.cnae', '5611201')
            ->assertJsonPath('data.tax_regime', 'simples_nacional')
            ->assertJsonPath('data.fiscal_environment', 'homologacao')
            ->assertJsonPath('data.ibge_city_code', '3550308');

        $tenant = Tenant::whereKey($this->tenant->id)->firstOrFail();
        $this->assertSame('12345678000199', $tenant->cnpj);
        $this->assertSame('ISENTO', $tenant->ie);
        $this->assertSame('998877', $tenant->im);
        $this->assertSame('5611201', $tenant->cnae);
        $this->assertSame('simples_nacional', $tenant->tax_regime);
        $this->assertSame('homologacao', $tenant->fiscal_environment);
        $this->assertSame('3550308', $tenant->ibge_city_code);
    }

    #[Test]
    public function owner_can_update_fiscal_integration_fields_without_exposing_secrets_back(): void
    {
        $this->grantPermission('tenant-profile', 'update');

        $certificate = UploadedFile::fake()->createWithContent(
            'certificado-a1.pfx',
            'conteudo-falso-certificado'
        );

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->post('/api/v1/tenant-profile', [
                '_method' => 'PUT',
                'name' => 'Empresa Integrada',
                'fiscal_provider' => 'plugnotas',
                'fiscal_nfe_series' => '1',
                'fiscal_nfce_series' => '2',
                'fiscal_nfse_series' => '3',
                'fiscal_next_nfe_number' => 101,
                'fiscal_next_nfce_number' => 202,
                'fiscal_next_nfse_number' => 303,
                'fiscal_nfce_csc_id' => 'ABC123',
                'fiscal_nfce_csc_code' => 'SEGREDO-CSC',
                'fiscal_provider_api_token' => 'TOKEN-SEGREDO',
                'fiscal_certificate_a1_password' => 'SenhaA1@123',
                'fiscal_certificate_a1' => $certificate,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.fiscal_provider', 'plugnotas')
            ->assertJsonPath('data.fiscal_nfe_series', '1')
            ->assertJsonPath('data.fiscal_nfce_series', '2')
            ->assertJsonPath('data.fiscal_nfse_series', '3')
            ->assertJsonPath('data.fiscal_next_nfe_number', 101)
            ->assertJsonPath('data.fiscal_next_nfce_number', 202)
            ->assertJsonPath('data.fiscal_next_nfse_number', 303)
            ->assertJsonPath('data.fiscal_nfce_csc_id', 'ABC123')
            ->assertJsonPath('data.has_fiscal_nfce_csc_code', true)
            ->assertJsonPath('data.has_fiscal_provider_api_token', true)
            ->assertJsonPath('data.has_fiscal_certificate_a1', true)
            ->assertJsonMissingPath('data.fiscal_nfce_csc_code')
            ->assertJsonMissingPath('data.fiscal_provider_api_token')
            ->assertJsonMissingPath('data.fiscal_certificate_a1_password');

        $tenant = Tenant::whereKey($this->tenant->id)->firstOrFail();
        $this->assertSame('plugnotas', $tenant->fiscal_provider);
        $this->assertSame('1', $tenant->fiscal_nfe_series);
        $this->assertSame('2', $tenant->fiscal_nfce_series);
        $this->assertSame('3', $tenant->fiscal_nfse_series);
        $this->assertSame(101, $tenant->fiscal_next_nfe_number);
        $this->assertSame(202, $tenant->fiscal_next_nfce_number);
        $this->assertSame(303, $tenant->fiscal_next_nfse_number);
        $this->assertSame('ABC123', $tenant->fiscal_nfce_csc_id);
        $this->assertSame('SEGREDO-CSC', $tenant->fiscal_nfce_csc_code);
        $this->assertSame('TOKEN-SEGREDO', $tenant->fiscal_provider_api_token);
        $this->assertSame('SenhaA1@123', $tenant->fiscal_certificate_a1_password);
        $this->assertSame('certificado-a1.pfx', $tenant->fiscal_certificate_a1_name);
        $this->assertNotNull($tenant->fiscal_certificate_a1_data);
        $this->assertNotNull($tenant->fiscal_certificate_a1_updated_at);
    }

    #[Test]
    public function owner_can_clear_stored_fiscal_secrets_and_certificate(): void
    {
        $this->grantPermission('tenant-profile', 'update');

        $this->tenant->update([
            'fiscal_nfce_csc_code' => 'SEGREDO-CSC',
            'fiscal_provider_api_token' => 'TOKEN-SEGREDO',
            'fiscal_certificate_a1_data' => 'certificado',
            'fiscal_certificate_a1_name' => 'certificado-a1.pfx',
            'fiscal_certificate_a1_mime' => 'application/x-pkcs12',
            'fiscal_certificate_a1_password' => 'SenhaA1@123',
            'fiscal_certificate_a1_updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/tenant-profile', [
                'name' => 'Empresa Sem Segredos',
                'clear_fiscal_nfce_csc_code' => true,
                'clear_fiscal_provider_api_token' => true,
                'clear_fiscal_certificate_a1' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.has_fiscal_nfce_csc_code', false)
            ->assertJsonPath('data.has_fiscal_provider_api_token', false)
            ->assertJsonPath('data.has_fiscal_certificate_a1', false);

        $tenant = Tenant::whereKey($this->tenant->id)->firstOrFail();
        $this->assertNull($tenant->fiscal_nfce_csc_code);
        $this->assertNull($tenant->fiscal_provider_api_token);
        $this->assertNull($tenant->fiscal_certificate_a1_data);
        $this->assertNull($tenant->fiscal_certificate_a1_name);
        $this->assertNull($tenant->fiscal_certificate_a1_mime);
        $this->assertNull($tenant->fiscal_certificate_a1_password);
        $this->assertNull($tenant->fiscal_certificate_a1_updated_at);
    }

    #[Test]
    public function multipart_request_accepts_boolean_clear_flags_sent_as_strings(): void
    {
        $this->grantPermission('tenant-profile', 'update');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->post('/api/v1/tenant-profile', [
                '_method' => 'PUT',
                'name' => 'Empresa Multipart',
                'fiscal_provider' => 'manual',
                'clear_fiscal_nfce_csc_code' => 'false',
                'clear_fiscal_provider_api_token' => 'false',
                'clear_fiscal_certificate_a1' => 'false',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Empresa Multipart');

        $this->assertSame('Empresa Multipart', Tenant::whereKey($this->tenant->id)->value('name'));
    }
}
