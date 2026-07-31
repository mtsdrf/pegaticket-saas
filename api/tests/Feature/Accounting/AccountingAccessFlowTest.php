<?php

namespace Tests\Feature\Accounting;

use App\Enums\Accounting\AccountingAccessStatus;
use App\Models\Accounting\AccountingOfficeTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Accounting\Concerns\CreatesAccountingFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class AccountingAccessFlowTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAccountingFixtures;
    use SetsUpTenantScopedUser;

    private function officeHeaders(\App\Models\Accounting\AccountingOffice $office): array
    {
        return ['Authorization' => 'Bearer ' . $this->officeToken($office)];
    }

    #[Test]
    public function full_flow_request_approve_access_then_revoke_denies(): void
    {
        $this->setUpTenantScopedUser('owner@example.com');
        $this->tenant->forceFill(['cnpj' => '99888777000166'])->save();

        $office = $this->makeOffice();
        $officeHeaders = $this->officeHeaders($office);

        // 1. Contador solicita acesso -> pending
        $this->withHeaders($officeHeaders)
            ->postJson('/api/v1/accounting/access-requests', ['tenant_cnpj' => '99.888.777/0001-66'])
            ->assertStatus(201)
            ->assertJsonPath('data.status', AccountingAccessStatus::Pending->value);

        // Antes de aprovar, contador NÃO acessa relatório
        $this->withHeaders($officeHeaders)
            ->getJson("/api/v1/accounting/tenants/{$this->tenant->uuid}/reports/sales")
            ->assertStatus(403)
            ->assertJsonPath('code', 'ACCOUNTING_ACCESS_DENIED');

        // 2. Tenant lista pendências e aprova com escopos (concede todas as
        // permissões antes de qualquer request autenticado — o permset do
        // usuário é cacheado por request, conceder depois não invalida).
        $this->grantPermission('accounting-access', 'read');
        $this->grantPermission('accounting-access', 'approve');
        $this->grantPermission('accounting-access', 'revoke');

        $link = AccountingOfficeTenant::where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/accounting-access-requests')
            ->assertStatus(200)
            ->assertJsonPath('data.0.status', AccountingAccessStatus::Pending->value);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/accounting-access-requests/{$link->uuid}/approve", [
                'scopes' => ['financial.read', 'reports.read'],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', AccountingAccessStatus::Approved->value);

        // 3. Agora contador acessa relatório
        $this->withHeaders($officeHeaders)
            ->getJson("/api/v1/accounting/tenants/{$this->tenant->uuid}/reports/sales")
            ->assertStatus(200);

        // 4. Tenant revoga -> acesso negado depois
        $this->grantPermission('accounting-access', 'revoke');
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/accounting-access-requests/{$link->uuid}/revoke")
            ->assertStatus(200)
            ->assertJsonPath('data.status', AccountingAccessStatus::Revoked->value);

        $this->withHeaders($officeHeaders)
            ->getJson("/api/v1/accounting/tenants/{$this->tenant->uuid}/reports/sales")
            ->assertStatus(403);
    }

    #[Test]
    public function request_to_unknown_cnpj_fails(): void
    {
        $office = $this->makeOffice();

        $this->withHeaders($this->officeHeaders($office))
            ->postJson('/api/v1/accounting/access-requests', ['tenant_cnpj' => '00000000000000'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'ACCOUNTING_ACCESS_ERROR');
    }

    #[Test]
    public function duplicate_pending_request_is_rejected(): void
    {
        $tenant = $this->makeTenantWithCnpj('12345678000199');
        $office = $this->makeOffice();

        $this->withHeaders($this->officeHeaders($office))
            ->postJson('/api/v1/accounting/access-requests', ['tenant_cnpj' => '12345678000199'])
            ->assertStatus(201);

        $this->withHeaders($this->officeHeaders($office))
            ->postJson('/api/v1/accounting/access-requests', ['tenant_cnpj' => '12345678000199'])
            ->assertStatus(422);
    }

    #[Test]
    public function request_accepts_alphanumeric_cnpj_and_matches_the_tenant(): void
    {
        $tenant = $this->makeTenantWithCnpj('AA345678000A29');
        $office = $this->makeOffice();

        $this->withHeaders($this->officeHeaders($office))
            ->postJson('/api/v1/accounting/access-requests', ['tenant_cnpj' => 'AA.345.678/000A-29'])
            ->assertStatus(201)
            ->assertJsonPath('data.tenant.cnpj', $tenant->cnpj);
    }

    #[Test]
    public function office_without_approved_link_cannot_access_tenant(): void
    {
        $tenant = $this->makeTenantWithCnpj('12345678000100');
        $office = $this->makeOffice();

        $this->withHeaders($this->officeHeaders($office))
            ->getJson("/api/v1/accounting/tenants/{$tenant->uuid}/reports/sales")
            ->assertStatus(403)
            ->assertJsonPath('code', 'ACCOUNTING_ACCESS_DENIED');
    }

    #[Test]
    public function office_approved_on_tenant_a_cannot_access_tenant_b(): void
    {
        $tenantA = $this->makeTenantWithCnpj('11111111000111');
        $tenantB = $this->makeTenantWithCnpj('22222222000122');
        $office = $this->makeOffice();

        $this->approveLink($office, $tenantA);

        // Tenant A (approved) OK
        $this->withHeaders($this->officeHeaders($office))
            ->getJson("/api/v1/accounting/tenants/{$tenantA->uuid}/reports/sales")
            ->assertStatus(200);

        // Tenant B (sem vínculo) negado
        $this->withHeaders($this->officeHeaders($office))
            ->getJson("/api/v1/accounting/tenants/{$tenantB->uuid}/reports/sales")
            ->assertStatus(403);
    }

    #[Test]
    public function tenant_cannot_approve_link_of_another_tenant(): void
    {
        $this->setUpTenantScopedUser('owner2@example.com');
        $this->grantPermission('accounting-access', 'approve');

        $otherTenant = $this->makeTenantWithCnpj('33333333000133');
        $office = $this->makeOffice();
        $foreignLink = AccountingOfficeTenant::create([
            'accounting_office_id' => $office->id,
            'tenant_id' => $otherTenant->id,
            'status' => AccountingAccessStatus::Pending->value,
            'requested_at' => now(),
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/accounting-access-requests/{$foreignLink->uuid}/approve", [
                'scopes' => ['financial.read'],
            ])
            ->assertStatus(404);
    }
}
