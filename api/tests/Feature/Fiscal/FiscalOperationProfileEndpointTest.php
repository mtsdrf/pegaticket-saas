<?php

namespace Tests\Feature\Fiscal;

use App\Models\Fiscal\FiscalOperationProfile;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class FiscalOperationProfileEndpointTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenantScopedUser('fiscal-profile@example.com');
        $this->grantPermission('tax-rules', 'read');
        $this->grantPermission('tax-rules', 'create');
        $this->grantPermission('tax-rules', 'update');
        $this->grantPermission('tax-rules', 'delete');
    }

    private function auth(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    private function otherTenant(): Tenant
    {
        return Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Fiscal Tenant',
            'slug' => 'other-fiscal-' . Str::random(8),
            'is_active' => true,
        ]);
    }

    #[Test]
    public function creates_a_fiscal_operation_profile(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/fiscal-operation-profiles', [
                'name' => 'Venda balcão NFC-e',
                'operation_nature' => 'sale',
                'document_type' => 'nfce',
                'default_cfop' => '5102',
                'scope' => [
                    'order_origin' => ['pdv', 'counter'],
                    'destination_type' => ['consumer_final'],
                ],
                'is_active' => true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Venda balcão NFC-e')
            ->assertJsonPath('data.document_type', 'nfce');

        $this->assertDatabaseHas('fiscal_operation_profiles', [
            'uuid' => $response->json('data.uuid'),
            'tenant_id' => $this->tenant->id,
            'default_cfop' => '5102',
        ]);
    }

    #[Test]
    public function lists_only_current_tenant_profiles(): void
    {
        $mine = FiscalOperationProfile::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Meu perfil',
            'operation_nature' => 'sale',
            'document_type' => 'nfce',
            'is_active' => true,
        ]);

        FiscalOperationProfile::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->otherTenant()->id,
            'name' => 'Perfil de fora',
            'operation_nature' => 'sale',
            'document_type' => 'nfe',
            'is_active' => true,
        ]);

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/v1/fiscal-operation-profiles')
            ->assertOk();

        $uuids = collect($response->json('data'))->pluck('uuid');
        $this->assertTrue($uuids->contains($mine->uuid));
        $this->assertCount(1, $response->json('data'));
    }

    #[Test]
    public function cannot_update_another_tenants_profile(): void
    {
        $foreign = FiscalOperationProfile::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->otherTenant()->id,
            'name' => 'Perfil externo',
            'operation_nature' => 'sale',
            'document_type' => 'nfce',
            'is_active' => true,
        ]);

        $this->withHeaders($this->auth())
            ->putJson('/api/v1/fiscal-operation-profiles/' . $foreign->uuid, [
                'name' => 'Tentativa',
                'operation_nature' => 'sale',
                'document_type' => 'nfe',
            ])
            ->assertStatus(404);
    }

    #[Test]
    public function deletes_own_profile(): void
    {
        $profile = FiscalOperationProfile::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Perfil para excluir',
            'operation_nature' => 'service',
            'document_type' => 'nfse',
            'is_active' => true,
        ]);

        $this->withHeaders($this->auth())
            ->deleteJson('/api/v1/fiscal-operation-profiles/' . $profile->uuid)
            ->assertStatus(204);

        $this->assertSoftDeleted('fiscal_operation_profiles', ['id' => $profile->id]);
    }
}
