<?php

namespace Tests\Feature\EmailTemplate;

use App\Models\EmailTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

/**
 * CRUD (index/show/update/reset) do editor de templates de e-mail por
 * tenant. Só cobre os types listados em
 * App\Services\EmailTemplate\EmailTemplateService::CUSTOMIZABLE_TYPES —
 * password_reset/portal_otp/email_confirmation ficam fora de propósito
 * (fluxo de segurança/plataforma).
 */
class EmailTemplateTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('email-template-user@test.com');
        $this->grantPermission('email_templates', 'read');
        $this->grantPermission('email_templates', 'create');
        $this->grantPermission('email_templates', 'update');
        $this->grantPermission('email_templates', 'delete');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    #[Test]
    public function index_lists_all_customizable_types_with_no_override_by_default(): void
    {
        $response = $this->auth()->getJson('/api/v1/email-templates');

        $response->assertStatus(200);

        $types = collect($response->json('data'))->pluck('type')->all();

        $this->assertContains('ticket_delivery', $types);
        $this->assertContains('event_reminder', $types);
        $this->assertContains('recompra_nudge', $types);
        $this->assertContains('waitlist_available', $types);
        $this->assertContains('tenant_user_invite', $types);
        $this->assertNotContains('password_reset', $types);
        $this->assertNotContains('portal_otp', $types);
        $this->assertNotContains('email_confirmation', $types);

        $response->assertJsonPath('data.0.is_customized', false);
    }

    #[Test]
    public function update_creates_an_override_and_show_returns_it(): void
    {
        $store = $this->auth()->putJson('/api/v1/email-templates/recompra_nudge', [
            'subject' => 'Sentimos sua falta, {{cliente}}!',
            'body_html' => '<p>Olá {{cliente}}</p>',
        ]);

        $store->assertStatus(200)
            ->assertJsonPath('data.type', 'recompra_nudge')
            ->assertJsonPath('data.is_customized', true)
            ->assertJsonPath('data.subject', 'Sentimos sua falta, {{cliente}}!');

        $show = $this->auth()->getJson('/api/v1/email-templates/recompra_nudge');

        $show->assertStatus(200)
            ->assertJsonPath('data.is_customized', true)
            ->assertJsonPath('data.body_html', '<p>Olá {{cliente}}</p>');

        $this->assertSame(1, EmailTemplate::where('tenant_id', $this->tenant->id)->where('type', 'recompra_nudge')->count());
    }

    #[Test]
    public function update_twice_upserts_instead_of_creating_a_duplicate_row(): void
    {
        $this->auth()->putJson('/api/v1/email-templates/waitlist_available', [
            'subject' => 'Primeira versão',
        ])->assertStatus(200);

        $this->auth()->putJson('/api/v1/email-templates/waitlist_available', [
            'subject' => 'Segunda versão',
        ])->assertStatus(200)->assertJsonPath('data.subject', 'Segunda versão');

        $this->assertSame(1, EmailTemplate::where('tenant_id', $this->tenant->id)->where('type', 'waitlist_available')->count());
    }

    #[Test]
    public function destroy_resets_the_template_back_to_default(): void
    {
        $this->auth()->putJson('/api/v1/email-templates/tenant_user_invite', [
            'subject' => 'Convite customizado',
        ])->assertStatus(200);

        $this->auth()->deleteJson('/api/v1/email-templates/tenant_user_invite')->assertStatus(204);

        $show = $this->auth()->getJson('/api/v1/email-templates/tenant_user_invite');

        $show->assertStatus(200)->assertJsonPath('data.is_customized', false);

        $this->assertSame(0, EmailTemplate::where('tenant_id', $this->tenant->id)->where('type', 'tenant_user_invite')->count());
    }

    #[Test]
    public function destroy_is_a_no_op_when_no_override_exists(): void
    {
        $this->auth()->deleteJson('/api/v1/email-templates/recompra_nudge')->assertStatus(204);
    }

    #[Test]
    public function security_and_platform_types_cannot_be_customized_via_crud(): void
    {
        $this->auth()->putJson('/api/v1/email-templates/password_reset', [
            'subject' => 'Tentativa de burlar segurança',
        ])->assertStatus(422)->assertJsonPath('code', 'INVALID_EMAIL_TEMPLATE_TYPE');

        $this->auth()->getJson('/api/v1/email-templates/portal_otp')
            ->assertStatus(422)->assertJsonPath('code', 'INVALID_EMAIL_TEMPLATE_TYPE');

        $this->auth()->deleteJson('/api/v1/email-templates/email_confirmation')
            ->assertStatus(422)->assertJsonPath('code', 'INVALID_EMAIL_TEMPLATE_TYPE');

        $this->assertSame(0, EmailTemplate::count());
    }

    #[Test]
    public function a_tenants_override_never_leaks_into_another_tenants_listing(): void
    {
        $this->auth()->putJson('/api/v1/email-templates/recompra_nudge', [
            'subject' => 'Assunto exclusivo do tenant A',
        ])->assertStatus(200);

        $this->setUpTenantScopedUser('email-template-user-b@test.com');
        $this->grantPermission('email_templates', 'read');

        $show = $this->auth()->getJson('/api/v1/email-templates/recompra_nudge');

        $show->assertStatus(200)->assertJsonPath('data.is_customized', false);
    }
}
