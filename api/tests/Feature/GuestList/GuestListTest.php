<?php

namespace Tests\Feature\GuestList;

use App\Mail\TicketDeliveryMail;
use App\Models\GuestList\GuestListEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

/**
 * "Cortesias estruturadas" (roadmap Fase 4) — lista de convidados criada
 * pelo staff, link individual por convidado, resgate público (sem login)
 * que emite uma Sale gratuita via SaleService::create() (mesmo pipeline de
 * sempre, Ticket/QR/e-mail saem do fluxo padrão de SalePaid).
 */
class GuestListTest extends TestCase
{
    use CreatesSaleFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    private function createGuestList(): array
    {
        $this->setUpTenantScopedUser('staff@guestlist.test');
        $this->grantPermission('events', 'read');
        $this->grantPermission('events', 'update');

        $ticketType = $this->createProduct($this->tenant->id, ['price' => 50]);
        $event = $ticketType->event;

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/guest-lists', [
                'event_uuid' => $event->uuid,
                'ticket_type_uuid' => $ticketType->uuid,
                'name' => 'Lista VIP',
                'quantity_per_entry' => 1,
            ])->assertStatus(201);

        return [$response->json('data.uuid'), $ticketType, $event];
    }

    #[Test]
    public function staff_creates_a_guest_list_and_adds_an_entry_with_an_individual_invite_link(): void
    {
        [$guestListUuid] = $this->createGuestList();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/v1/guest-lists/{$guestListUuid}/entries", [
                'name' => 'Convidado Um',
                'email' => 'convidado1@test.com',
            ])->assertStatus(201);

        $this->assertNotEmpty($response->json('data.invite_url'));
        $this->assertStringContainsString('/convite-ingresso/', $response->json('data.invite_url'));

        $show = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/v1/guest-lists/{$guestListUuid}")
            ->assertStatus(200);

        $this->assertSame(1, $show->json('data.entries_count'));
    }

    #[Test]
    public function guest_redeems_the_invite_and_receives_a_free_ticket(): void
    {
        Mail::fake();
        [$guestListUuid] = $this->createGuestList();

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/v1/guest-lists/{$guestListUuid}/entries", [
                'name' => 'Convidado Dois',
                'email' => 'convidado2@test.com',
            ])->assertStatus(201);

        $entry = GuestListEntry::where('email', 'convidado2@test.com')->firstOrFail();

        $invite = $this->getJson("/api/v1/convites/{$entry->token}")->assertStatus(200);
        $this->assertFalse($invite->json('data.is_redeemed'));
        $this->assertNotEmpty($invite->json('data.event.uuid'));

        $redeem = $this->postJson("/api/v1/convites/{$entry->token}/resgatar", [
            'name' => 'Convidado Dois Confirmado',
            'email' => 'convidado2@test.com',
            'document' => '11122233344',
        ])->assertStatus(200);

        $this->assertNotEmpty($redeem->json('data.sale_uuid'));
        $this->assertStringContainsString('/rastreio/', $redeem->json('data.tracking_url'));

        $entry->refresh();
        $this->assertNotNull($entry->redeemed_at);
        $this->assertNotNull($entry->sale_id);
        $this->assertSame('Convidado Dois Confirmado', $entry->name);

        $sale = $entry->sale;
        $this->assertTrue((bool) $sale->is_paid);
        $this->assertSame('0.00', $sale->total_amount);

        Mail::assertSent(TicketDeliveryMail::class);
    }

    #[Test]
    public function cannot_redeem_the_same_invite_twice(): void
    {
        Mail::fake();
        [$guestListUuid] = $this->createGuestList();

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/v1/guest-lists/{$guestListUuid}/entries", [
                'name' => 'Convidado Três',
                'email' => 'convidado3@test.com',
            ])->assertStatus(201);

        $entry = GuestListEntry::where('email', 'convidado3@test.com')->firstOrFail();

        $this->postJson("/api/v1/convites/{$entry->token}/resgatar", [
            'name' => 'Convidado Três',
            'email' => 'convidado3@test.com',
        ])->assertStatus(200);

        $this->postJson("/api/v1/convites/{$entry->token}/resgatar", [
            'name' => 'Convidado Três',
            'email' => 'convidado3@test.com',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'GUEST_LIST_ENTRY_ALREADY_REDEEMED');
    }

    #[Test]
    public function returns_404_for_an_unknown_invite_token(): void
    {
        $this->getJson('/api/v1/convites/unknown-token-xyz')->assertStatus(404);
    }
}
