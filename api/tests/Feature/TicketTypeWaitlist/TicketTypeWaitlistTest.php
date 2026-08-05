<?php

namespace Tests\Feature\TicketTypeWaitlist;

use App\Console\Commands\NotifyTicketTypeWaitlistCommand;
use App\Mail\TicketTypeWaitlistAvailableMail;
use App\Models\Event\TicketTypeWaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\Feature\Storefront\Concerns\CreatesStorefrontFixtures;
use Tests\TestCase;

/**
 * Lista de espera de TicketType esgotado (roadmap inventário) — cadastro
 * público idempotente, anti-bot, listagem staff tenant-scoped e
 * notificação por Command agendado quando a disponibilidade volta.
 */
class TicketTypeWaitlistTest extends TestCase
{
    use CreatesSaleFixtures;
    use CreatesStorefrontFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    #[Test]
    public function a_customer_joins_the_waitlist_of_a_sold_out_ticket_type(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createProduct($tenant->id, ['quantity_available' => 0]);

        $response = $this->postJson("/api/v1/loja/{$tenant->slug}/lista-espera", [
            'ticket_type_uuid' => $ticketType->uuid,
            'name' => 'Cliente Um',
            'email' => 'cliente1@test.com',
            'quantity_desired' => 2,
            'form_rendered_at' => now()->subSeconds(5)->toIso8601String(),
        ]);

        $response->assertStatus(201);

        $entry = TicketTypeWaitlistEntry::where('email', 'cliente1@test.com')->first();
        $this->assertNotNull($entry);
        $this->assertSame(2, $entry->quantity_desired);
        $this->assertSame($tenant->id, $entry->tenant_id);
        $this->assertNull($entry->notified_at);
    }

    #[Test]
    public function joining_the_waitlist_twice_with_the_same_email_is_idempotent(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createProduct($tenant->id, ['quantity_available' => 0]);

        $payload = [
            'ticket_type_uuid' => $ticketType->uuid,
            'name' => 'Cliente Dois',
            'email' => 'cliente2@test.com',
            'form_rendered_at' => now()->subSeconds(5)->toIso8601String(),
        ];

        $this->postJson("/api/v1/loja/{$tenant->slug}/lista-espera", $payload)->assertStatus(201);
        $this->postJson("/api/v1/loja/{$tenant->slug}/lista-espera", $payload)->assertStatus(201);

        $this->assertSame(
            1,
            TicketTypeWaitlistEntry::where('email', 'cliente2@test.com')
                ->where('ticket_type_id', $ticketType->id)
                ->count()
        );
    }

    #[Test]
    public function honeypot_field_blocks_the_submission(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createProduct($tenant->id, ['quantity_available' => 0]);

        $this->postJson("/api/v1/loja/{$tenant->slug}/lista-espera", [
            'ticket_type_uuid' => $ticketType->uuid,
            'name' => 'Bot',
            'email' => 'bot@test.com',
            'website' => 'http://spam.example',
            'form_rendered_at' => now()->subSeconds(5)->toIso8601String(),
        ])->assertStatus(422);

        $this->assertDatabaseMissing('ticket_type_waitlist_entries', ['email' => 'bot@test.com']);
    }

    #[Test]
    public function submitting_too_fast_is_blocked_as_suspicious(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createProduct($tenant->id, ['quantity_available' => 0]);

        $this->postJson("/api/v1/loja/{$tenant->slug}/lista-espera", [
            'ticket_type_uuid' => $ticketType->uuid,
            'name' => 'Rápido Demais',
            'email' => 'fast@test.com',
            'form_rendered_at' => now()->toIso8601String(),
        ])->assertStatus(422);

        $this->assertDatabaseMissing('ticket_type_waitlist_entries', ['email' => 'fast@test.com']);
    }

    #[Test]
    public function staff_lists_waitlist_entries_of_a_ticket_type_scoped_to_their_tenant(): void
    {
        $this->setUpTenantScopedUser('staff@waitlist.test');
        $this->grantPermission('ticket_waitlist', 'read');

        $ticketType = $this->createProduct($this->tenant->id, ['quantity_available' => 0]);

        TicketTypeWaitlistEntry::create([
            'tenant_id' => $this->tenant->id,
            'ticket_type_id' => $ticketType->id,
            'name' => 'Convidado A',
            'email' => 'a@test.com',
        ]);

        $otherTicketType = $this->createProduct($this->tenant->id, ['quantity_available' => 0]);
        TicketTypeWaitlistEntry::create([
            'tenant_id' => $this->tenant->id,
            'ticket_type_id' => $otherTicketType->id,
            'name' => 'Convidado B',
            'email' => 'b@test.com',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/v1/ticket-types/{$ticketType->uuid}/lista-espera")
            ->assertStatus(200);

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('a@test.com', $response->json('data.0.email'));
    }

    #[Test]
    public function command_notifies_only_unnotified_entries_once_availability_is_restored(): void
    {
        Mail::fake();

        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createProduct($tenant->id, ['quantity_available' => 0]);

        $pending = TicketTypeWaitlistEntry::create([
            'tenant_id' => $tenant->id,
            'ticket_type_id' => $ticketType->id,
            'name' => 'Pendente',
            'email' => 'pendente@test.com',
        ]);

        $alreadyNotified = TicketTypeWaitlistEntry::create([
            'tenant_id' => $tenant->id,
            'ticket_type_id' => $ticketType->id,
            'name' => 'Já Notificado',
            'email' => 'notificado@test.com',
            'notified_at' => now()->subDay(),
        ]);

        // Estoque continua zerado — comando não deve notificar ninguém.
        $this->artisan(NotifyTicketTypeWaitlistCommand::class)->assertSuccessful();
        Mail::assertNotSent(TicketTypeWaitlistAvailableMail::class);

        // Repõe disponibilidade (mesmo efeito de reabrir lote/cancelar venda).
        $ticketType->forceFill(['quantity_available' => 5])->save();

        $this->artisan(NotifyTicketTypeWaitlistCommand::class)->assertSuccessful();

        Mail::assertSent(TicketTypeWaitlistAvailableMail::class, 1);

        $pending->refresh();
        $alreadyNotified->refresh();

        $this->assertNotNull($pending->notified_at);
        $this->assertNotNull($alreadyNotified->notified_at);
        $this->assertTrue($alreadyNotified->notified_at->lt(now()->subHours(20)));
    }

    #[Test]
    public function command_does_not_notify_while_availability_stays_at_zero(): void
    {
        Mail::fake();

        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createProduct($tenant->id, ['quantity_available' => 0]);

        TicketTypeWaitlistEntry::create([
            'tenant_id' => $tenant->id,
            'ticket_type_id' => $ticketType->id,
            'name' => 'Ainda Esperando',
            'email' => 'esperando@test.com',
        ]);

        $this->artisan(NotifyTicketTypeWaitlistCommand::class)->assertSuccessful();

        Mail::assertNotSent(TicketTypeWaitlistAvailableMail::class);
        $this->assertDatabaseHas('ticket_type_waitlist_entries', [
            'email' => 'esperando@test.com',
            'notified_at' => null,
        ]);
    }
}
