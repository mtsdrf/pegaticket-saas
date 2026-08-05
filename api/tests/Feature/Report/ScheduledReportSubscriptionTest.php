<?php

namespace Tests\Feature\Report;

use App\Mail\ScheduledReportSummaryMail;
use App\Models\Report\ScheduledReportSubscription;
use App\Models\Tenant\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

/**
 * CRUD tenant-scoped de assinatura de resumo agendado (roadmap A2) + disparo
 * via App\Console\Commands\SendScheduledReportSummariesCommand, respeitando
 * ScheduledReportSubscription::isDue e usando CommunicationDispatcherService
 * (nunca Mail::send() direto).
 */
class ScheduledReportSubscriptionTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('scheduled-report@test.com');
    }

    private function auth()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    #[Test]
    public function creates_a_subscription_for_the_active_tenant(): void
    {
        $this->grantPermission('reports', 'create');

        $response = $this->auth()->postJson('/api/v1/reports/scheduled-report-subscriptions', [
            'recipient_email' => 'relatorios@empresa.com',
            'frequency' => ScheduledReportSubscription::FREQUENCY_WEEKLY,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.recipient_email', 'relatorios@empresa.com')
            ->assertJsonPath('data.frequency', ScheduledReportSubscription::FREQUENCY_WEEKLY);

        $this->assertDatabaseHas('scheduled_report_subscriptions', [
            'tenant_id' => $this->tenant->id,
            'recipient_email' => 'relatorios@empresa.com',
            'frequency' => ScheduledReportSubscription::FREQUENCY_WEEKLY,
        ]);
    }

    #[Test]
    public function rejects_invalid_frequency(): void
    {
        $this->grantPermission('reports', 'create');

        $this->auth()->postJson('/api/v1/reports/scheduled-report-subscriptions', [
            'recipient_email' => 'relatorios@empresa.com',
            'frequency' => 'monthly',
        ])->assertStatus(422);
    }

    #[Test]
    public function rejects_invalid_email(): void
    {
        $this->grantPermission('reports', 'create');

        $this->auth()->postJson('/api/v1/reports/scheduled-report-subscriptions', [
            'recipient_email' => 'not-an-email',
            'frequency' => ScheduledReportSubscription::FREQUENCY_DAILY,
        ])->assertStatus(422);
    }

    #[Test]
    public function lists_only_subscriptions_of_the_active_tenant(): void
    {
        $this->grantPermission('reports', 'create');
        $this->grantPermission('reports', 'read');

        $this->auth()->postJson('/api/v1/reports/scheduled-report-subscriptions', [
            'recipient_email' => 'own@empresa.com',
            'frequency' => ScheduledReportSubscription::FREQUENCY_DAILY,
        ])->assertStatus(201);

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-'.Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        ScheduledReportSubscription::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'recipient_email' => 'other@empresa.com',
            'frequency' => ScheduledReportSubscription::FREQUENCY_WEEKLY,
        ]);

        $response = $this->auth()->getJson('/api/v1/reports/scheduled-report-subscriptions');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertSame('own@empresa.com', $response->json('data.0.recipient_email'));
    }

    #[Test]
    public function cancels_a_subscription_of_the_active_tenant(): void
    {
        $this->grantPermission('reports', 'create');
        $this->grantPermission('reports', 'delete');

        $created = $this->auth()->postJson('/api/v1/reports/scheduled-report-subscriptions', [
            'recipient_email' => 'cancelar@empresa.com',
            'frequency' => ScheduledReportSubscription::FREQUENCY_DAILY,
        ])->json('data');

        $this->auth()->deleteJson('/api/v1/reports/scheduled-report-subscriptions/'.$created['uuid'])
            ->assertStatus(204);

        $this->assertSoftDeleted('scheduled_report_subscriptions', [
            'uuid' => $created['uuid'],
        ]);
    }

    #[Test]
    public function cannot_cancel_a_subscription_belonging_to_another_tenant(): void
    {
        $this->grantPermission('reports', 'delete');

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-'.Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $foreign = ScheduledReportSubscription::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'recipient_email' => 'foreign@empresa.com',
            'frequency' => ScheduledReportSubscription::FREQUENCY_DAILY,
        ]);

        $this->auth()->deleteJson('/api/v1/reports/scheduled-report-subscriptions/'.$foreign->uuid)
            ->assertStatus(404);
    }

    #[Test]
    public function requires_authentication(): void
    {
        // setUpTenantScopedUser() usa withHeader() internamente pra logar
        // e trocar de tenant — isso é acumulativo em $this->defaultHeaders
        // (não por-chamada), então sem remover aqui esta requisição "sem
        // auth" sairia autenticada por acidente (mesmo padrão de
        // OnboardingChecklistTest::unauthenticated_request_is_rejected).
        $this->withoutHeader('Authorization')
            ->getJson('/api/v1/reports/scheduled-report-subscriptions')
            ->assertStatus(401);
    }

    #[Test]
    public function command_sends_summary_via_dispatcher_for_a_subscription_never_sent_before(): void
    {
        Mail::fake();

        $subscription = ScheduledReportSubscription::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'recipient_email' => 'nunca-enviado@empresa.com',
            'frequency' => ScheduledReportSubscription::FREQUENCY_DAILY,
        ]);

        $this->artisan('reports:send-scheduled-summaries')->assertExitCode(0);

        Mail::assertSent(ScheduledReportSummaryMail::class, function (ScheduledReportSummaryMail $mail) use ($subscription) {
            return $mail->tenant->id === $subscription->tenant_id;
        });

        $this->assertNotNull(ScheduledReportSubscription::whereKey($subscription->id)->value('last_sent_at'));
    }

    #[Test]
    public function command_does_not_resend_a_daily_subscription_sent_less_than_a_day_ago(): void
    {
        Mail::fake();

        $lastSentAt = now()->subHours(2);

        $subscription = ScheduledReportSubscription::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'recipient_email' => 'recem-enviado@empresa.com',
            'frequency' => ScheduledReportSubscription::FREQUENCY_DAILY,
            'last_sent_at' => $lastSentAt,
        ]);

        $this->artisan('reports:send-scheduled-summaries')->assertExitCode(0);

        Mail::assertNotSent(ScheduledReportSummaryMail::class);
        $this->assertSame(
            $lastSentAt->format('Y-m-d H:i:s'),
            Carbon::parse(ScheduledReportSubscription::whereKey($subscription->id)->value('last_sent_at'))->format('Y-m-d H:i:s')
        );
    }

    #[Test]
    public function command_resends_a_weekly_subscription_only_after_seven_days(): void
    {
        Mail::fake();

        $stale = ScheduledReportSubscription::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'recipient_email' => 'semanal-vencido@empresa.com',
            'frequency' => ScheduledReportSubscription::FREQUENCY_WEEKLY,
            'last_sent_at' => now()->subDays(8),
        ]);

        $recentLastSentAt = now()->subDays(3);

        $recent = ScheduledReportSubscription::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'recipient_email' => 'semanal-em-dia@empresa.com',
            'frequency' => ScheduledReportSubscription::FREQUENCY_WEEKLY,
            'last_sent_at' => $recentLastSentAt,
        ]);

        $this->artisan('reports:send-scheduled-summaries')->assertExitCode(0);

        Mail::assertSent(ScheduledReportSummaryMail::class, 1);
        Mail::assertSent(ScheduledReportSummaryMail::class, function (ScheduledReportSummaryMail $mail) use ($stale) {
            return $mail->tenant->id === $stale->tenant_id;
        });
        $this->assertSame(
            $recentLastSentAt->format('Y-m-d H:i:s'),
            Carbon::parse(ScheduledReportSubscription::whereKey($recent->id)->value('last_sent_at'))->format('Y-m-d H:i:s')
        );
    }

    #[Test]
    public function command_records_a_communication_log_entry_for_the_dispatched_summary(): void
    {
        Mail::fake();

        $subscription = ScheduledReportSubscription::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'recipient_email' => 'log@empresa.com',
            'frequency' => ScheduledReportSubscription::FREQUENCY_DAILY,
        ]);

        $this->artisan('reports:send-scheduled-summaries');

        $this->assertDatabaseHas('communication_logs', [
            'tenant_id' => $subscription->tenant_id,
            'type' => 'scheduled_report_summary',
            'recipient_email' => 'log@empresa.com',
            'status' => 'sent',
        ]);
    }
}
