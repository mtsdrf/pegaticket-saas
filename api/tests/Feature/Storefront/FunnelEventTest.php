<?php

namespace Tests\Feature\Storefront;

use App\Models\Event\Event;
use App\Models\Storefront\StorefrontFunnelEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\Feature\Storefront\Concerns\CreatesStorefrontFixtures;
use Tests\TestCase;

/**
 * POST /loja/{slug}/eventos/{eventSlug}/funnel-events (roadmap A2) — 100%
 * público, e GET /reports/analytics/funnel (staff, tenant-scoped) —
 * agregação de sessões únicas por etapa e taxa de conversão.
 */
class FunnelEventTest extends TestCase
{
    use CreatesSaleFixtures;
    use CreatesStorefrontFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    private function publishedEvent(int $tenantId): Event
    {
        $ticketType = $this->createProduct($tenantId);

        return Event::findOrFail($ticketType->event_id);
    }

    private function auth()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    #[Test]
    public function records_anonymous_funnel_event_without_authentication(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $event = $this->publishedEvent($tenant->id);

        $response = $this->postJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/funnel-events", [
            'session_id' => 'anon-session-123',
            'step' => StorefrontFunnelEvent::STEP_EVENT_VIEWED,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.step', StorefrontFunnelEvent::STEP_EVENT_VIEWED);

        $this->assertDatabaseHas('storefront_funnel_events', [
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'session_id' => 'anon-session-123',
            'step' => StorefrontFunnelEvent::STEP_EVENT_VIEWED,
        ]);
    }

    #[Test]
    public function returns_404_for_unknown_store_slug(): void
    {
        $this->postJson('/api/v1/loja/loja-inexistente/eventos/qualquer/funnel-events', [
            'session_id' => 'anon-session-123',
            'step' => StorefrontFunnelEvent::STEP_EVENT_VIEWED,
        ])->assertStatus(404);
    }

    #[Test]
    public function returns_404_when_plan_does_not_allow_storefront(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(false);
        $event = $this->publishedEvent($tenant->id);

        $this->postJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/funnel-events", [
            'session_id' => 'anon-session-123',
            'step' => StorefrontFunnelEvent::STEP_EVENT_VIEWED,
        ])->assertStatus(404);
    }

    #[Test]
    public function returns_404_for_unpublished_event(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createProduct($tenant->id);
        $event = Event::findOrFail($ticketType->event_id);
        $event->forceFill(['status' => 'rascunho'])->save();

        $this->postJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/funnel-events", [
            'session_id' => 'anon-session-123',
            'step' => StorefrontFunnelEvent::STEP_EVENT_VIEWED,
        ])->assertStatus(404);
    }

    #[Test]
    public function rejects_invalid_step(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $event = $this->publishedEvent($tenant->id);

        $this->postJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/funnel-events", [
            'session_id' => 'anon-session-123',
            'step' => 'not_a_real_step',
        ])->assertStatus(422);
    }

    #[Test]
    public function rejects_missing_session_id(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $event = $this->publishedEvent($tenant->id);

        $this->postJson("/api/v1/loja/{$tenant->slug}/eventos/{$event->slug}/funnel-events", [
            'step' => StorefrontFunnelEvent::STEP_EVENT_VIEWED,
        ])->assertStatus(422);
    }

    #[Test]
    public function funnel_report_counts_unique_sessions_per_step_and_computes_conversion_rates(): void
    {
        $this->setUpTenantScopedUser('funnel-analytics@test.com');
        $this->grantPermission('analytics', 'read');

        $event = $this->publishedEvent($this->tenant->id);

        // 4 sessões únicas viram o evento, 2 dessas 4 iniciam seleção de
        // ingresso (repetição da mesma session_id não deve contar 2x).
        foreach (['s1', 's2', 's3', 's4'] as $session) {
            StorefrontFunnelEvent::create([
                'tenant_id' => $this->tenant->id,
                'event_id' => $event->id,
                'session_id' => $session,
                'step' => StorefrontFunnelEvent::STEP_EVENT_VIEWED,
            ]);
        }

        foreach (['s1', 's1', 's2'] as $session) {
            StorefrontFunnelEvent::create([
                'tenant_id' => $this->tenant->id,
                'event_id' => $event->id,
                'session_id' => $session,
                'step' => StorefrontFunnelEvent::STEP_TICKET_SELECTION_STARTED,
            ]);
        }

        StorefrontFunnelEvent::create([
            'tenant_id' => $this->tenant->id,
            'event_id' => $event->id,
            'session_id' => 's1',
            'step' => StorefrontFunnelEvent::STEP_PAYMENT_CONFIRMED,
        ]);

        $response = $this->auth()->getJson('/api/v1/reports/analytics/funnel');

        $response->assertStatus(200)
            ->assertJsonPath('data.steps.0.step', StorefrontFunnelEvent::STEP_EVENT_VIEWED)
            ->assertJsonPath('data.steps.0.session_count', 4)
            ->assertJsonPath('data.steps.0.conversion_from_previous_percentage', null)
            ->assertJsonPath('data.steps.0.conversion_from_first_percentage', 100)
            ->assertJsonPath('data.steps.1.step', StorefrontFunnelEvent::STEP_TICKET_SELECTION_STARTED)
            ->assertJsonPath('data.steps.1.session_count', 2)
            ->assertJsonPath('data.steps.1.conversion_from_previous_percentage', 50)
            ->assertJsonPath('data.steps.1.conversion_from_first_percentage', 50)
            ->assertJsonPath('data.steps.2.step', StorefrontFunnelEvent::STEP_HOLD_CREATED)
            ->assertJsonPath('data.steps.2.session_count', 0)
            ->assertJsonPath('data.steps.2.conversion_from_previous_percentage', 0)
            ->assertJsonPath('data.steps.4.step', StorefrontFunnelEvent::STEP_PAYMENT_CONFIRMED)
            ->assertJsonPath('data.steps.4.session_count', 1)
            ->assertJsonPath('data.steps.4.conversion_from_first_percentage', 25);
    }

    #[Test]
    public function funnel_events_never_leak_between_tenants(): void
    {
        $this->setUpTenantScopedUser('funnel-isolation@test.com');
        $this->grantPermission('analytics', 'read');

        $ownEvent = $this->publishedEvent($this->tenant->id);
        StorefrontFunnelEvent::create([
            'tenant_id' => $this->tenant->id,
            'event_id' => $ownEvent->id,
            'session_id' => 'own-session',
            'step' => StorefrontFunnelEvent::STEP_EVENT_VIEWED,
        ]);

        $otherTenant = $this->createTenantWithStorefrontPlan(true);
        $otherEvent = $this->publishedEvent($otherTenant->id);
        StorefrontFunnelEvent::create([
            'tenant_id' => $otherTenant->id,
            'event_id' => $otherEvent->id,
            'session_id' => 'other-session-1',
            'step' => StorefrontFunnelEvent::STEP_EVENT_VIEWED,
        ]);
        StorefrontFunnelEvent::create([
            'tenant_id' => $otherTenant->id,
            'event_id' => $otherEvent->id,
            'session_id' => 'other-session-2',
            'step' => StorefrontFunnelEvent::STEP_EVENT_VIEWED,
        ]);

        $response = $this->auth()->getJson('/api/v1/reports/analytics/funnel');

        $response->assertStatus(200)
            ->assertJsonPath('data.steps.0.session_count', 1);
    }

    #[Test]
    public function funnel_report_requires_authentication(): void
    {
        $this->getJson('/api/v1/reports/analytics/funnel')->assertStatus(401);
    }

    #[Test]
    public function funnel_report_requires_analytics_permission(): void
    {
        $this->setUpTenantScopedUser('funnel-no-perm@test.com');

        $this->auth()->getJson('/api/v1/reports/analytics/funnel')->assertStatus(403);
    }
}
