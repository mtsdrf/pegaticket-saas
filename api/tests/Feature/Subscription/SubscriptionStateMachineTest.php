<?php

namespace Tests\Feature\Subscription;

use App\Enums\Subscription\SubscriptionStatus;
use App\Exceptions\Subscription\InvalidSubscriptionTransitionException;
use App\Models\Subscription\SubscriptionEvent;
use App\Services\Subscription\SubscriptionStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Subscription\Concerns\CreatesSubscriptionFixtures;
use Tests\TestCase;

class SubscriptionStateMachineTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSubscriptionFixtures;

    private function machine(): SubscriptionStateMachine
    {
        return app(SubscriptionStateMachine::class);
    }

    #[Test]
    public function it_allows_valid_transitions(): void
    {
        $machine = $this->machine();

        $this->assertTrue($machine->canTransition(SubscriptionStatus::Pending, SubscriptionStatus::Trialing));
        $this->assertTrue($machine->canTransition(SubscriptionStatus::Trialing, SubscriptionStatus::Active));
        $this->assertTrue($machine->canTransition(SubscriptionStatus::Active, SubscriptionStatus::PastDue));
        $this->assertTrue($machine->canTransition(SubscriptionStatus::PastDue, SubscriptionStatus::Active));
        $this->assertTrue($machine->canTransition(SubscriptionStatus::PastDue, SubscriptionStatus::Suspended));
        $this->assertTrue($machine->canTransition(SubscriptionStatus::Active, SubscriptionStatus::CancelScheduled));
        $this->assertTrue($machine->canTransition(SubscriptionStatus::CancelScheduled, SubscriptionStatus::Canceled));
    }

    #[Test]
    public function it_rejects_invalid_transitions(): void
    {
        $machine = $this->machine();

        $this->assertFalse($machine->canTransition(SubscriptionStatus::Canceled, SubscriptionStatus::Active));
        $this->assertFalse($machine->canTransition(SubscriptionStatus::Pending, SubscriptionStatus::PastDue));
        $this->assertFalse($machine->canTransition(SubscriptionStatus::Trialing, SubscriptionStatus::Suspended));
        $this->assertFalse($machine->canTransition(SubscriptionStatus::Suspended, SubscriptionStatus::PastDue));
    }

    #[Test]
    public function transition_persists_status_and_writes_event(): void
    {
        $subscription = $this->createSubscription(['status' => 'active']);

        $this->machine()->transition($subscription, SubscriptionStatus::PastDue);

        $this->assertSame('past_due', $subscription->fresh()->status);
        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $subscription->id,
            'type' => 'past_due',
        ]);
    }

    #[Test]
    public function transition_throws_and_persists_nothing_when_invalid(): void
    {
        $subscription = $this->createSubscription(['status' => 'canceled']);

        try {
            $this->machine()->transition($subscription, SubscriptionStatus::Active);
            $this->fail('Expected InvalidSubscriptionTransitionException.');
        } catch (InvalidSubscriptionTransitionException) {
            // esperado
        }

        $this->assertSame('canceled', $subscription->fresh()->status);
        $this->assertSame(0, SubscriptionEvent::where('subscription_id', $subscription->id)->count());
    }
}
