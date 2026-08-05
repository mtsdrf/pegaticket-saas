<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\Feature\Storefront\Concerns\CreatesStorefrontFixtures;
use Tests\TestCase;

/**
 * Cloudflare Turnstile (App\Services\Security\TurnstileVerificationService)
 * como camada ADICIONAL ao anti-bot existente (ver AntiBotHoneypotTest) no
 * endpoint de criação de hold do storefront.
 */
class TurnstileVerificationTest extends TestCase
{
    use CreatesSaleFixtures;
    use CreatesStorefrontFixtures;
    use RefreshDatabase;

    #[Test]
    public function allows_submission_when_turnstile_is_not_configured(): void
    {
        config(['services.turnstile.secret_key' => null]);

        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createProduct($tenant->id, ['quantity_available' => 5, 'price' => 50]);
        $event = $ticketType->event;

        $response = $this->postJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/holds", [
            'session_token' => 'sess-'.Str::random(10),
            'items' => [
                ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 1],
            ],
            'form_rendered_at' => now()->subSeconds(10)->toIso8601String(),
        ]);

        $response->assertStatus(201);
    }

    #[Test]
    public function allows_submission_with_a_valid_turnstile_token(): void
    {
        config(['services.turnstile.secret_key' => 'test-secret']);

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
        ]);

        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createProduct($tenant->id, ['quantity_available' => 5, 'price' => 50]);
        $event = $ticketType->event;

        $response = $this->postJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/holds", [
            'session_token' => 'sess-'.Str::random(10),
            'items' => [
                ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 1],
            ],
            'form_rendered_at' => now()->subSeconds(10)->toIso8601String(),
            'turnstile_token' => 'valid-token',
        ]);

        $response->assertStatus(201);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
                && $request['secret'] === 'test-secret'
                && $request['response'] === 'valid-token';
        });
    }

    #[Test]
    public function rejects_submission_with_an_invalid_turnstile_token(): void
    {
        config(['services.turnstile.secret_key' => 'test-secret']);

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => false], 200),
        ]);

        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createProduct($tenant->id, ['quantity_available' => 5, 'price' => 50]);
        $event = $ticketType->event;

        $response = $this->postJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/holds", [
            'session_token' => 'sess-'.Str::random(10),
            'items' => [
                ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 1],
            ],
            'form_rendered_at' => now()->subSeconds(10)->toIso8601String(),
            'turnstile_token' => 'invalid-token',
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function rejects_submission_when_turnstile_is_configured_but_no_token_is_sent(): void
    {
        config(['services.turnstile.secret_key' => 'test-secret']);

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
        ]);

        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createProduct($tenant->id, ['quantity_available' => 5, 'price' => 50]);
        $event = $ticketType->event;

        $response = $this->postJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/holds", [
            'session_token' => 'sess-'.Str::random(10),
            'items' => [
                ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 1],
            ],
            'form_rendered_at' => now()->subSeconds(10)->toIso8601String(),
        ]);

        $response->assertStatus(422);
        Http::assertNothingSent();
    }
}
