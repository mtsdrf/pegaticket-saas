<?php

namespace Tests\Feature\Communication;

use App\Mail\PasswordResetMail;
use App\Models\CommunicationLog;
use App\Models\Tenant\Tenant;
use App\Models\User\User;
use App\Services\Communication\CommunicationDispatcherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CommunicationDispatcherServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Dispatcher User',
            'email' => 'dispatcher@communication.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    #[Test]
    public function it_logs_sent_status_on_successful_send(): void
    {
        Mail::fake();

        $dispatcher = app(CommunicationDispatcherService::class);

        $resetUrl = 'https://example.test/redefinir-senha/token';

        $dispatcher->send(
            'password_reset',
            new PasswordResetMail($this->user, $resetUrl),
            $this->user->email
        );

        Mail::assertSent(PasswordResetMail::class);

        $log = CommunicationLog::first();

        $this->assertNotNull($log);
        $this->assertSame('password_reset', $log->type);
        $this->assertSame('sent', $log->status);
        $this->assertSame($this->user->email, $log->recipient_email);
        $this->assertNull($log->tenant_id);
        $this->assertNotNull($log->sent_at);
        $this->assertNull($log->error_message);
    }

    #[Test]
    public function it_logs_tenant_id_when_provided(): void
    {
        Mail::fake();

        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant Comunicação',
            'slug' => 'tenant-comunicacao-'.Str::random(6),
            'is_active' => true,
        ]);

        $dispatcher = app(CommunicationDispatcherService::class);

        $dispatcher->send(
            'password_reset',
            new PasswordResetMail($this->user, 'https://example.test/x'),
            $this->user->email,
            $tenant->id
        );

        $log = CommunicationLog::first();

        $this->assertSame($tenant->id, $log->tenant_id);
    }

    #[Test]
    public function it_logs_failed_status_and_rethrows_original_exception_on_failure(): void
    {
        Mail::shouldReceive('to')
            ->once()
            ->andReturnSelf();

        Mail::shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('SMTP connection refused'));

        $dispatcher = app(CommunicationDispatcherService::class);

        try {
            $dispatcher->send(
                'password_reset',
                new PasswordResetMail($this->user, 'https://example.test/x'),
                $this->user->email
            );

            $this->fail('Esperava que a exceção original fosse relançada.');
        } catch (\RuntimeException $e) {
            $this->assertSame('SMTP connection refused', $e->getMessage());
        }

        $log = CommunicationLog::first();

        $this->assertNotNull($log);
        $this->assertSame('failed', $log->status);
        $this->assertSame('SMTP connection refused', $log->error_message);
        $this->assertNull($log->sent_at);
    }

    #[Test]
    public function it_truncates_long_error_messages_to_500_characters(): void
    {
        $longMessage = str_repeat('x', 1000);

        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException($longMessage));

        $dispatcher = app(CommunicationDispatcherService::class);

        try {
            $dispatcher->send(
                'password_reset',
                new PasswordResetMail($this->user, 'https://example.test/x'),
                $this->user->email
            );
        } catch (\RuntimeException $e) {
            // esperado
        }

        $log = CommunicationLog::first();

        $this->assertSame(500, strlen($log->error_message));
    }
}
