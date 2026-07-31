<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\AccountingOfficeTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Accounting\Concerns\CreatesAccountingFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class AccountingMessageTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAccountingFixtures;
    use SetsUpTenantScopedUser;

    #[Test]
    public function both_sides_can_exchange_messages_and_status_flows(): void
    {
        $this->setUpTenantScopedUser('msg-owner@example.com');
        $this->grantPermission('accounting-access', 'read');
        $this->grantPermission('accounting-access', 'create');

        $office = $this->makeOffice();
        $link = $this->approveLink($office, $this->tenant);
        $officeHeaders = ['Authorization' => 'Bearer ' . $this->officeToken($office)];

        // Contador envia (status open)
        $this->withHeaders($officeHeaders)
            ->postJson("/api/v1/accounting/tenants/{$this->tenant->uuid}/messages", [
                'body' => 'Preciso das notas de janeiro',
                'due_date' => now()->addDays(5)->toDateString(),
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.sender_type', 'accounting_office')
            ->assertJsonPath('data.status', 'open');

        // Tenant responde -> mensagem anterior do contador vira answered
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/accounting-access-requests/{$link->uuid}/messages", [
                'body' => 'Segue em anexo',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.sender_type', 'tenant');

        // Contador lista: vê as duas mensagens, a sua original agora answered
        $list = $this->withHeaders($officeHeaders)
            ->getJson("/api/v1/accounting/tenants/{$this->tenant->uuid}/messages")
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(2, $list);
        $this->assertSame('answered', $list[0]['status']);
        $this->assertSame('open', $list[1]['status']);
    }

    #[Test]
    public function message_can_carry_an_attachment(): void
    {
        Storage::fake('public');

        $this->setUpTenantScopedUser('att-owner@example.com');
        $office = $this->makeOffice();
        $link = $this->approveLink($office, $this->tenant);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->officeToken($office)])
            ->post("/api/v1/accounting/tenants/{$this->tenant->uuid}/messages", [
                'body' => 'Documento anexo',
                'attachment' => UploadedFile::fake()->create('balanco.pdf', 20, 'application/pdf'),
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.attachment_name', 'balanco.pdf');

        $message = $link->messages()->first();
        $this->assertNotNull($message->attachment_path);
        Storage::disk('public')->assertExists($message->attachment_path);
    }

    #[Test]
    public function tenant_cannot_message_link_of_another_tenant(): void
    {
        $this->setUpTenantScopedUser('x-owner@example.com');
        $this->grantPermission('accounting-access', 'create');

        $other = $this->makeTenantWithCnpj('44444444000144');
        $office = $this->makeOffice();
        $foreignLink = $this->approveLink($office, $other);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/accounting-access-requests/{$foreignLink->uuid}/messages", [
                'body' => 'invasão',
            ])
            ->assertStatus(404);
    }
}
