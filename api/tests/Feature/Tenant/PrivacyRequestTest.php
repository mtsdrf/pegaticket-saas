<?php

namespace Tests\Feature\Tenant;

use App\Models\Privacy\PrivacyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class PrivacyRequestTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenantScopedUser('privacy@test.com');
    }

    #[Test]
    public function tenant_user_can_create_and_list_privacy_requests(): void
    {
        $this->grantPermission('tenant-profile', 'read');
        $this->grantPermission('tenant-profile', 'update');

        $create = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/tenant-profile/privacy-requests', [
                'requester_name' => 'Maria Silva',
                'requester_email' => 'maria@example.com',
                'requester_role' => 'titular_final',
                'request_type' => 'acesso',
                'channel' => 'email',
                'subject' => 'Solicitação de acesso aos dados',
                'description' => 'Cliente solicita cópia dos dados cadastrados.',
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.request_type', 'acesso');

        $list = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/tenant-profile/privacy-requests');

        $list->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject', 'Solicitação de acesso aos dados');
    }

    #[Test]
    public function tenant_user_can_update_privacy_request_status(): void
    {
        $this->grantPermission('tenant-profile', 'update');

        $request = PrivacyRequest::create([
            'tenant_id' => $this->tenant->id,
            'requested_by_user_id' => $this->userId,
            'requester_name' => 'João',
            'requester_email' => 'joao@example.com',
            'requester_role' => 'empresa',
            'request_type' => 'correcao',
            'channel' => 'whatsapp',
            'status' => PrivacyRequest::STATUS_OPEN,
            'subject' => 'Correção cadastral',
            'description' => 'Ajustar dados de cadastro.',
            'requested_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/v1/tenant-profile/privacy-requests/{$request->uuid}", [
                'status' => 'completed',
                'resolution_notes' => 'Exportação enviada e cadastro corrigido.',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.resolution_notes', 'Exportação enviada e cadastro corrigido.');

        $this->assertNotNull($request->fresh()->resolved_at);
    }
}
