<?php

namespace Tests\Feature\EmailTemplate;

use App\Mail\RecompraNudgeMail;
use App\Models\EmailTemplate;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cobre o comportamento de override de App\Models\EmailTemplate dentro de
 * um Mailable real (RecompraNudgeMail), incluindo isolamento entre
 * tenants — não passa pelo CRUD HTTP (ver EmailTemplateTest), só a
 * integração Mailable <-> EmailTemplateResolverService.
 */
class EmailTemplateMailIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(): Tenant
    {
        return Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant Recompra',
            'slug' => 'tenant-recompra-'.Str::random(6),
            'is_active' => true,
        ]);
    }

    private function makeCustomer(): FinalCustomer
    {
        return FinalCustomer::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Cliente Teste',
            'email' => 'cliente-'.Str::random(6).'@test.com',
        ]);
    }

    #[Test]
    public function mailable_uses_default_subject_and_view_when_no_override_exists(): void
    {
        $tenant = $this->makeTenant();
        $customer = $this->makeCustomer();

        $mail = (new RecompraNudgeMail($tenant, $customer, 'https://loja.test/eventos/'.$tenant->slug))->build();

        $this->assertSame(
            __('messages.customers.recompra_mail_subject', ['tenant' => $tenant->name]),
            $mail->subject
        );

        $rendered = $mail->render();

        $this->assertStringContainsString($customer->name, $rendered);
    }

    #[Test]
    public function mailable_uses_customized_subject_and_body_when_override_exists(): void
    {
        $tenant = $this->makeTenant();
        $customer = $this->makeCustomer();

        EmailTemplate::create([
            'tenant_id' => $tenant->id,
            'type' => 'recompra_nudge',
            'subject' => 'Sentimos sua falta, {{cliente}}!',
            'body_html' => '<p>Olá {{cliente}}, volte para {{empresa}}: {{link}}</p>',
        ]);

        $mail = (new RecompraNudgeMail($tenant, $customer, 'https://loja.test/eventos/'.$tenant->slug))->build();

        $this->assertSame("Sentimos sua falta, {$customer->name}!", $mail->subject);

        $rendered = $mail->render();

        $this->assertStringContainsString("Olá {$customer->name}, volte para {$tenant->name}: https://loja.test/eventos/{$tenant->slug}", $rendered);
    }

    #[Test]
    public function template_override_from_one_tenant_never_leaks_into_another_tenants_mail(): void
    {
        $tenantA = $this->makeTenant();
        $tenantB = $this->makeTenant();
        $customer = $this->makeCustomer();

        EmailTemplate::create([
            'tenant_id' => $tenantA->id,
            'type' => 'recompra_nudge',
            'subject' => 'Assunto exclusivo do tenant A',
            'body_html' => '<p>Corpo exclusivo do tenant A</p>',
        ]);

        $mailB = (new RecompraNudgeMail($tenantB, $customer, 'https://loja.test/eventos/'.$tenantB->slug))->build();

        $this->assertSame(
            __('messages.customers.recompra_mail_subject', ['tenant' => $tenantB->name]),
            $mailB->subject
        );
        $this->assertStringNotContainsString('Assunto exclusivo do tenant A', $mailB->subject);
    }
}
