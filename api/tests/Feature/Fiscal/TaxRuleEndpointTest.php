<?php

namespace Tests\Feature\Fiscal;

use App\Models\Fiscal\TaxRule;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class TaxRuleEndpointTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenantScopedUser('tax-owner@example.com');
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
            'name' => 'Other Tenant',
            'slug' => 'other-' . Str::random(8),
            'is_active' => true,
        ]);
    }

    #[Test]
    public function creates_a_tax_rule(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/tax-rules', [
                'tax_type' => 'icms',
                'scope' => ['cfop' => ['5102'], 'uf_dest' => ['SP']],
                'rate_percent' => 18.0,
                'is_active' => true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tax_type', 'icms')
            ->assertJsonPath('data.rate_percent', 18);

        $this->assertDatabaseHas('tax_rules', [
            'uuid' => $response->json('data.uuid'),
            'tenant_id' => $this->tenant->id,
            'tax_type' => 'icms',
        ]);
    }

    #[Test]
    public function accepts_decimal_rate_with_four_places(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/tax-rules', [
                'tax_type' => 'pis',
                'rate_percent' => 0.6500,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.rate_percent', 0.65);
    }

    #[Test]
    public function rejects_validity_range_when_valid_to_before_valid_from(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/tax-rules', [
                'tax_type' => 'icms',
                'rate_percent' => 18.0,
                'valid_from' => '2026-06-01',
                'valid_to' => '2026-05-01',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['valid_to']);
    }

    #[Test]
    public function rejects_unknown_tax_type(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/tax-rules', [
                'tax_type' => 'invalid_tax',
                'rate_percent' => 1.0,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tax_type']);
    }

    #[Test]
    public function index_lists_only_current_tenant_rules(): void
    {
        $mine = TaxRule::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'tax_type' => 'iss',
            'rate_percent' => 5.0,
            'is_active' => true,
        ]);

        // Regra de OUTRO tenant — não pode vazar na listagem.
        TaxRule::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->otherTenant()->id,
            'tax_type' => 'icms',
            'rate_percent' => 12.0,
            'is_active' => true,
        ]);

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/v1/tax-rules')
            ->assertStatus(200);

        $uuids = collect($response->json('data'))->pluck('uuid');
        $this->assertTrue($uuids->contains($mine->uuid));
        $this->assertCount(1, $response->json('data'));
    }

    #[Test]
    public function updates_own_tax_rule(): void
    {
        $rule = TaxRule::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'tax_type' => 'icms',
            'rate_percent' => 18.0,
            'is_active' => true,
        ]);

        $this->withHeaders($this->auth())
            ->putJson('/api/v1/tax-rules/' . $rule->uuid, [
                'tax_type' => 'icms',
                'rate_percent' => 12.0,
                'is_active' => false,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.rate_percent', 12)
            ->assertJsonPath('data.is_active', false);
    }

    #[Test]
    public function cannot_update_another_tenants_tax_rule(): void
    {
        $foreign = TaxRule::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->otherTenant()->id,
            'tax_type' => 'icms',
            'rate_percent' => 18.0,
            'is_active' => true,
        ]);

        $this->withHeaders($this->auth())
            ->putJson('/api/v1/tax-rules/' . $foreign->uuid, [
                'tax_type' => 'icms',
                'rate_percent' => 1.0,
            ])
            ->assertStatus(404);

        // Regra do outro tenant permanece intacta.
        $this->assertEquals('18.0000', $foreign->fresh()->rate_percent);
    }

    #[Test]
    public function cannot_delete_another_tenants_tax_rule(): void
    {
        $foreign = TaxRule::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->otherTenant()->id,
            'tax_type' => 'icms',
            'rate_percent' => 18.0,
            'is_active' => true,
        ]);

        $this->withHeaders($this->auth())
            ->deleteJson('/api/v1/tax-rules/' . $foreign->uuid)
            ->assertStatus(404);

        $this->assertDatabaseHas('tax_rules', [
            'id' => $foreign->id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function deletes_own_tax_rule(): void
    {
        $rule = TaxRule::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'tax_type' => 'icms',
            'rate_percent' => 18.0,
            'is_active' => true,
        ]);

        $this->withHeaders($this->auth())
            ->deleteJson('/api/v1/tax-rules/' . $rule->uuid)
            ->assertStatus(204);

        $this->assertSoftDeleted('tax_rules', ['id' => $rule->id]);
    }

    #[Test]
    public function writes_audit_log_on_create(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/tax-rules', [
                'tax_type' => 'cofins',
                'rate_percent' => 3.0,
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('audit_logs', ['event' => 'tax_rule_created']);
    }
}
