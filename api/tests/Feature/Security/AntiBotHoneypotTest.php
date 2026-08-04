<?php

namespace Tests\Feature\Security;

use App\Models\GuestList\GuestListEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\Feature\Storefront\Concerns\CreatesStorefrontFixtures;
use Tests\TestCase;

/**
 * Proteção anti-bot básica sem fornecedor externo (roadmap Fase 7) — ver
 * App\Services\Security\AntiBotGuardService. Cobre os dois formulários
 * públicos de maior risco de abuso automatizado: criação de hold no
 * storefront e resgate de convite de cortesia.
 */
class AntiBotHoneypotTest extends TestCase
{
    use CreatesSaleFixtures;
    use CreatesStorefrontFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    #[Test]
    public function rejects_hold_creation_when_honeypot_field_is_filled(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createProduct($tenant->id, ['quantity_available' => 5, 'price' => 50]);
        $event = $ticketType->event;

        $response = $this->postJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/holds", [
            'session_token' => 'sess-'.Str::random(10),
            'items' => [
                ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 1],
            ],
            'website' => 'https://bot-spam.example',
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function rejects_hold_creation_when_submitted_too_fast(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createProduct($tenant->id, ['quantity_available' => 5, 'price' => 50]);
        $event = $ticketType->event;

        $response = $this->postJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/holds", [
            'session_token' => 'sess-'.Str::random(10),
            'items' => [
                ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 1],
            ],
            'form_rendered_at' => now()->toIso8601String(),
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function allows_legitimate_hold_creation_with_empty_honeypot_and_enough_fill_time(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createProduct($tenant->id, ['quantity_available' => 5, 'price' => 50]);
        $event = $ticketType->event;

        $response = $this->postJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/holds", [
            'session_token' => 'sess-'.Str::random(10),
            'items' => [
                ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 1],
            ],
            'website' => '',
            'form_rendered_at' => now()->subSeconds(10)->toIso8601String(),
        ]);

        $response->assertStatus(201);
    }

    #[Test]
    public function rejects_guest_invite_redemption_when_honeypot_field_is_filled(): void
    {
        Mail::fake();
        $this->setUpTenantScopedUser('staff@antibot.test');
        $this->grantPermission('events', 'read');
        $this->grantPermission('events', 'update');

        $ticketType = $this->createProduct($this->tenant->id, ['price' => 50]);
        $event = $ticketType->event;

        $guestList = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/guest-lists', [
                'event_uuid' => $event->uuid,
                'ticket_type_uuid' => $ticketType->uuid,
                'name' => 'Lista Anti-bot',
                'quantity_per_entry' => 1,
            ])->assertStatus(201)->json('data.uuid');

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/v1/guest-lists/{$guestList}/entries", [
                'name' => 'Convidado Bot',
                'email' => 'convidadobot@test.com',
            ])->assertStatus(201);

        $entry = GuestListEntry::where('email', 'convidadobot@test.com')->firstOrFail();

        $response = $this->postJson("/api/v1/convites/{$entry->token}/resgatar", [
            'name' => 'Convidado Bot',
            'email' => 'convidadobot@test.com',
            'website' => 'https://bot-spam.example',
        ]);

        $response->assertStatus(422);
        $entry->refresh();
        $this->assertNull($entry->redeemed_at);
    }
}
